<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(string $basePath): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = strtolower((string) Env::get('DB_DRIVER', 'sqlite'));

        if ($driver === 'mysql') {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $db = Env::get('DB_DATABASE', 'license_api');
            $user = Env::get('DB_USERNAME', 'root');
            $pass = Env::get('DB_PASSWORD', '');
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $relative = Env::get('DB_PATH', 'storage/database.sqlite') ?: 'storage/database.sqlite';
            $path = str_starts_with($relative, DIRECTORY_SEPARATOR) || preg_match('#^[A-Za-z]:\\\\#', $relative)
                ? $relative
                : $basePath.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            self::$pdo = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }

        return self::$pdo;
    }

    public static function migrate(string $basePath): void
    {
        $pdo = self::pdo($basePath);
        $schema = (string) file_get_contents($basePath.DIRECTORY_SEPARATOR.'schema.sql');
        $driver = strtolower((string) Env::get('DB_DRIVER', 'sqlite'));

        if ($driver === 'mysql') {
            $schema = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT AUTO_INCREMENT PRIMARY KEY', $schema);
            $schema = str_replace('INTEGER NOT NULL DEFAULT 0', 'TINYINT NOT NULL DEFAULT 0', $schema);
            $schema = str_replace('INTEGER NOT NULL DEFAULT 1', 'TINYINT NOT NULL DEFAULT 1', $schema);
            $schema = preg_replace('/\bTEXT NOT NULL\b/', 'DATETIME NOT NULL', $schema) ?: $schema;
            $schema = preg_replace('/\bTEXT NULL\b/', 'TEXT NULL', $schema) ?: $schema;
            foreach ([
                'customer_note', 'body_markdown', 'raw_json', 'payload_json', 'process_result', 'checkout_url',
                'events', 'request_body', 'response_body', 'url', 'value', 'bearer_token', 'totp_secret',
            ] as $keep) {
                $schema = str_replace("{$keep} DATETIME NULL", "{$keep} TEXT NULL", $schema);
                $schema = str_replace("{$keep} DATETIME NOT NULL", "{$keep} TEXT NOT NULL", $schema);
            }
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        self::ensureColumn($pdo, 'licenses', 'customer_id', 'INTEGER NULL');
        self::ensureColumn($pdo, 'licenses', 'product_id', 'INTEGER NULL');
        self::ensureColumn($pdo, 'licenses', 'order_id', 'INTEGER NULL');
        self::ensureColumn($pdo, 'activations', 'is_localhost', 'INTEGER NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'activations', 'bound_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'admins', 'totp_secret', 'TEXT NULL');
        self::ensureColumn($pdo, 'admins', 'totp_enabled', 'INTEGER NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'admins', 'totp_confirmed_at', 'TEXT NULL');
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Throwable) {
            // column already exists
        }
    }
}
