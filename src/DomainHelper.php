<?php

declare(strict_types=1);

namespace LicenseApi;

final class DomainHelper
{
    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^https?://#', '', $value) ?: $value;
        $value = explode('/', $value)[0] ?? $value;
        $value = explode('?', $value)[0] ?? $value;
        $value = explode(':', $value)[0] ?? $value;

        return rtrim($value, '.');
    }

    public static function isLocalhost(string $domain): bool
    {
        $d = self::normalize($domain);
        if (in_array($d, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return true;
        }

        return str_ends_with($d, '.local') || str_ends_with($d, '.test') || str_ends_with($d, '.localhost');
    }
}
