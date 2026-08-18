<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * CORS handling for public submission endpoint (PRD §6.5 Layer 5).
 */
class CorsHandler
{
  /**
   * Apply CORS headers for the current request. Returns false if origin is not allowed.
   *
   * @param array<string, mixed> $form
   * @param array<string, mixed> $config
   */
  public static function apply(array $form, array $config): bool
  {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $allowed = is_array($settings['allowed_domains'] ?? null) ? $settings['allowed_domains'] : [];
    $appUrl = rtrim((string) ($config['app']['url'] ?? ''), '/');
    $appHost = strtolower((string) (parse_url($appUrl, PHP_URL_HOST) ?: ''));
    $requestHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));

    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 86400');

    if ($origin === '') {
      return true;
    }

    $originHost = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));

    if ($originHost !== '' && $requestHost !== '' && strcasecmp($originHost, $requestHost) === 0) {
      header('Access-Control-Allow-Origin: ' . $origin);

      return true;
    }

    if ($allowed === []) {
      if ($originHost !== '' && $appHost !== '' && strcasecmp($originHost, $appHost) === 0) {
        header('Access-Control-Allow-Origin: ' . $origin);

        return true;
      }

      return false;
    }

    if (self::originMatchesAllowList($origin, $allowed)) {
      header('Access-Control-Allow-Origin: ' . $origin);

      return true;
    }

    return false;
  }

  /**
   * @param list<string> $allowed
   */
  private static function originMatchesAllowList(string $origin, array $allowed): bool
  {
    $originHost = parse_url($origin, PHP_URL_HOST) ?: '';
    $originScheme = parse_url($origin, PHP_URL_SCHEME) ?: 'https';

    foreach ($allowed as $entry) {
      $entry = trim((string) $entry);
      if ($entry === '') {
        continue;
      }

      if (str_starts_with($entry, 'http://') || str_starts_with($entry, 'https://')) {
        if (rtrim($entry, '/') === rtrim($origin, '/')) {
          return true;
        }
        continue;
      }

      if (strcasecmp($entry, $originHost) === 0) {
        return true;
      }

      if (str_starts_with($entry, '*.') && $originHost !== '') {
        $suffix = substr($entry, 1);
        if (str_ends_with($originHost, $suffix)) {
          return true;
        }
      }
    }

    return false;
  }
}
