<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($basePath): void {
    $prefix = 'LicenseApi\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $basePath.'/src/'.str_replace('\\', '/', $relative).'.php';
    if (is_file($file)) {
        require $file;
    }
});

use LicenseApi\Database;
use LicenseApi\Env;
use LicenseApi\LicenseService;

Env::load($basePath);
Database::migrate($basePath);
$pdo = Database::pdo($basePath);
$svc = new LicenseService(
    $pdo,
    (string) Env::get('LICENSE_SIGNING_KEY', 'x'),
    Env::get('SUPPORT_CONTACT')
);
$lic = $svc->createLicense(5, 'Local docker test');
fwrite(STDOUT, $lic['license_key'].PHP_EOL);
