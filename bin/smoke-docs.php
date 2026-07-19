<?php

declare(strict_types=1);

/**
 * Smoke check local da docs (sem servidor HTTP).
 * php bin/smoke-docs.php
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

use LicenseApi\Markdown;

$seed = require $basePath.'/content/docs-seed.php';
$sections = count($seed);
$pages = 0;
$slugs = [];
foreach ($seed as $section) {
    foreach ($section['pages'] as $page) {
        $pages++;
        $slug = $page['slug'];
        if (isset($slugs[$slug])) {
            fwrite(STDERR, "Slug duplicado: {$slug}\n");
            exit(1);
        }
        $slugs[$slug] = true;
        $html = Markdown::toHtml($page['body']);
        if ($html === '' || ! str_contains($html, '<h1')) {
            fwrite(STDERR, "Markdown vazio/sem H1: {$slug}\n");
            exit(1);
        }
        // exercise features once
        if ($slug === 'visao-geral') {
            if (! str_contains($html, 'portal.honeypay.tech')) {
                fwrite(STDERR, "URL do portal ausente em visao-geral\n");
                exit(1);
            }
            if (! str_contains($html, 'docs-table') && ! str_contains($html, '<table')) {
                fwrite(STDERR, "Tabela não renderizada em visao-geral\n");
                exit(1);
            }
            if (! str_contains($html, 'docs-callout')) {
                fwrite(STDERR, "Callout não renderizado em visao-geral\n");
                exit(1);
            }
            $toc = Markdown::extractToc($html);
            if ($toc === []) {
                fwrite(STDERR, "TOC vazio em visao-geral\n");
                exit(1);
            }
        }
    }
}

$views = [
    'views/layout_docs.php',
    'views/customer/docs_index.php',
    'views/customer/docs_show.php',
    'views/admin/docs.php',
];
foreach ($views as $v) {
    if (! is_file($basePath.'/'.$v)) {
        fwrite(STDERR, "View ausente: {$v}\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK smoke docs: {$sections} seções, {$pages} páginas, Markdown+views ok\n");
exit(0);
