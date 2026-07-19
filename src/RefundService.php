<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class RefundService
{
    public const WINDOW_DAYS = 7;

    private const CARD_METHODS = [
        'card',
        'credit_card',
        'debit_card',
        'creditcard',
        'debitcard',
        'cartao',
        'cartão',
        'cartao_credito',
        'cartao_debito',
        'credit',
        'debit',
    ];

    public function __construct(
        private PDO $pdo,
        private ProductService $products,
        private LicenseService $licenses,
        private CustomerService $customers,
        private Mailer $mailer,
    ) {}

    public static function isCardPayment(?string $method): bool
    {
        if ($method === null || trim($method) === '') {
            return false;
        }
        $normalized = mb_strtolower(trim($method), 'UTF-8');
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return in_array($normalized, self::CARD_METHODS, true);
    }

    public static function isWithinRefundWindow(string $createdAt): bool
    {
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return false;
        }

        return (time() - $ts) <= (self::WINDOW_DAYS * 86400);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function canRequest(array $order, ?array $existingRequest = null): bool
    {
        if (($order['status'] ?? '') !== 'completed') {
            return false;
        }
        if (! self::isCardPayment(isset($order['payment_method']) ? (string) $order['payment_method'] : null)) {
            return false;
        }
        if (! self::isWithinRefundWindow((string) ($order['created_at'] ?? ''))) {
            return false;
        }
        if ($existingRequest !== null) {
            $status = (string) ($existingRequest['status'] ?? '');
            if ($status === 'pending' || $status === 'completed') {
                return false;
            }
        }

        return true;
    }

    /** @return array{ok:bool,message:string} */
    public function request(int $customerId, int $orderId, string $reason): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            return ['ok' => false, 'message' => 'Informe o motivo com pelo menos 10 caracteres.'];
        }
        if (mb_strlen($reason) > 2000) {
            return ['ok' => false, 'message' => 'Motivo muito longo.'];
        }

        $order = $this->findOrder($orderId);
        if (! $order || (int) ($order['customer_id'] ?? 0) !== $customerId) {
            return ['ok' => false, 'message' => 'Pedido não encontrado.'];
        }

        $existing = $this->findByOrderId($orderId);
        if (! $this->canRequest($order, $existing)) {
            return ['ok' => false, 'message' => 'Este pedido não é elegível para reembolso.'];
        }

        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO refund_requests (order_id, customer_id, reason, status, created_at, updated_at)
             VALUES (:oid, :cid, :reason, :status, :c, :u)'
        );
        $stmt->execute([
            'oid' => $orderId,
            'cid' => $customerId,
            'reason' => $reason,
            'status' => 'pending',
            'c' => $now,
            'u' => $now,
        ]);

        $this->updateOrderStatus($orderId, 'refund_pending');

        if (! empty($order['product_id'])) {
            $this->products->revokeEntitlement($customerId, (int) $order['product_id']);
        }
        $this->licenses->revokeByOrder($orderId);

        $customer = $this->customers->findById($customerId);
        if ($customer) {
            $this->mailer->send(
                (string) $customer['email'],
                'Solicitação de reembolso recebida',
                $this->mailer->refundRequestHtml((string) $customer['name'])
            );
        }

        return [
            'ok' => true,
            'message' => 'Reembolso em processamento. O valor pode levar até 30 dias para aparecer na fatura do seu cartão.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(): array
    {
        $rows = $this->pdo->query(
            'SELECT r.*,
                    o.external_order_id, o.amount, o.currency, o.payment_method, o.raw_json, o.status AS order_status,
                    c.email AS customer_email, c.name AS customer_name,
                    p.name AS product_name
             FROM refund_requests r
             JOIN orders o ON o.id = r.order_id
             LEFT JOIN customers c ON c.id = r.customer_id
             LEFT JOIN products p ON p.id = o.product_id
             ORDER BY CASE WHEN r.status = \'pending\' THEN 0 ELSE 1 END, r.id DESC'
        )->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $row['gateway_transaction_id'] = self::extractGatewayTransactionId(
                isset($row['raw_json']) ? (string) $row['raw_json'] : null
            );
        }
        unset($row);

        return $rows;
    }

    /** @return array{ok:bool,message:string} */
    public function complete(int $id, ?string $notes = null): array
    {
        $request = $this->findById($id);
        if (! $request) {
            return ['ok' => false, 'message' => 'Solicitação não encontrada.'];
        }
        if (($request['status'] ?? '') === 'completed') {
            return ['ok' => true, 'message' => 'Já estava marcado como reembolsado.'];
        }

        $now = gmdate('c');
        $notes = $notes !== null ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE refund_requests
             SET status = :s, admin_notes = :n, processed_at = :p, updated_at = :u
             WHERE id = :id'
        );
        $stmt->execute([
            's' => 'completed',
            'n' => $notes,
            'p' => $now,
            'u' => $now,
            'id' => $id,
        ]);

        $this->updateOrderStatus((int) $request['order_id'], 'refunded');

        return ['ok' => true, 'message' => 'Reembolso marcado como concluído.'];
    }

    public static function extractGatewayTransactionId(?string $rawJson): ?string
    {
        if ($rawJson === null || $rawJson === '') {
            return null;
        }
        $data = json_decode($rawJson, true);
        if (! is_array($data)) {
            return null;
        }
        $payment = is_array($data['payment'] ?? null) ? $data['payment'] : [];
        $id = $payment['gateway_transaction_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refund_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByOrderId(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM refund_requests WHERE order_id = :oid ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['oid' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findOrder(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function updateOrderStatus(int $orderId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = :s, updated_at = :u WHERE id = :id');
        $stmt->execute(['s' => $status, 'u' => gmdate('c'), 'id' => $orderId]);
    }
}
