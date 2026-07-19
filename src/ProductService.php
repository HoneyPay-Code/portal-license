<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class ProductService
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array<string, mixed>
     */
    public function upsertFromWebhook(?string $externalProductId, ?string $externalOfferId, string $name, ?string $type, ?string $checkoutUrl): array
    {
        $slugBase = $this->slugify($name.($externalOfferId ? '-'.$externalOfferId : ''));
        $existing = null;
        if ($externalProductId) {
            $stmt = $this->pdo->prepare('SELECT * FROM products WHERE external_product_id = :id LIMIT 1');
            $stmt->execute(['id' => $externalProductId]);
            $existing = $stmt->fetch() ?: null;
        }
        if (! $existing && $externalOfferId) {
            $stmt = $this->pdo->prepare('SELECT * FROM products WHERE external_offer_id = :id LIMIT 1');
            $stmt->execute(['id' => $externalOfferId]);
            $existing = $stmt->fetch() ?: null;
        }

        $now = gmdate('c');
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE products SET name = :name, type = COALESCE(:type, type), checkout_url = COALESCE(:url, checkout_url),
                 external_offer_id = COALESCE(:offer, external_offer_id), updated_at = :u WHERE id = :id'
            );
            $stmt->execute([
                'name' => $name,
                'type' => $type,
                'url' => $checkoutUrl,
                'offer' => $externalOfferId,
                'u' => $now,
                'id' => (int) $existing['id'],
            ]);
            $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id');
            $stmt->execute(['id' => (int) $existing['id']]);

            return $stmt->fetch() ?: $existing;
        }

        $slug = $slugBase;
        $i = 1;
        while ($this->slugExists($slug)) {
            $slug = $slugBase.'-'.$i++;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO products (external_product_id, external_offer_id, slug, name, type, checkout_url, created_at, updated_at)
             VALUES (:epid, :eoid, :slug, :name, :type, :url, :c, :u)'
        );
        $stmt->execute([
            'epid' => $externalProductId,
            'eoid' => $externalOfferId,
            'slug' => $slug,
            'name' => $name,
            'type' => $type,
            'url' => $checkoutUrl,
            'c' => $now,
            'u' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: throw new \RuntimeException('product create failed');
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function entitlementsForCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, p.name AS product_name, p.slug, p.checkout_url, p.type
             FROM entitlements e
             JOIN products p ON p.id = e.product_id
             WHERE e.customer_id = :cid AND e.status = :status
             ORDER BY e.id DESC'
        );
        $stmt->execute(['cid' => $customerId, 'status' => 'active']);

        return $stmt->fetchAll() ?: [];
    }

    public function grantEntitlement(int $customerId, int $productId, ?int $orderId): void
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare('SELECT id FROM entitlements WHERE customer_id = :c AND product_id = :p LIMIT 1');
        $stmt->execute(['c' => $customerId, 'p' => $productId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $upd = $this->pdo->prepare(
                'UPDATE entitlements SET status = :s, order_id = COALESCE(:oid, order_id), updated_at = :u WHERE id = :id'
            );
            $upd->execute(['s' => 'active', 'oid' => $orderId, 'u' => $now, 'id' => (int) $id]);

            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO entitlements (customer_id, product_id, order_id, status, created_at, updated_at)
             VALUES (:c, :p, :oid, :s, :ca, :u)'
        );
        $ins->execute([
            'c' => $customerId,
            'p' => $productId,
            'oid' => $orderId,
            's' => 'active',
            'ca' => $now,
            'u' => $now,
        ]);
    }

    public function revokeEntitlement(int $customerId, int $productId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE entitlements SET status = :s, updated_at = :u WHERE customer_id = :c AND product_id = :p'
        );
        $stmt->execute(['s' => 'revoked', 'u' => gmdate('c'), 'c' => $customerId, 'p' => $productId]);
    }

    public function customerHasActiveEntitlement(int $customerId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM entitlements WHERE customer_id = :c AND status = :s'
        );
        $stmt->execute(['c' => $customerId, 's' => 'active']);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: 'product';

        return trim($value, '-') ?: 'product';
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :s');
        $stmt->execute(['s' => $slug]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
