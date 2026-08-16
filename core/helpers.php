<?php

declare(strict_types=1);

/**
 * View / controller helpers.
 */

use FormFlow\Csrf;

if (!function_exists('csrf_field')) {
  function csrf_field(): string
  {
    return Csrf::field();
  }
}

if (!function_exists('csrf_token')) {
  function csrf_token(): string
  {
    return Csrf::token();
  }
}

if (!function_exists('e')) {
  function e(?string $value): string
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('flash')) {
  function flash(string $key, ?string $message = null): ?string
  {
    if ($message !== null) {
      $_SESSION['_flash'][$key] = $message;

      return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return is_string($value) ? $value : null;
  }
}

if (!function_exists('old')) {
  function old(string $key, string $default = ''): string
  {
    $old = $_SESSION['_old'][$key] ?? $default;

    return is_string($old) ? $old : $default;
  }
}

if (!function_exists('set_old')) {
  /**
   * @param array<string, mixed> $input
   */
  function set_old(array $input): void
  {
    $_SESSION['_old'] = $input;
  }
}

if (!function_exists('clear_old')) {
  function clear_old(): void
  {
    unset($_SESSION['_old']);
  }
}

if (!function_exists('redirect')) {
  function redirect(string $url, int $code = 302): void
  {
    header('Location: ' . $url, true, $code);
    exit;
  }
}

if (!function_exists('app_url')) {
  /**
   * @param array<string, mixed> $config
   */
  function app_url(array $config, string $path = ''): string
  {
    $base = rtrim((string) ($config['app']['url'] ?? ''), '/');
    if ($base === '') {
      $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
      $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
      $base = ($https ? 'https://' : 'http://') . $host;
    }
    $path = '/' . ltrim($path, '/');

    return $base === '' ? $path : $base . $path;
  }
}
