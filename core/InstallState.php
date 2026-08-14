<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Installer wizard session state.
 */
class InstallState
{
  private const SESSION_KEY = 'install_wizard';

  public const STEPS = [
    1 => ['slug' => 'requirements', 'title' => 'Requirements'],
    2 => ['slug' => 'database', 'title' => 'Database'],
    3 => ['slug' => 'smtp', 'title' => 'SMTP'],
    4 => ['slug' => 'admin', 'title' => 'Admin Account'],
    5 => ['slug' => 'settings', 'title' => 'Site Settings'],
    6 => ['slug' => 'complete', 'title' => 'Complete'],
  ];

  public static function get(): array
  {
    return $_SESSION[self::SESSION_KEY] ?? [];
  }

  public static function put(array $data): void
  {
    $_SESSION[self::SESSION_KEY] = array_merge(self::get(), $data);
  }

  public static function maxAllowedStep(): int
  {
    $state = self::get();

    if (empty($state['requirements_passed'])) {
      return 1;
    }
    if (empty($state['database_configured'])) {
      return 2;
    }
    if (empty($state['smtp_configured']) && empty($state['smtp_skipped'])) {
      return 3;
    }
    if (empty($state['admin_created'])) {
      return 4;
    }
    if (empty($state['settings_saved'])) {
      return 5;
    }

    return 6;
  }

  public static function stepFromSlug(string $slug): int
  {
    foreach (self::STEPS as $num => $step) {
      if ($step['slug'] === $slug) {
        return $num;
      }
    }

    return 1;
  }

  public static function slugFromStep(int $step): string
  {
    return self::STEPS[$step]['slug'] ?? 'requirements';
  }

  public static function guardStep(int $requestedStep): void
  {
    if ($requestedStep > self::maxAllowedStep()) {
      $slug = self::slugFromStep(self::maxAllowedStep());
      redirect('/install/' . ($slug === 'requirements' ? '' : $slug));
    }
  }

  /**
   * @return array<string, mixed>
   */
  public static function buildConfig(string $rootPath): array
  {
    $state = self::get();
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $baseUrl = $scheme . '://' . $host;

    return [
      'app' => [
        'name' => (string) ($state['settings']['site_name'] ?? 'FormFlow'),
        'url' => $baseUrl,
        'secret' => (string) ($state['app_secret'] ?? base64_encode(random_bytes(32))),
        'env' => 'production',
        'debug' => false,
        'session_secure' => $scheme === 'https',
        'timezone' => (string) ($state['settings']['timezone'] ?? 'UTC'),
        'locale' => (string) ($state['settings']['locale'] ?? 'en'),
      ],
      'database' => $state['database'] ?? [],
      'smtp' => $state['smtp'] ?? [
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_email' => '',
        'from_name' => (string) ($state['settings']['site_name'] ?? 'FormFlow'),
      ],
    ];
  }

  public static function clear(): void
  {
    unset($_SESSION[self::SESSION_KEY]);
  }
}
