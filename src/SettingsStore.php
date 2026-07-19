<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class SettingsStore
{
    public function __construct(private PDO $pdo) {}

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute(['k' => $key]);
        $val = $stmt->fetchColumn();
        if ($val === false || $val === null) {
            return $default;
        }

        return (string) $val;
    }

    public function set(string $key, ?string $value): void
    {
        $now = gmdate('c');
        if ($this->hasKey($key)) {
            $upd = $this->pdo->prepare('UPDATE settings SET value = :v, updated_at = :u WHERE setting_key = :k');
            $upd->execute(['v' => $value, 'u' => $now, 'k' => $key]);

            return;
        }
        $ins = $this->pdo->prepare('INSERT INTO settings (setting_key, value, updated_at) VALUES (:k, :v, :u)');
        $ins->execute(['k' => $key, 'v' => $value, 'u' => $now]);
    }

    private function hasKey(string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM settings WHERE setting_key = :k');
        $stmt->execute(['k' => $key]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return array<string, string|null>
     */
    public function mailConfig(?array $envFallback = null): array
    {
        $envFallback = $envFallback ?? [];

        return [
            'mail_from' => $this->get('mail_from', $envFallback['mail_from'] ?? null),
            'mail_host' => $this->get('mail_host', $envFallback['mail_host'] ?? null),
            'mail_port' => $this->get('mail_port', $envFallback['mail_port'] ?? '587'),
            'mail_username' => $this->get('mail_username', $envFallback['mail_username'] ?? null),
            'mail_password' => $this->get('mail_password', $envFallback['mail_password'] ?? null),
            'mail_encryption' => $this->get('mail_encryption', $envFallback['mail_encryption'] ?? 'tls'),
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     */
    public function saveMailConfig(array $data, bool $updatePassword): void
    {
        foreach (['mail_from', 'mail_host', 'mail_port', 'mail_username', 'mail_encryption'] as $key) {
            if (array_key_exists($key, $data)) {
                $val = $data[$key];
                $this->set($key, ($val !== null && $val !== '') ? $val : null);
            }
        }
        if ($updatePassword && array_key_exists('mail_password', $data)) {
            $val = $data['mail_password'];
            $this->set('mail_password', ($val !== null && $val !== '') ? $val : null);
        }
    }
}
