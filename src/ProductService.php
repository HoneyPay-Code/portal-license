<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;
use RuntimeException;

final class ProductService
{
    public const KIND_GATEWAY = 'gateway';
    public const KIND_PLUGIN = 'plugin';
    public const DEFAULT_GATEWAY_SLUG = 'honeypay-gateway';

    public function __construct(
        private PDO $pdo,
        private string $basePath = '',
    ) {}

    public function seedDefaults(): void
    {
        $existing = $this->findBySlug(self::DEFAULT_GATEWAY_SLUG);
        if ($existing) {
            if (empty($existing['kind'])) {
                $stmt = $this->pdo->prepare('UPDATE products SET kind = :k, updated_at = :u WHERE id = :id');
                $stmt->execute(['k' => self::KIND_GATEWAY, 'u' => gmdate('c'), 'id' => (int) $existing['id']]);
            }
            $this->ensureWebhookCredentials((int) $existing['id']);

            return;
        }

        $this->create([
            'name' => 'HoneyPay - Gateway',
            'slug' => self::DEFAULT_GATEWAY_SLUG,
            'kind' => self::KIND_GATEWAY,
            'description' => 'Gateway de pagamentos Honey Pay. Após a compra, você libera licença, documentação e o download/instalação do sistema.',
            'price' => null,
            'currency' => 'BRL',
            'checkout_url' => null,
            'is_published' => true,
            'sort_order' => 1,
            'type' => 'gateway',
        ]);
    }

    public function ensureWebhookCredentials(int $productId): void
    {
        $product = $this->findById($productId);
        if (! $product) {
            return;
        }
        if (! empty($product['webhook_token']) && ! empty($product['webhook_secret'])) {
            return;
        }
        $this->rotateWebhookCredentials($productId);
    }

    /**
     * @return array{ok:bool,message:string,token?:string,secret?:string,url?:string}
     */
    public function rotateWebhookCredentials(int $productId, string $appUrl = ''): array
    {
        $product = $this->findById($productId);
        if (! $product) {
            return ['ok' => false, 'message' => 'Produto não encontrado.'];
        }

        $token = bin2hex(random_bytes(16));
        while ($this->findByWebhookToken($token)) {
            $token = bin2hex(random_bytes(16));
        }
        $secret = bin2hex(random_bytes(24));

        $stmt = $this->pdo->prepare(
            'UPDATE products SET webhook_token = :t, webhook_secret = :s, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            't' => $token,
            's' => $secret,
            'u' => gmdate('c'),
            'id' => $productId,
        ]);

        return [
            'ok' => true,
            'message' => 'Credenciais de webhook geradas. Atualize o checkout externo.',
            'token' => $token,
            'secret' => $secret,
            'url' => $appUrl !== '' ? rtrim($appUrl, '/').'/webhooks/checkout/'.$token : '',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findByWebhookToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || ! preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE webhook_token = :t LIMIT 1');
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function webhookUrl(array $product, string $appUrl): string
    {
        $token = (string) ($product['webhook_token'] ?? '');
        if ($token === '') {
            return '';
        }

        return rtrim($appUrl, '/').'/webhooks/checkout/'.$token;
    }

    public function ensureAllWebhookCredentials(): int
    {
        $count = 0;
        foreach ($this->listAll() as $product) {
            $before = (string) ($product['webhook_token'] ?? '');
            $this->ensureWebhookCredentials((int) $product['id']);
            $after = $this->findById((int) $product['id']);
            if ($before === '' && ! empty($after['webhook_token'])) {
                $count++;
            }
        }

        return $count;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM products WHERE slug = :s LIMIT 1');
        $stmt->execute(['s' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

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

            return $this->findById((int) $existing['id']) ?? $existing;
        }

        $slug = $slugBase;
        $i = 1;
        while ($this->slugExists($slug)) {
            $slug = $slugBase.'-'.$i++;
        }

        $kind = self::KIND_PLUGIN;
        if ($type && str_contains(strtolower($type), 'gateway')) {
            $kind = self::KIND_GATEWAY;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO products (external_product_id, external_offer_id, slug, name, type, kind, checkout_url, is_published, sort_order, created_at, updated_at)
             VALUES (:epid, :eoid, :slug, :name, :type, :kind, :url, 1, 100, :c, :u)'
        );
        $stmt->execute([
            'epid' => $externalProductId,
            'eoid' => $externalOfferId,
            'slug' => $slug,
            'name' => $name,
            'type' => $type,
            'kind' => $kind,
            'url' => $checkoutUrl,
            'c' => $now,
            'u' => $now,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId())
            ?? throw new RuntimeException('product create failed');
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->pdo->query(
            'SELECT * FROM products ORDER BY sort_order ASC, id ASC'
        )->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listPublished(): array
    {
        return $this->pdo->query(
            'SELECT * FROM products WHERE is_published = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll() ?: [];
    }

    /**
     * @param array{
     *   name:string,
     *   slug?:string,
     *   kind?:string,
     *   description?:?string,
     *   price?:?float,
     *   currency?:?string,
     *   checkout_url?:?string,
     *   is_published?:bool,
     *   sort_order?:int,
     *   type?:?string,
     *   external_product_id?:?string,
     *   external_offer_id?:?string
     * } $data
     * @return array{ok:bool,message:string,product?:array<string,mixed>}
     */
    public function create(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Informe o título do produto.'];
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($name);
        } else {
            $slug = $this->slugify($slug);
        }
        if ($this->slugExists($slug)) {
            return ['ok' => false, 'message' => 'Já existe um produto com este slug.'];
        }

        $kind = (string) ($data['kind'] ?? self::KIND_PLUGIN);
        if (! in_array($kind, [self::KIND_GATEWAY, self::KIND_PLUGIN], true)) {
            $kind = self::KIND_PLUGIN;
        }

        $checkout = $this->normalizeCheckoutUrl($data['checkout_url'] ?? null);
        if (($data['checkout_url'] ?? null) && $checkout === null) {
            return ['ok' => false, 'message' => 'Link de checkout inválido (use http/https).'];
        }

        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (
                external_product_id, external_offer_id, slug, name, type, kind, description, price, currency,
                checkout_url, is_published, sort_order, created_at, updated_at
             ) VALUES (
                :epid, :eoid, :slug, :name, :type, :kind, :description, :price, :currency,
                :url, :pub, :ord, :c, :u
             )'
        );
        $stmt->execute([
            'epid' => $data['external_product_id'] ?? null,
            'eoid' => $data['external_offer_id'] ?? null,
            'slug' => $slug,
            'name' => $name,
            'type' => $data['type'] ?? $kind,
            'kind' => $kind,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'price' => isset($data['price']) && $data['price'] !== null && $data['price'] !== ''
                ? (float) $data['price'] : null,
            'currency' => trim((string) ($data['currency'] ?? 'BRL')) ?: 'BRL',
            'url' => $checkout,
            'pub' => ! empty($data['is_published']) ? 1 : 0,
            'ord' => (int) ($data['sort_order'] ?? 100),
            'c' => $now,
            'u' => $now,
        ]);

        $product = $this->findById((int) $this->pdo->lastInsertId());
        if ($product) {
            $this->ensureWebhookCredentials((int) $product['id']);
            $product = $this->findById((int) $product['id']) ?? $product;
        }

        return ['ok' => true, 'message' => 'Produto criado.', 'product' => $product ?? []];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok:bool,message:string,product?:array<string,mixed>}
     */
    public function update(int $id, array $data): array
    {
        $product = $this->findById($id);
        if (! $product) {
            return ['ok' => false, 'message' => 'Produto não encontrado.'];
        }

        $name = trim((string) ($data['name'] ?? $product['name']));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Informe o título do produto.'];
        }

        $slug = trim((string) ($data['slug'] ?? $product['slug']));
        $slug = $this->slugify($slug);
        $other = $this->findBySlug($slug);
        if ($other && (int) $other['id'] !== $id) {
            return ['ok' => false, 'message' => 'Já existe um produto com este slug.'];
        }

        // Protect default gateway slug from being renamed away accidentally if it is the default product
        if ((string) $product['slug'] === self::DEFAULT_GATEWAY_SLUG && $slug !== self::DEFAULT_GATEWAY_SLUG) {
            return ['ok' => false, 'message' => 'O slug do gateway padrão não pode ser alterado.'];
        }

        $kind = (string) ($data['kind'] ?? $product['kind'] ?? self::KIND_PLUGIN);
        if (! in_array($kind, [self::KIND_GATEWAY, self::KIND_PLUGIN], true)) {
            $kind = self::KIND_PLUGIN;
        }

        $checkoutRaw = array_key_exists('checkout_url', $data) ? $data['checkout_url'] : $product['checkout_url'];
        $checkout = $this->normalizeCheckoutUrl($checkoutRaw);
        if ($checkoutRaw && trim((string) $checkoutRaw) !== '' && $checkout === null) {
            return ['ok' => false, 'message' => 'Link de checkout inválido (use http/https).'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE products SET
                name = :name, slug = :slug, kind = :kind, type = :type, description = :description,
                price = :price, currency = :currency, checkout_url = :url,
                is_published = :pub, sort_order = :ord,
                external_product_id = :epid, external_offer_id = :eoid,
                updated_at = :u
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'kind' => $kind,
            'type' => $data['type'] ?? $product['type'] ?? $kind,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'price' => isset($data['price']) && $data['price'] !== null && $data['price'] !== ''
                ? (float) $data['price'] : null,
            'currency' => trim((string) ($data['currency'] ?? 'BRL')) ?: 'BRL',
            'url' => $checkout,
            'pub' => ! empty($data['is_published']) ? 1 : 0,
            'ord' => (int) ($data['sort_order'] ?? $product['sort_order'] ?? 100),
            'epid' => array_key_exists('external_product_id', $data)
                ? (trim((string) $data['external_product_id']) ?: null)
                : ($product['external_product_id'] ?? null),
            'eoid' => array_key_exists('external_offer_id', $data)
                ? (trim((string) $data['external_offer_id']) ?: null)
                : ($product['external_offer_id'] ?? null),
            'u' => gmdate('c'),
            'id' => $id,
        ]);

        return ['ok' => true, 'message' => 'Produto atualizado.', 'product' => $this->findById($id) ?? []];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function delete(int $id): array
    {
        $product = $this->findById($id);
        if (! $product) {
            return ['ok' => false, 'message' => 'Produto não encontrado.'];
        }
        if ((string) $product['slug'] === self::DEFAULT_GATEWAY_SLUG) {
            return ['ok' => false, 'message' => 'O produto padrão HoneyPay - Gateway não pode ser excluído.'];
        }

        $this->deleteStoredFile($product['image_path'] ?? null);
        $this->deleteStoredFile($product['plugin_zip_path'] ?? null);

        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return ['ok' => true, 'message' => 'Produto excluído.'];
    }

    /**
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return array{ok:bool,message:string,product?:array<string,mixed>}
     */
    public function uploadImage(int $id, array $file): array
    {
        $product = $this->findById($id);
        if (! $product) {
            return ['ok' => false, 'message' => 'Produto não encontrado.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Falha no upload da imagem.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $name = (string) ($file['name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            return ['ok' => false, 'message' => 'Arquivo de imagem inválido.'];
        }
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'Imagem deve ter no máximo 5 MB.'];
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg' => true, 'jpeg' => true, 'png' => true, 'webp' => true];
        if (! isset($allowed[$ext])) {
            return ['ok' => false, 'message' => 'Use JPG, PNG ou WEBP.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $okMimes = ['image/jpeg' => true, 'image/png' => true, 'image/webp' => true];
        if (! isset($okMimes[$mime])) {
            return ['ok' => false, 'message' => 'Tipo de imagem não permitido.'];
        }

        $dir = $this->imagesDir();
        $stored = 'p'.$id.'-'.bin2hex(random_bytes(6)).'.'.$ext;
        $dest = $dir.DIRECTORY_SEPARATOR.$stored;
        if (! move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'message' => 'Não foi possível salvar a imagem.'];
        }

        $this->deleteStoredFile($product['image_path'] ?? null);
        $relative = 'storage/products/images/'.$stored;
        $stmt = $this->pdo->prepare('UPDATE products SET image_path = :p, updated_at = :u WHERE id = :id');
        $stmt->execute(['p' => $relative, 'u' => gmdate('c'), 'id' => $id]);

        return ['ok' => true, 'message' => 'Imagem enviada.', 'product' => $this->findById($id) ?? []];
    }

    /**
     * @param array{name:string,tmp_name:string,size:int,error:int} $file
     * @return array{ok:bool,message:string,product?:array<string,mixed>}
     */
    public function uploadPluginZip(int $id, array $file): array
    {
        $product = $this->findById($id);
        if (! $product) {
            return ['ok' => false, 'message' => 'Produto não encontrado.'];
        }
        if (($product['kind'] ?? '') === self::KIND_GATEWAY) {
            return ['ok' => false, 'message' => 'O gateway usa Releases (/admin/releases), não ZIP de plugin.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Falha no upload do ZIP.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $name = (string) ($file['name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp)) {
            return ['ok' => false, 'message' => 'Arquivo ZIP inválido.'];
        }
        if ($size <= 0 || $size > 512 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'ZIP inválido ou maior que 512 MB.'];
        }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            return ['ok' => false, 'message' => 'Envie um arquivo .zip.'];
        }

        $sha = hash_file('sha256', $tmp);
        if ($sha === false) {
            return ['ok' => false, 'message' => 'Não foi possível calcular o checksum.'];
        }

        $dir = $this->zipsDir();
        $stored = 'p'.$id.'-'.bin2hex(random_bytes(8)).'.zip';
        $dest = $dir.DIRECTORY_SEPARATOR.$stored;
        if (! move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'message' => 'Não foi possível salvar o ZIP.'];
        }

        $this->deleteStoredFile($product['plugin_zip_path'] ?? null);
        $relative = 'storage/products/zips/'.$stored;
        $safeName = preg_replace('/[^\w.\-]+/', '-', $name) ?: 'plugin.zip';
        $stmt = $this->pdo->prepare(
            'UPDATE products SET plugin_zip_path = :p, plugin_zip_filename = :f, plugin_zip_sha256 = :s,
             plugin_zip_size = :sz, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            'p' => $relative,
            'f' => $safeName,
            's' => $sha,
            'sz' => $size,
            'u' => gmdate('c'),
            'id' => $id,
        ]);

        return ['ok' => true, 'message' => 'ZIP do plugin enviado.', 'product' => $this->findById($id) ?? []];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function clearPluginZip(int $id): array
    {
        $product = $this->findById($id);
        if (! $product) {
            return ['ok' => false, 'message' => 'Produto não encontrado.'];
        }
        $this->deleteStoredFile($product['plugin_zip_path'] ?? null);
        $stmt = $this->pdo->prepare(
            'UPDATE products SET plugin_zip_path = NULL, plugin_zip_filename = NULL, plugin_zip_sha256 = NULL,
             plugin_zip_size = NULL, updated_at = :u WHERE id = :id'
        );
        $stmt->execute(['u' => gmdate('c'), 'id' => $id]);

        return ['ok' => true, 'message' => 'ZIP removido.'];
    }

    public function customerHasProduct(int $customerId, int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM entitlements WHERE customer_id = :c AND product_id = :p AND status = :s'
        );
        $stmt->execute(['c' => $customerId, 'p' => $productId, 's' => 'active']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function absolutePath(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }
        $relative = str_replace(['\\', '..'], ['/', ''], $relative);
        if (! str_starts_with($relative, 'storage/products/')) {
            return null;
        }
        $full = $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (! is_file($full)) {
            return null;
        }

        return $full;
    }

    public function streamPluginZip(array $product): void
    {
        $path = $this->absolutePath(isset($product['plugin_zip_path']) ? (string) $product['plugin_zip_path'] : null);
        if ($path === null) {
            http_response_code(404);
            echo 'Arquivo não encontrado';
            exit;
        }
        $filename = (string) ($product['plugin_zip_filename'] ?? 'plugin.zip');
        header('Content-Type: application/zip');
        header('Content-Length: '.(string) filesize($path));
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public function streamImage(array $product): void
    {
        $path = $this->absolutePath(isset($product['image_path']) ? (string) $product['image_path'] : null);
        if ($path === null) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
        header('Content-Type: '.$mime);
        header('Content-Length: '.(string) filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    /** @return list<array<string, mixed>> */
    public function entitlementsForCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, p.name AS product_name, p.slug, p.checkout_url, p.type, p.kind, p.description,
                    p.price, p.currency, p.image_path, p.plugin_zip_path, p.plugin_zip_filename, p.plugin_zip_size,
                    o.id AS order_row_id, o.external_order_id, o.status AS order_status,
                    o.amount AS order_amount, o.currency AS order_currency, o.payment_method AS order_payment_method,
                    o.created_at AS order_created_at
             FROM entitlements e
             JOIN products p ON p.id = e.product_id
             LEFT JOIN orders o ON o.id = e.order_id
             WHERE e.customer_id = :cid AND e.status = :status
             ORDER BY p.sort_order ASC, e.id DESC'
        );
        $stmt->execute(['cid' => $customerId, 'status' => 'active']);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $orderId = isset($row['order_row_id']) ? (int) $row['order_row_id'] : 0;
            $row['refund_eligible'] = $orderId > 0
                && ($row['order_status'] ?? '') === 'completed'
                && RefundService::isCardPayment(
                    isset($row['order_payment_method']) ? (string) $row['order_payment_method'] : null
                )
                && RefundService::isWithinRefundWindow((string) ($row['order_created_at'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    /**
     * Published products the customer does not own yet.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogForCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.* FROM products p
             WHERE p.is_published = 1
               AND p.id NOT IN (
                   SELECT product_id FROM entitlements
                   WHERE customer_id = :cid AND status = :s
               )
             ORDER BY p.sort_order ASC, p.id ASC'
        );
        $stmt->execute(['cid' => $customerId, 's' => 'active']);

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

    public static function formatPrice(?float $price, ?string $currency = 'BRL'): string
    {
        if ($price === null) {
            return 'Sob consulta';
        }
        $currency = $currency ?: 'BRL';
        if (strtoupper($currency) === 'BRL') {
            return 'R$ '.number_format($price, 2, ',', '.');
        }

        return $currency.' '.number_format($price, 2, '.', ',');
    }

    private function imagesDir(): string
    {
        $dir = $this->basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'products'.DIRECTORY_SEPARATOR.'images';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function zipsDir(): string
    {
        $dir = $this->basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'products'.DIRECTORY_SEPARATOR.'zips';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function deleteStoredFile(mixed $relative): void
    {
        $path = $this->absolutePath(is_string($relative) ? $relative : null);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    private function normalizeCheckoutUrl(mixed $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        return SafeUrl::forHref($url, true);
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
        $value = strtr($value, $map);
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
