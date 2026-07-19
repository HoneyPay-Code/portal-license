<?php

declare(strict_types=1);

$basePath = __DIR__;

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

use LicenseApi\AdminTotpService;
use LicenseApi\Auth;
use LicenseApi\CustomerService;
use LicenseApi\Database;
use LicenseApi\Env;
use LicenseApi\LessonService;
use LicenseApi\LicenseService;
use LicenseApi\Mailer;
use LicenseApi\OutboundWebhookService;
use LicenseApi\ProductService;
use LicenseApi\ReleaseService;
use LicenseApi\SecretBox;
use LicenseApi\Security;
use LicenseApi\SettingsStore;
use LicenseApi\WebhookProcessor;

Env::load($basePath);
if (! is_file($basePath.'/.env')) {
    copy($basePath.'/.env.example', $basePath.'/.env');
    Env::load($basePath);
}

Security::assertProductionSecrets();
Security::startSession();
Security::sendSecurityHeaders();

Database::migrate($basePath);
$pdo = Database::pdo($basePath);

$signingKey = (string) Env::get('LICENSE_SIGNING_KEY', 'dev-signing-key');
$appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8081'), '/');
$appName = (string) Env::get('APP_NAME', 'License Portal');
$appEnv = strtolower((string) Env::get('APP_ENV', 'local'));

$auth = new Auth($pdo);
$auth->ensureBootstrapAdmin(
    (string) Env::get('ADMIN_EMAIL', 'admin@localhost'),
    (string) Env::get('ADMIN_PASSWORD', 'admin123')
);

$settings = new SettingsStore($pdo);
$customers = new CustomerService($pdo);
$products = new ProductService($pdo, $basePath);
$products->seedDefaults();
$licenses = new LicenseService($pdo, $signingKey !== '' ? $signingKey : 'dev-signing-key', Env::get('SUPPORT_CONTACT'));
$releases = new ReleaseService($pdo, $basePath, $licenses, $appUrl);
$lessons = new LessonService($pdo);
$lessons->seedDefaults();
$outbound = new OutboundWebhookService($pdo, $appEnv === 'local');
$mailer = new Mailer(
    $basePath,
    $settings,
    [
        'mail_from' => Env::get('MAIL_FROM'),
        'mail_host' => Env::get('MAIL_HOST') ?: null,
        'mail_port' => Env::get('MAIL_PORT') ?: '587',
        'mail_username' => Env::get('MAIL_USERNAME'),
        'mail_password' => Env::get('MAIL_PASSWORD'),
        'mail_encryption' => Env::get('MAIL_ENCRYPTION') ?: 'tls',
    ]
);
$adminTotp = new AdminTotpService(
    $pdo,
    new SecretBox($signingKey !== '' ? $signingKey : 'dev-signing-key'),
    $appName
);
$webhooks = new WebhookProcessor(
    $pdo,
    $customers,
    $products,
    $licenses,
    $mailer,
    $outbound,
    $appUrl,
    filter_var(Env::get('WEBHOOK_ACCEPT_TEST', 'false'), FILTER_VALIDATE_BOOLEAN)
);

return compact(
    'basePath',
    'pdo',
    'auth',
    'settings',
    'customers',
    'products',
    'licenses',
    'releases',
    'lessons',
    'mailer',
    'outbound',
    'adminTotp',
    'webhooks',
    'appUrl',
    'appName',
    'appEnv',
    'signingKey'
);
