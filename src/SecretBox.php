<?php

declare(strict_types=1);

namespace LicenseApi;

final class SecretBox
{
    public function __construct(private string $masterKey) {}

    public function encrypt(string $plaintext): string
    {
        $key = $this->key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('encrypt failed');
        }

        return 'v1.'.base64_encode($iv.$tag.$cipher);
    }

    public function decrypt(string $payload): string
    {
        if (! str_starts_with($payload, 'v1.')) {
            throw new \RuntimeException('invalid payload');
        }
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('invalid payload');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('decrypt failed');
        }

        return $plain;
    }

    private function key(): string
    {
        return hash('sha256', $this->masterKey, true);
    }
}
