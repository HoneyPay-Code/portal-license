<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class WebhookProcessor
{
    public function __construct(
        private PDO $pdo,
        private CustomerService $customers,
        private ProductService $products,
        private LicenseService $licenses,
        private Mailer $mailer,
        private OutboundWebhookService $outbound,
        private string $appUrl,
        private bool $acceptTest,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok:bool,message:string,license_key?:string}
     */
    public function handle(array $body, ?int $boundProductId = null): array
    {
        $event = (string) ($body['event'] ?? '');
        $payload = $body['payload'] ?? null;
        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Invalid payload'];
        }

        if (! empty($payload['test']) && ! $this->acceptTest) {
            return ['ok' => true, 'message' => 'Test webhook ignored'];
        }

        $orderId = (string) (($payload['order']['id'] ?? '') ?: '');
        $eventId = $this->logEvent($event, $orderId !== '' ? $orderId : null, $body);

        if ($event === 'pedido_pago') {
            $result = $this->handlePaid($payload, $boundProductId);
            $this->markProcessed($eventId, $result['message']);

            return $result;
        }

        if ($event === 'reembolso') {
            $result = $this->handleRefund($payload);
            $this->markProcessed($eventId, $result['message']);

            return $result;
        }

        $this->markProcessed($eventId, 'ignored event');

        return ['ok' => true, 'message' => 'Event ignored'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,message:string,license_key?:string}
     */
    private function handlePaid(array $payload, ?int $boundProductId = null): array
    {
        $externalOrderId = (string) ($payload['order']['id'] ?? '');
        if ($externalOrderId === '') {
            return ['ok' => false, 'message' => 'Missing order.id'];
        }

        $customerData = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $email = strtolower(trim((string) ($customerData['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Invalid customer email'];
        }

        $customer = $this->customers->upsert(
            $email,
            (string) ($customerData['name'] ?? $email),
            isset($customerData['phone']) ? (string) $customerData['phone'] : null
        );
        $customerId = (int) $customer['id'];

        $product = $this->resolvePaidProduct($payload, $boundProductId);
        if (! $product) {
            return ['ok' => false, 'message' => 'Bound product not found'];
        }

        $existing = $this->findOrder($externalOrderId);
        $isNewOrder = ! $existing;

        if ($existing && ($existing['status'] ?? '') === 'completed') {
            if ($this->products->purchaseFulfillmentComplete($customerId, $product)) {
                $lic = $this->licenses->findLicenseForOrder((int) $existing['id']);

                return [
                    'ok' => true,
                    'message' => 'Already processed',
                    'license_key' => $lic['license_key'] ?? null,
                ];
            }

            $orderRowId = (int) $existing['id'];

            return $this->fulfillPurchase(
                $customer,
                $product,
                $orderRowId,
                $externalOrderId,
                $payload,
                false
            );
        }

        $orderRowId = $this->upsertOrder(
            $externalOrderId,
            $customerId,
            (int) $product['id'],
            $payload,
            'completed',
            $existing
        );

        return $this->fulfillPurchase(
            $customer,
            $product,
            $orderRowId,
            $externalOrderId,
            $payload,
            $isNewOrder
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function resolvePaidProduct(array $payload, ?int $boundProductId): ?array
    {
        $productData = is_array($payload['product'] ?? null) ? $payload['product'] : [];
        $offerData = is_array($payload['offer'] ?? null) ? $payload['offer'] : [];

        if ($boundProductId !== null) {
            $product = $this->products->findById($boundProductId);
            if (! $product) {
                return null;
            }
            $extPid = isset($productData['id']) ? (string) $productData['id'] : null;
            $extOid = isset($offerData['public_id'])
                ? (string) $offerData['public_id']
                : (isset($offerData['id']) ? (string) $offerData['id'] : null);
            if (($extPid || $extOid) && (empty($product['external_product_id']) || empty($product['external_offer_id']))) {
                $this->products->update((int) $product['id'], [
                    'name' => (string) $product['name'],
                    'slug' => (string) $product['slug'],
                    'kind' => (string) ($product['kind'] ?? 'plugin'),
                    'description' => $product['description'] ?? null,
                    'price' => $product['price'] ?? null,
                    'currency' => $product['currency'] ?? 'BRL',
                    'checkout_url' => $product['checkout_url'] ?? null,
                    'is_published' => ! empty($product['is_published']),
                    'sort_order' => (int) ($product['sort_order'] ?? 100),
                    'external_product_id' => $extPid ?: ($product['external_product_id'] ?? null),
                    'external_offer_id' => $extOid ?: ($product['external_offer_id'] ?? null),
                ]);
                $product = $this->products->findById($boundProductId) ?? $product;
            }

            return $product;
        }

        $product = $this->products->upsertFromWebhook(
            isset($productData['id']) ? (string) $productData['id'] : null,
            isset($offerData['public_id']) ? (string) $offerData['public_id'] : (isset($offerData['id']) ? (string) $offerData['id'] : null),
            (string) ($productData['name'] ?? 'Produto'),
            isset($productData['type']) ? (string) $productData['type'] : null,
            isset($productData['checkout_url']) ? (string) $productData['checkout_url'] : (isset($payload['checkoutUrl']) ? (string) $payload['checkoutUrl'] : null),
        );
        $this->products->ensureWebhookCredentials((int) $product['id']);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $customer
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,message:string,license_key?:string}
     */
    private function fulfillPurchase(
        array $customer,
        array $product,
        int $orderRowId,
        string $externalOrderId,
        array $payload,
        bool $sendNotifications,
    ): array {
        $customerId = (int) $customer['id'];
        $grantIds = $this->products->productIdsGrantedOnPurchase($product);
        if ($grantIds === [] && ($product['kind'] ?? '') === ProductService::KIND_COMBO) {
            return ['ok' => false, 'message' => 'Combo sem produtos incluídos. Configure no admin.'];
        }
        if ($grantIds === []) {
            $grantIds = [(int) $product['id']];
        }

        $licenseBefore = $this->licenses->findLicenseForOrder($orderRowId);
        foreach ($grantIds as $productId) {
            $this->products->grantEntitlement($customerId, $productId, $orderRowId);
        }

        $license = $licenseBefore;
        foreach ($grantIds as $productId) {
            $granted = $this->products->findById($productId);
            if (! $granted || ($granted['kind'] ?? '') !== ProductService::KIND_GATEWAY) {
                continue;
            }
            $existingLic = $this->licenses->findLicenseForOrderAndProduct($orderRowId, $productId);
            if ($existingLic) {
                $license = $existingLic;

                continue;
            }
            $license = $this->licenses->createLicense(
                1,
                'Order #'.$externalOrderId,
                null,
                $customerId,
                $productId,
                $orderRowId
            );
        }

        if ($license === null) {
            $license = $this->licenses->findLicenseForOrder($orderRowId);
        }

        $shouldNotify = $sendNotifications
            || ($licenseBefore === null && $license !== null);

        if ($shouldNotify && $license) {
            $portalUrl = rtrim($this->appUrl, '/');
            $isNewAccount = empty($customer['password_hash']);
            $setPasswordUrl = null;
            if ($isNewAccount) {
                $token = $this->customers->createResetToken($customerId);
                $setPasswordUrl = $portalUrl.'/reset-password/'.$token;
            }

            $this->mailer->send(
                (string) $customer['email'],
                $isNewAccount ? 'Bem-vindo — defina sua senha' : 'Novo acesso — sua licença',
                $this->mailer->welcomeHtml(
                    (string) $customer['name'],
                    $portalUrl.'/login',
                    $setPasswordUrl ?: ($portalUrl.'/forgot-password'),
                    (string) $license['license_key'],
                    $isNewAccount
                )
            );

            $amount = $payload['amount'] ?? ($payload['order']['amount'] ?? null);
            $currency = (string) ($payload['order']['currency'] ?? ($payload['currency'] ?? 'BRL'));
            $this->safeOutbound('order.paid', [
                'event' => 'order.paid',
                'customer' => [
                    'name' => (string) $customer['name'],
                    'email' => (string) $customer['email'],
                    'phone' => $customer['phone'] ?? null,
                ],
                'license_key' => (string) $license['license_key'],
                'portal_url' => $portalUrl,
                'set_password_url' => $setPasswordUrl,
                'product' => [
                    'name' => (string) ($product['name'] ?? ''),
                    'external_id' => $product['external_product_id'] ?? ($product['external_id'] ?? null),
                ],
                'order' => [
                    'external_id' => $externalOrderId,
                    'amount' => $amount,
                    'currency' => $currency,
                ],
                'timestamp' => gmdate('c'),
            ]);
        }

        return [
            'ok' => true,
            'message' => $shouldNotify ? 'Order processed' : 'Additional products granted',
            'license_key' => $license['license_key'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,message:string}
     */
    private function handleRefund(array $payload): array
    {
        $externalOrderId = (string) ($payload['order']['id'] ?? '');
        if ($externalOrderId === '') {
            return ['ok' => false, 'message' => 'Missing order.id'];
        }

        $order = $this->findOrder($externalOrderId);
        if (! $order) {
            return ['ok' => true, 'message' => 'Order not found (noop)'];
        }

        $this->updateOrderStatus((int) $order['id'], 'refunded');
        if (! empty($order['product_id']) && ! empty($order['customer_id'])) {
            $orderProduct = $this->products->findById((int) $order['product_id']);
            if ($orderProduct) {
                $this->products->revokeEntitlementsForPurchase((int) $order['customer_id'], $orderProduct);
            } else {
                $this->products->revokeEntitlement((int) $order['customer_id'], (int) $order['product_id']);
            }
        }
        $this->licenses->revokeByOrder((int) $order['id']);

        $customer = $this->customers->findById((int) $order['customer_id']);
        $license = $this->licenses->findLicenseForOrder((int) $order['id']);
        if ($customer) {
            $this->mailer->send(
                (string) $customer['email'],
                'Licença revogada — reembolso',
                $this->mailer->refundHtml((string) $customer['name'])
            );

            $this->safeOutbound('order.refunded', [
                'event' => 'order.refunded',
                'customer' => [
                    'name' => (string) $customer['name'],
                    'email' => (string) $customer['email'],
                    'phone' => $customer['phone'] ?? null,
                ],
                'license_key' => $license['license_key'] ?? null,
                'portal_url' => rtrim($this->appUrl, '/'),
                'set_password_url' => null,
                'product' => [
                    'name' => null,
                    'external_id' => null,
                ],
                'order' => [
                    'external_id' => $externalOrderId,
                    'amount' => $order['amount'] ?? null,
                    'currency' => $order['currency'] ?? 'BRL',
                ],
                'timestamp' => gmdate('c'),
            ]);
        }

        return ['ok' => true, 'message' => 'Refund processed'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function safeOutbound(string $event, array $payload): void
    {
        try {
            $this->outbound->dispatch($event, $payload);
        } catch (\Throwable) {
            // inbound webhook must succeed even if outbound fails
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function logEvent(string $event, ?string $orderId, array $body): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_events (event_name, external_order_id, payload_json, processed, created_at)
             VALUES (:e, :oid, :json, 0, :c)'
        );
        $stmt->execute([
            'e' => $event,
            'oid' => $orderId,
            'json' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'c' => gmdate('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function markProcessed(int $id, string $result): void
    {
        $stmt = $this->pdo->prepare('UPDATE webhook_events SET processed = 1, process_result = :r WHERE id = :id');
        $stmt->execute(['r' => $result, 'id' => $id]);
    }

    /** @return array<string, mixed>|null */
    private function findOrder(string $externalOrderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE external_order_id = :id LIMIT 1');
        $stmt->execute(['id' => $externalOrderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $existing
     */
    private function upsertOrder(
        string $externalOrderId,
        int $customerId,
        int $productId,
        array $payload,
        string $status,
        ?array $existing = null,
    ): int {
        $existing = $existing ?? $this->findOrder($externalOrderId);
        $now = gmdate('c');
        $amount = $payload['amount'] ?? ($payload['order']['amount'] ?? null);
        $currency = $payload['order']['currency'] ?? ($payload['currency'] ?? 'BRL');
        $method = $payload['paymentMethod'] ?? ($payload['payment']['method'] ?? null);

        if ($existing) {
            $keepProductId = ! empty($existing['product_id']) ? (int) $existing['product_id'] : $productId;
            $stmt = $this->pdo->prepare(
                'UPDATE orders SET status = :s, product_id = :p, amount = :a, currency = :cur, payment_method = :m, raw_json = :j, updated_at = :u WHERE id = :id'
            );
            $stmt->execute([
                's' => $status,
                'p' => $keepProductId,
                'a' => $amount,
                'cur' => $currency,
                'm' => $method,
                'j' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'u' => $now,
                'id' => (int) $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO orders (external_order_id, customer_id, product_id, status, amount, currency, payment_method, raw_json, created_at, updated_at)
             VALUES (:eid, :cid, :pid, :s, :a, :cur, :m, :j, :c, :u)'
        );
        $stmt->execute([
            'eid' => $externalOrderId,
            'cid' => $customerId,
            'pid' => $productId,
            's' => $status,
            'a' => $amount,
            'cur' => $currency,
            'm' => $method,
            'j' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'c' => $now,
            'u' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function updateOrderStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = :s, updated_at = :u WHERE id = :id');
        $stmt->execute(['s' => $status, 'u' => gmdate('c'), 'id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listEvents(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhook_events ORDER BY id DESC LIMIT :lim');
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listOrders(): array
    {
        return $this->pdo->query(
            'SELECT o.*, c.email AS customer_email, p.name AS product_name
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             LEFT JOIN products p ON p.id = o.product_id
             ORDER BY o.id DESC'
        )->fetchAll() ?: [];
    }
}
