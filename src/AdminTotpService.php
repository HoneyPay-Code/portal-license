<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class AdminTotpService
{
    public function __construct(
        private PDO $pdo,
        private SecretBox $box,
        private string $issuer,
    ) {}

    /** @return array<string, mixed>|null */
    public function findAdmin(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function isEnabled(int $adminId): bool
    {
        $admin = $this->findAdmin($adminId);

        return $admin !== null && ! empty($admin['totp_enabled']);
    }

    /**
     * @return array{secret:string,otpauth:string,qr_svg:string}
     */
    public function beginSetup(int $adminId, string $email): array
    {
        $secret = Totp::generateSecret();
        $enc = $this->box->encrypt($secret);
        $stmt = $this->pdo->prepare(
            'UPDATE admins SET totp_secret = :s, totp_enabled = 0, totp_confirmed_at = NULL WHERE id = :id'
        );
        $stmt->execute(['s' => $enc, 'id' => $adminId]);
        $otpauth = Totp::otpauthUri($secret, $email, $this->issuer);

        return [
            'secret' => $secret,
            'otpauth' => $otpauth,
            'qr_svg' => QrSvg::svg($otpauth),
        ];
    }

    public function confirmSetup(int $adminId, string $code): bool
    {
        $secret = $this->plainSecret($adminId);
        if ($secret === null || ! Totp::verify($secret, $code)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE admins SET totp_enabled = 1, totp_confirmed_at = :c WHERE id = :id'
        );
        $stmt->execute(['c' => gmdate('c'), 'id' => $adminId]);

        return true;
    }

    public function disable(int $adminId, string $password, string $code): bool
    {
        $admin = $this->findAdmin($adminId);
        if (! $admin || ! password_verify($password, (string) $admin['password_hash'])) {
            return false;
        }
        if (! empty($admin['totp_enabled'])) {
            $secret = $this->plainSecret($adminId);
            if ($secret === null || ! Totp::verify($secret, $code)) {
                return false;
            }
        }
        $stmt = $this->pdo->prepare(
            'UPDATE admins SET totp_secret = NULL, totp_enabled = 0, totp_confirmed_at = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $adminId]);

        return true;
    }

    public function verifyLogin(int $adminId, string $code): bool
    {
        if (! $this->isEnabled($adminId)) {
            return false;
        }
        $secret = $this->plainSecret($adminId);

        return $secret !== null && Totp::verify($secret, $code);
    }

    private function plainSecret(int $adminId): ?string
    {
        $admin = $this->findAdmin($adminId);
        if (! $admin || empty($admin['totp_secret'])) {
            return null;
        }
        try {
            return $this->box->decrypt((string) $admin['totp_secret']);
        } catch (\Throwable) {
            return null;
        }
    }
}
