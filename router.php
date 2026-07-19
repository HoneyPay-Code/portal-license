<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

if ($path === '' || $path[0] !== '/' || str_contains($path, "\0") || str_contains($path, '\\')) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

$segments = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
foreach ($segments as $segment) {
    if ($segment === '.' || $segment === '..') {
        http_response_code(400);
        echo 'Bad request';
        exit;
    }
}

$publicRoot = realpath(__DIR__.'/public');
if ($publicRoot === false) {
    http_response_code(500);
    echo 'Public root missing';
    exit;
}

$relative = implode('/', $segments);
$candidate = $relative === '' ? $publicRoot : $publicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

$mime = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'webp' => 'image/webp',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'map' => 'application/json',
    'txt' => 'text/plain; charset=utf-8',
];

$real = is_file($candidate) ? realpath($candidate) : false;
if ($real !== false
    && str_starts_with($real, $publicRoot.DIRECTORY_SEPARATOR)
    && $path !== '/'
) {
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    if (isset($mime[$ext])) {
        header('Content-Type: '.$mime[$ext]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        readfile($real);
        exit;
    }
}

require __DIR__.'/public/index.php';
