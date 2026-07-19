<?php

declare(strict_types=1);

/**
 * Reaplica o seed oficial da documentação.
 *
 * Uso:
 *   php bin/seed-docs.php
 *   php bin/seed-docs.php --force
 */

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
use LicenseApi\LessonService;
use LicenseApi\SettingsStore;

Env::load($basePath);
Database::migrate($basePath);
$pdo = Database::pdo($basePath);
$lessons = new LessonService($pdo);
$settings = new SettingsStore($pdo);

$force = in_array('--force', $argv ?? [], true);
$before = $settings->get('docs_seed_version');

if ($force) {
    $count = $lessons->applySeedFromFile(true);
    $settings->set('docs_seed_version', LessonService::SEED_VERSION);
    fwrite(STDOUT, "Seed forçado: {$count} páginas upsertadas. Versão=".LessonService::SEED_VERSION.' (antes: '.($before ?? 'null').")\n");
    exit(0);
}

$lessons->seedDefaults(false);
$after = $settings->get('docs_seed_version');
$pageCount = (int) $pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
$sectionCount = (int) $pdo->query('SELECT COUNT(*) FROM lesson_sections')->fetchColumn();
fwrite(STDOUT, "Seed OK. Seções={$sectionCount} Páginas={$pageCount} Versão=".($after ?? 'null')."\n");
exit(0);
