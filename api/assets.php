<?php

declare(strict_types=1);

$publicRoot = realpath(__DIR__.'/../public');
$requestedFile = rawurldecode((string) ($_GET['file'] ?? ''));
$assetPath = $publicRoot === false ? false : realpath($publicRoot.DIRECTORY_SEPARATOR.$requestedFile);

if (
    $publicRoot === false
    || $assetPath === false
    || ! str_starts_with($assetPath, $publicRoot.DIRECTORY_SEPARATOR)
    || ! is_file($assetPath)
) {
    http_response_code(404);
    exit;
}

$contentType = match (strtolower(pathinfo($assetPath, PATHINFO_EXTENSION))) {
    'css' => 'text/css; charset=UTF-8',
    'js' => 'application/javascript; charset=UTF-8',
    'json' => 'application/json; charset=UTF-8',
    'ico' => 'image/x-icon',
    'png' => 'image/png',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'txt' => 'text/plain; charset=UTF-8',
    default => 'application/octet-stream',
};

header('Content-Type: '.$contentType);
header('Content-Length: '.filesize($assetPath));
header('Cache-Control: public, max-age=31536000, immutable');

readfile($assetPath);
