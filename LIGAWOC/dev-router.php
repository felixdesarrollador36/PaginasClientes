<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = '/LIGAWOC';
$path = $uri;

if (strpos($path, $base) === 0) {
    $path = substr($path, strlen($base));
}

if ($path === false || $path === '') {
    $path = '/';
}

$fullPath = realpath(__DIR__ . $path);
$rootPath = realpath(__DIR__);

if ($fullPath && $rootPath && strpos($fullPath, $rootPath) === 0 && is_file($fullPath)) {
    $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeMap = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'txt' => 'text/plain; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'xml' => 'application/xml; charset=UTF-8',
        'pdf' => 'application/pdf',
    ];

    $mimeType = $mimeMap[$extension] ?? (function_exists('mime_content_type') ? mime_content_type($fullPath) : null);
    if ($mimeType) {
        header('Content-Type: ' . $mimeType);
    }
    readfile($fullPath);
    return true;
}

require __DIR__ . '/index.php';
