<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in development server.
 *
 * Usage: php -S localhost:8000 router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Block direct access to sensitive paths (mirror production .htaccess).
if (preg_match('#^/(core|includes|uploads)(/|$)#', $path)
    || $path === '/config.php'
    || $path === '/config.sample.php') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    return true;
}

// Installer has its own front controller.
if (str_starts_with($path, '/install')) {
    require __DIR__ . '/install/index.php';
    return true;
}

$file = __DIR__ . $path;

// Serve existing files directly (assets, favicon, etc.) — never sensitive paths above.
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
