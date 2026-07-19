<?php

declare(strict_types=1);

namespace LicenseApi;

final class Mailer
{
    public function __construct(
        private string $basePath,
        private SettingsStore $settings,
        /** @var array<string, string|null> */
        private array $envFallback = [],
    ) {}

    /**
     * @return array{ok:bool,message:string}
     */
    public function send(string $to, string $subject, string $html): array
    {
        $cfg = $this->settings->mailConfig($this->envFallback);
        $to = SafeUrl::stripCrLf($to);
        $subject = SafeUrl::stripCrLf($subject);
        $this->log($to, $subject, 'queued');

        $host = trim((string) ($cfg['mail_host'] ?? ''));
        if ($host === '') {
            return ['ok' => true, 'message' => 'Logged only (SMTP host not configured)'];
        }

        try {
            $this->sendSmtp($to, $subject, $html, $cfg);
            $this->log($to, $subject, 'sent');

            return ['ok' => true, 'message' => 'Sent via SMTP'];
        } catch (\Throwable $e) {
            $this->log($to, $subject, 'error: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, string|null>  $cfg
     */
    private function sendSmtp(string $to, string $subject, string $html, array $cfg): void
    {
        $host = (string) $cfg['mail_host'];
        $port = (int) ($cfg['mail_port'] ?: 587);
        $user = (string) ($cfg['mail_username'] ?? '');
        $pass = (string) ($cfg['mail_password'] ?? '');
        $enc = strtolower((string) ($cfg['mail_encryption'] ?? 'tls'));
        $from = SafeUrl::stripCrLf((string) ($cfg['mail_from'] ?: ($user ?: 'noreply@localhost')));
        $to = SafeUrl::stripCrLf($to);

        $remote = ($enc === 'ssl' ? 'ssl://' : '').$host;
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote.':'.$port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (! is_resource($fp)) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 15);

        $this->expect($fp, [220]);
        $this->cmd($fp, 'EHLO localhost', [250]);
        if ($enc === 'tls') {
            $this->cmd($fp, 'STARTTLS', [220]);
            if (! stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS failed');
            }
            $this->cmd($fp, 'EHLO localhost', [250]);
        }
        if ($user !== '') {
            $this->cmd($fp, 'AUTH LOGIN', [334]);
            $this->cmd($fp, base64_encode($user), [334]);
            $this->cmd($fp, base64_encode($pass), [235]);
        }

        $this->cmd($fp, 'MAIL FROM:<'.$this->addr($from).'>', [250]);
        $this->cmd($fp, 'RCPT TO:<'.$this->addr($to).'>', [250, 251]);
        $this->cmd($fp, 'DATA', [354]);

        $headers = [
            'Date: '.date('r'),
            'From: '.$from,
            'To: '.$to,
            'Subject: '.$this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $body = implode("\r\n", $headers)."\r\n\r\n".$html;
        $body = preg_replace("/\r\n\./", "\r\n..", $body) ?: $body;
        fwrite($fp, $body."\r\n.\r\n");
        $this->expect($fp, [250]);
        $this->cmd($fp, 'QUIT', [221]);
        fclose($fp);
    }

    /** @param resource $fp */
    private function cmd($fp, string $line, array $okCodes): void
    {
        fwrite($fp, $line."\r\n");
        $this->expect($fp, $okCodes);
    }

    /** @param resource $fp @param list<int> $okCodes */
    private function expect($fp, array $okCodes): void
    {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (! in_array($code, $okCodes, true)) {
            throw new \RuntimeException('SMTP unexpected reply: '.trim($response));
        }
    }

    private function addr(string $email): string
    {
        if (preg_match('/<([^>]+)>/', $email, $m)) {
            return $m[1];
        }

        return trim($email);
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }

    private function log(string $to, string $subject, string $status = ''): void
    {
        $logDir = $this->basePath.'/storage';
        if (! is_dir($logDir)) {
            mkdir($logDir, 0770, true);
        }
        file_put_contents(
            $logDir.'/mail.log',
            sprintf("[%s] TO=%s SUBJECT=%s STATUS=%s\n", gmdate('c'), $to, $subject, $status),
            FILE_APPEND
        );
    }

    public function welcomeHtml(string $name, string $portalUrl, string $setPasswordUrl, string $licenseKey, bool $isNewAccount): string
    {
        $name = htmlspecialchars($name);
        $portalUrl = htmlspecialchars($portalUrl);
        $setPasswordUrl = htmlspecialchars($setPasswordUrl);
        $licenseKey = htmlspecialchars($licenseKey);
        $title = $isNewAccount ? 'Bem-vindo ao portal' : 'Nova compra liberada';
        $cta = $isNewAccount ? 'Criar minha senha' : 'Acessar o portal';
        $ctaUrl = $isNewAccount ? $setPasswordUrl : $portalUrl;
        $extra = $isNewAccount
            ? '<p>Defina sua senha para acessar documentação, produtos e sua chave de licença.</p>'
            : '<p>Sua conta já existe. Use o login do portal para ver a nova licença.</p>';

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:system-ui,sans-serif;background:#f8fafc;padding:32px;color:#0f172a">
  <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px">
    <h1 style="margin:0 0 12px;font-size:22px">{$title}</h1>
    <p>Olá {$name},</p>
    {$extra}
    <p>Sua chave de licença:</p>
    <p style="font-family:ui-monospace,monospace;background:#f1f5f9;padding:12px;border-radius:8px;word-break:break-all">{$licenseKey}</p>
    <p><a href="{$ctaUrl}" style="display:inline-block;background:#0f172a;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px">{$cta}</a></p>
    <p style="font-size:13px;color:#64748b;margin-top:20px">Portal: <a href="{$portalUrl}">{$portalUrl}</a></p>
  </div>
</body></html>
HTML;
    }

    public function refundHtml(string $name): string
    {
        $name = htmlspecialchars($name);

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:system-ui,sans-serif;background:#f8fafc;padding:32px;color:#0f172a">
  <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px">
    <h1 style="margin:0 0 12px;font-size:22px">Reembolso processado</h1>
    <p>Olá {$name}, sua licença foi revogada devido a um reembolso.</p>
  </div>
</body></html>
HTML;
    }

    public function refundRequestHtml(string $name): string
    {
        $name = htmlspecialchars($name);

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:system-ui,sans-serif;background:#f8fafc;padding:32px;color:#0f172a">
  <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px">
    <h1 style="margin:0 0 12px;font-size:22px">Solicitação de reembolso recebida</h1>
    <p>Olá {$name}, recebemos sua solicitação de reembolso.</p>
    <p>Seu acesso ao produto foi revogado. O estorno no cartão está em processamento e pode levar até <strong>30 dias</strong> para aparecer na fatura.</p>
  </div>
</body></html>
HTML;
    }
}
