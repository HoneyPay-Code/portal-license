<?php

declare(strict_types=1);

namespace LicenseApi;

final class Signer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload, string $key): string
    {
        return hash_hmac('sha256', self::canonicalJson($payload), $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function canonicalJson(array $payload): string
    {
        $normalized = [
            'valid' => (bool) ($payload['valid'] ?? false),
            'blocked' => (bool) ($payload['blocked'] ?? false),
            'domain' => $payload['domain'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
            'support_contact' => $payload['support_contact'] ?? null,
            'install_id' => $payload['install_id'] ?? null,
            'bound' => (bool) ($payload['bound'] ?? false),
            'is_localhost' => (bool) ($payload['is_localhost'] ?? false),
        ];

        return json_encode($normalized, JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function signedResponse(array $payload, string $key): array
    {
        $canonical = [
            'valid' => (bool) ($payload['valid'] ?? false),
            'blocked' => (bool) ($payload['blocked'] ?? false),
            'domain' => $payload['domain'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
            'support_contact' => $payload['support_contact'] ?? null,
            'install_id' => $payload['install_id'] ?? null,
            'bound' => (bool) ($payload['bound'] ?? false),
            'is_localhost' => (bool) ($payload['is_localhost'] ?? false),
        ];
        $canonical['signature'] = self::sign($canonical, $key);

        return $canonical;
    }
}
