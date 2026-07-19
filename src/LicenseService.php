<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;
use RuntimeException;

final class LicenseService
{
    public function __construct(
        private PDO $pdo,
        private string $signingKey,
        private ?string $supportContact = null,
    ) {}

    public static function generateKey(): string
    {
        return 'LIC-'.strtoupper(bin2hex(random_bytes(16)));
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $licenseKey, string $domain, string $installId, ?string $appVersion, ?string $ip): array
    {
        $domain = DomainHelper::normalize($domain);
        $isLocal = DomainHelper::isLocalhost($domain);
        $license = $this->findLicense($licenseKey);
        if (! $license) {
            return $this->signed(false, false, $domain, null, $installId, false, $isLocal);
        }

        if (in_array($license['status'], ['blocked', 'revoked'], true)) {
            return $this->signed(false, true, $domain, $license['expires_at'] ?? null, $installId, false, $isLocal);
        }

        if ($this->isExpired($license['expires_at'] ?? null)) {
            return $this->signed(false, false, $domain, $license['expires_at'] ?? null, $installId, false, $isLocal);
        }

        $existing = $this->findActivationByInstall((int) $license['id'], $installId);
        if ($existing) {
            // Same install may refresh domain only if both are localhost or domain matches.
            $prevLocal = ! empty($existing['is_localhost']);
            if (! $isLocal && ! $prevLocal && DomainHelper::normalize((string) $existing['domain']) !== $domain) {
                return $this->signed(false, false, $domain, $license['expires_at'] ?? null, $installId, true, false);
            }
            $this->touchActivation((int) $existing['id'], $domain, $isLocal, $appVersion, $ip);

            return $this->signed(true, false, $domain, $license['expires_at'] ?? null, $installId, ! $isLocal, $isLocal);
        }

        if ($isLocal) {
            $this->insertActivation((int) $license['id'], $domain, $installId, true, $appVersion, $ip);

            return $this->signed(true, false, $domain, $license['expires_at'] ?? null, $installId, false, true);
        }

        $prod = $this->findProductionActivation((int) $license['id']);
        if ($prod) {
            // Already bound to another install/domain
            return $this->signed(false, false, $domain, $license['expires_at'] ?? null, (string) $prod['install_id'], true, false);
        }

        $this->insertActivation((int) $license['id'], $domain, $installId, false, $appVersion, $ip, true);

        return $this->signed(true, false, $domain, $license['expires_at'] ?? null, $installId, true, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function heartbeat(string $licenseKey, string $domain, string $installId, ?string $appVersion, ?string $ip): array
    {
        $domain = DomainHelper::normalize($domain);
        $isLocal = DomainHelper::isLocalhost($domain);
        $license = $this->findLicense($licenseKey);
        if (! $license) {
            return $this->signed(false, false, $domain, null, $installId, false, $isLocal);
        }

        if (in_array($license['status'], ['blocked', 'revoked'], true)) {
            return $this->signed(false, true, $domain, $license['expires_at'] ?? null, $installId, false, $isLocal);
        }

        if ($this->isExpired($license['expires_at'] ?? null)) {
            return $this->signed(false, false, $domain, $license['expires_at'] ?? null, $installId, false, $isLocal);
        }

        $existing = $this->findActivationByInstall((int) $license['id'], $installId);
        if (! $existing) {
            return $this->signed(false, false, $domain, $license['expires_at'] ?? null, $installId, false, $isLocal);
        }

        $prevLocal = ! empty($existing['is_localhost']);
        if (! $isLocal && ! $prevLocal && DomainHelper::normalize((string) $existing['domain']) !== $domain) {
            return $this->signed(false, false, $domain, $license['expires_at'] ?? null, $installId, true, false);
        }

        $this->touchActivation((int) $existing['id'], $domain, $isLocal || $prevLocal, $appVersion, $ip);

        return $this->signed(true, false, $domain, $license['expires_at'] ?? null, $installId, ! ($isLocal || $prevLocal), $isLocal || $prevLocal);
    }

    /** @return array<string, mixed>|null */
    public function findLicense(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM licenses WHERE license_key = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listLicenses(): array
    {
        return $this->pdo->query(
            'SELECT l.*, c.email AS customer_email, p.name AS product_name,
                (SELECT COUNT(*) FROM activations a WHERE a.license_id = l.id AND a.is_localhost = 0) AS production_activations
             FROM licenses l
             LEFT JOIN customers c ON c.id = l.customer_id
             LEFT JOIN products p ON p.id = l.product_id
             ORDER BY l.id DESC'
        )->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listForCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.*, p.name AS product_name,
                (SELECT domain FROM activations a WHERE a.license_id = l.id AND a.is_localhost = 0 ORDER BY a.id ASC LIMIT 1) AS bound_domain,
                (SELECT install_id FROM activations a WHERE a.license_id = l.id AND a.is_localhost = 0 ORDER BY a.id ASC LIMIT 1) AS bound_install_id
             FROM licenses l
             LEFT JOIN products p ON p.id = l.product_id
             WHERE l.customer_id = :cid
             ORDER BY l.id DESC'
        );
        $stmt->execute(['cid' => $customerId]);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listActivations(int $licenseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM activations WHERE license_id = :id ORDER BY last_seen_at DESC');
        $stmt->execute(['id' => $licenseId]);

        return $stmt->fetchAll() ?: [];
    }

    public function createLicense(
        int $maxActivations = 1,
        ?string $note = null,
        ?string $expiresAt = null,
        ?int $customerId = null,
        ?int $productId = null,
        ?int $orderId = null,
    ): array {
        $now = gmdate('c');
        $key = self::generateKey();
        $stmt = $this->pdo->prepare(
            'INSERT INTO licenses (license_key, status, max_activations, customer_id, product_id, order_id, customer_note, expires_at, created_at, updated_at)
             VALUES (:key, :status, :max_activations, :customer_id, :product_id, :order_id, :note, :expires_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            'key' => $key,
            'status' => 'active',
            'max_activations' => max(1, $maxActivations),
            'customer_id' => $customerId,
            'product_id' => $productId,
            'order_id' => $orderId,
            'note' => $note,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findLicense($key) ?? throw new RuntimeException('Failed to create license');
    }

    public function setStatus(int $id, string $status): void
    {
        if (! in_array($status, ['active', 'blocked', 'revoked'], true)) {
            throw new RuntimeException('Invalid status');
        }
        $stmt = $this->pdo->prepare('UPDATE licenses SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute(['status' => $status, 'updated_at' => gmdate('c'), 'id' => $id]);
    }

    public function revokeByOrder(int $orderId): void
    {
        $stmt = $this->pdo->prepare('UPDATE licenses SET status = :status, updated_at = :u WHERE order_id = :oid');
        $stmt->execute(['status' => 'revoked', 'u' => gmdate('c'), 'oid' => $orderId]);
    }

    public function clearLocalhostActivations(int $licenseId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM activations WHERE license_id = :id AND is_localhost = 1');
        $stmt->execute(['id' => $licenseId]);
    }

    /** @return array<string, mixed>|null */
    private function findActivationByInstall(int $licenseId, string $installId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM activations WHERE license_id = :license_id AND install_id = :install_id LIMIT 1'
        );
        $stmt->execute(['license_id' => $licenseId, 'install_id' => $installId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findProductionActivation(int $licenseId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM activations WHERE license_id = :id AND is_localhost = 0 ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['id' => $licenseId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function insertActivation(
        int $licenseId,
        string $domain,
        string $installId,
        bool $isLocal,
        ?string $appVersion,
        ?string $ip,
        bool $bound = false,
    ): void {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO activations (license_id, domain, install_id, is_localhost, app_version, ip, bound_at, last_seen_at, created_at)
             VALUES (:license_id, :domain, :install_id, :is_localhost, :app_version, :ip, :bound_at, :last_seen_at, :created_at)'
        );
        $stmt->execute([
            'license_id' => $licenseId,
            'domain' => $domain,
            'install_id' => $installId,
            'is_localhost' => $isLocal ? 1 : 0,
            'app_version' => $appVersion,
            'ip' => $ip,
            'bound_at' => (! $isLocal && $bound) ? $now : null,
            'last_seen_at' => $now,
            'created_at' => $now,
        ]);
    }

    private function touchActivation(int $id, string $domain, bool $isLocal, ?string $appVersion, ?string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE activations SET domain = :domain, is_localhost = :is_localhost, app_version = :app_version, ip = :ip, last_seen_at = :last_seen_at WHERE id = :id'
        );
        $stmt->execute([
            'domain' => $domain,
            'is_localhost' => $isLocal ? 1 : 0,
            'app_version' => $appVersion,
            'ip' => $ip,
            'last_seen_at' => gmdate('c'),
            'id' => $id,
        ]);
    }

    private function isExpired(?string $expiresAt): bool
    {
        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }
        $ts = strtotime($expiresAt);

        return $ts !== false && $ts < time();
    }

    /**
     * @return array<string, mixed>
     */
    private function signed(
        bool $valid,
        bool $blocked,
        ?string $domain,
        ?string $expiresAt,
        ?string $installId,
        bool $bound,
        bool $isLocalhost,
    ): array {
        return Signer::signedResponse([
            'valid' => $valid,
            'blocked' => $blocked,
            'domain' => $domain,
            'expires_at' => $expiresAt,
            'support_contact' => $this->supportContact,
            'install_id' => $installId,
            'bound' => $bound,
            'is_localhost' => $isLocalhost,
        ], $this->signingKey);
    }
}
