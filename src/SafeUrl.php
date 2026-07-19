<?php

declare(strict_types=1);

namespace LicenseApi;

final class SafeUrl
{
    public static function isHttpUrl(string $url, bool $allowHttp = false): bool
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme === 'https') {
            return true;
        }
        if ($scheme === 'http' && $allowHttp) {
            return true;
        }

        return false;
    }

    public static function forHref(?string $url, bool $allowHttp = false): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if (! self::isHttpUrl($url, $allowHttp)) {
            return null;
        }

        return $url;
    }

    public static function isAllowedOutbound(string $url, bool $allowHttpLocal = false): bool
    {
        if (! self::isHttpUrl($url, $allowHttpLocal)) {
            return false;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return $allowHttpLocal;
        }
        // Block obvious metadata / link-local hostnames
        if ($host === 'metadata.google.internal' || str_ends_with($host, '.internal')) {
            return false;
        }
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = $resolved;
            }
            // Also try IPv6
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $rec) {
                    if (! empty($rec['ipv6'])) {
                        $ips[] = (string) $rec['ipv6'];
                    }
                }
            }
        }
        if ($ips === [] && ! $allowHttpLocal) {
            // unresolved host — deny in production-like mode
            return false;
        }
        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return false;
            }
        }

        return true;
    }

    public static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }
            $ranges = [
                ['0.0.0.0', '0.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['100.64.0.0', '100.127.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.0.0.0', '192.0.0.255'],
                ['192.168.0.0', '192.168.255.255'],
                ['198.18.0.0', '198.19.255.255'],
                ['224.0.0.0', '255.255.255.255'],
            ];
            foreach ($ranges as [$start, $end]) {
                if ($long >= ip2long($start) && $long <= ip2long($end)) {
                    return true;
                }
            }

            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip = strtolower($ip);
            if ($ip === '::1' || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd') || str_starts_with($ip, 'fe80')) {
                return true;
            }
            // IPv4-mapped
            if (str_starts_with($ip, '::ffff:')) {
                $v4 = substr($ip, 7);

                return self::isPrivateIp($v4);
            }
        }

        return true;
    }

    public static function stripCrLf(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}
