<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class OutboundWebhookService
{
    public function __construct(
        private PDO $pdo,
        private bool $allowHttpLocal = false,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->pdo->query('SELECT * FROM outbound_webhooks ORDER BY id DESC')->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM outbound_webhooks WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param  list<string>  $events
     */
    public function create(string $name, string $url, ?string $bearerToken, array $events, bool $enabled = true): int
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO outbound_webhooks (name, url, bearer_token, events, enabled, created_at, updated_at)
             VALUES (:n, :u, :t, :e, :en, :c, :up)'
        );
        $stmt->execute([
            'n' => $name,
            'u' => $url,
            't' => $bearerToken,
            'e' => json_encode(array_values($events), JSON_UNESCAPED_SLASHES),
            'en' => $enabled ? 1 : 0,
            'c' => $now,
            'up' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param  list<string>  $events
     */
    public function update(int $id, string $name, string $url, ?string $bearerToken, array $events, bool $enabled, bool $updateToken): void
    {
        if ($updateToken) {
            $stmt = $this->pdo->prepare(
                'UPDATE outbound_webhooks SET name = :n, url = :u, bearer_token = :t, events = :e, enabled = :en, updated_at = :up WHERE id = :id'
            );
            $stmt->execute([
                'n' => $name,
                'u' => $url,
                't' => $bearerToken,
                'e' => json_encode(array_values($events), JSON_UNESCAPED_SLASHES),
                'en' => $enabled ? 1 : 0,
                'up' => gmdate('c'),
                'id' => $id,
            ]);

            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE outbound_webhooks SET name = :n, url = :u, events = :e, enabled = :en, updated_at = :up WHERE id = :id'
        );
        $stmt->execute([
            'n' => $name,
            'u' => $url,
            'e' => json_encode(array_values($events), JSON_UNESCAPED_SLASHES),
            'en' => $enabled ? 1 : 0,
            'up' => gmdate('c'),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM outbound_webhooks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload): void
    {
        $hooks = $this->pdo->query('SELECT * FROM outbound_webhooks WHERE enabled = 1')->fetchAll() ?: [];
        foreach ($hooks as $hook) {
            $events = json_decode((string) ($hook['events'] ?? '[]'), true);
            if (! is_array($events) || ! in_array($event, $events, true)) {
                continue;
            }
            $this->deliver((int) $hook['id'], $event, (string) $hook['url'], $hook['bearer_token'] ?? null, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,status:?int,message:string}
     */
    public function deliver(int $hookId, string $event, string $url, ?string $bearerToken, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $status = null;
        $responseBody = '';
        $ok = false;
        $message = '';

        if (! SafeUrl::isAllowedOutbound($url, $this->allowHttpLocal)) {
            $message = 'URL blocked (scheme/SSRF policy)';
            $stmt = $this->pdo->prepare(
                'INSERT INTO outbound_webhook_deliveries
                 (outbound_webhook_id, event_name, request_body, response_status, response_body, ok, created_at)
                 VALUES (:hid, :e, :req, :st, :res, :ok, :c)'
            );
            $stmt->execute([
                'hid' => $hookId,
                'e' => $event,
                'req' => substr($body, 0, 4000),
                'st' => null,
                'res' => $message,
                'ok' => 0,
                'c' => gmdate('c'),
            ]);

            return ['ok' => false, 'status' => null, 'message' => $message];
        }

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                $headers = ['Content-Type: application/json', 'Accept: application/json'];
                if (is_string($bearerToken) && $bearerToken !== '') {
                    $headers[] = 'Authorization: Bearer '.$bearerToken;
                }
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_CONNECTTIMEOUT => 5,
                ]);
                $responseBody = (string) curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_close($ch);
                if ($err !== '') {
                    $message = $err;
                } else {
                    $ok = $status >= 200 && $status < 300;
                    $message = $ok ? 'Delivered' : 'HTTP '.$status;
                }
            } else {
                $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
                if (is_string($bearerToken) && $bearerToken !== '') {
                    $headers .= 'Authorization: Bearer '.$bearerToken."\r\n";
                }
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => $headers,
                        'content' => $body,
                        'timeout' => 8,
                        'ignore_errors' => true,
                    ],
                ]);
                $responseBody = (string) (@file_get_contents($url, false, $ctx) ?: '');
                if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                    $status = (int) $m[1];
                }
                $ok = $status !== null && $status >= 200 && $status < 300;
                $message = $ok ? 'Delivered' : 'HTTP '.($status ?? 0);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO outbound_webhook_deliveries
             (outbound_webhook_id, event_name, request_body, response_status, response_body, ok, created_at)
             VALUES (:hid, :e, :req, :st, :res, :ok, :c)'
        );
        $stmt->execute([
            'hid' => $hookId,
            'e' => $event,
            'req' => substr($body, 0, 4000),
            'st' => $status,
            'res' => substr($responseBody, 0, 4000),
            'ok' => $ok ? 1 : 0,
            'c' => gmdate('c'),
        ]);

        return ['ok' => $ok, 'status' => $status, 'message' => $message];
    }

    /** @return list<array<string, mixed>> */
    public function listDeliveries(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT d.*, w.name AS webhook_name FROM outbound_webhook_deliveries d
                LEFT JOIN outbound_webhooks w ON w.id = d.outbound_webhook_id
                ORDER BY d.id DESC LIMIT '.$limit;

        return $this->pdo->query($sql)->fetchAll() ?: [];
    }
}
