<?php

declare(strict_types=1);

namespace LicenseApi;

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $forceSecure = filter_var(Env::get('SESSION_SECURE', ''), FILTER_VALIDATE_BOOLEAN)
            || strtolower((string) Env::get('APP_ENV', 'local')) === 'production';
        $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        // Only trust forwarded proto when explicitly behind a trusted proxy.
        $trustProxy = filter_var(Env::get('TRUST_PROXY', 'false'), FILTER_VALIDATE_BOOLEAN);
        if ($trustProxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $https = true;
        }
        $secure = $forceSecure || $https;

        // Persistência: cliente + admin (padrão 30 dias). Renova a cada request (sliding).
        $lifetimeDays = max(1, (int) Env::get('SESSION_LIFETIME_DAYS', '30'));
        $lifetime = $lifetimeDays * 86400;
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_lifetime', (string) $lifetime);
        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
        session_start();

        // Sliding window: cada visita empurra +30 dias (cookie + ficheiro de sessão).
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            setcookie(session_name(), session_id(), [
                'expires' => time() + $lifetime,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $secure,
            ]);
        }
    }

    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        $csp = "default-src 'self'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."frame-ancestors 'none'; "
            ."img-src 'self' data:; "
            ."font-src 'self' https://fonts.gstatic.com; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."script-src 'self'; "
            ."object-src 'none'";
        header('Content-Security-Policy: '.$csp);
        if (strtolower((string) Env::get('APP_ENV', 'local')) === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function cspNonce(): string
    {
        return '';
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_csrf'];
    }

    public static function rotateCsrfToken(): string
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));

        return (string) $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        $session = (string) ($_SESSION['_csrf'] ?? '');

        return $session !== '' && is_string($token) && hash_equals($session, $token);
    }

    public static function rateLimit(string $key, int $max, int $windowSeconds, string $basePath): bool
    {
        $dir = $basePath.'/storage/rate';
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $file = $dir.'/'.hash('sha256', $key).'.json';
        $now = time();
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return true; // fail open only if filesystem broken
        }
        try {
            if (! flock($fp, LOCK_EX)) {
                return true;
            }
            $raw = stream_get_contents($fp);
            $data = ['hits' => []];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) {
                    $data = $decoded;
                }
            }
            $hits = array_values(array_filter(
                $data['hits'],
                static fn ($t) => is_int($t) && ($now - $t) < $windowSeconds
            ));
            if (count($hits) >= $max) {
                return false;
            }
            $hits[] = $now;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(['hits' => $hits]));
            fflush($fp);

            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * @return list<string>
     */
    public static function weakSecretDefaults(): array
    {
        return [
            '',
            'change-me-to-a-long-random-string',
            'dev-signing-key',
            'local-webhook-secret-change-me',
            'admin123',
            'password',
            'secret',
        ];
    }

    public static function assertProductionSecrets(): void
    {
        if (strtolower((string) Env::get('APP_ENV', 'local')) !== 'production') {
            return;
        }
        $weak = self::weakSecretDefaults();
        $signing = (string) Env::get('LICENSE_SIGNING_KEY', '');
        $adminPass = (string) Env::get('ADMIN_PASSWORD', '');
        $problems = [];
        if (in_array($signing, $weak, true) || strlen($signing) < 32) {
            $problems[] = 'LICENSE_SIGNING_KEY';
        }
        if (in_array($adminPass, $weak, true)) {
            $problems[] = 'ADMIN_PASSWORD (bootstrap default)';
        }
        if ($problems !== []) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Production refused: weak or missing secrets: ".implode(', ', $problems);
            exit;
        }
    }

    /**
     * @return string|null Error message, or null if ok
     */
    public static function validatePassword(string $password, int $minLength = 8, bool $requireMixed = false): ?string
    {
        if (strlen($password) < $minLength) {
            return "A senha deve ter no mínimo {$minLength} caracteres.";
        }
        if (preg_match('/\s/u', $password)) {
            return 'A senha não pode conter espaços.';
        }
        if ($requireMixed) {
            if (! preg_match('/[A-Za-z]/u', $password) || ! preg_match('/[0-9]/u', $password)) {
                return 'A senha deve ter letras e números.';
            }
        }

        return null;
    }
}
