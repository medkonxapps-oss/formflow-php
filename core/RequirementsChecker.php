<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * System requirements check for the installer (PRD §5.1 step 1).
 */
class RequirementsChecker
{
  private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'curl', 'gd'];

  /**
   * @return array{
   *   passed: bool,
   *   checks: list<array{label: string, status: string, message: string, blocking: bool}>
   * }
   */
  public static function run(string $rootPath): array
  {
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = [
      'label' => 'PHP version (8.0+)',
      'status' => $phpOk ? 'pass' : 'fail',
      'message' => 'Current: ' . PHP_VERSION,
      'blocking' => true,
    ];

    foreach (self::REQUIRED_EXTENSIONS as $ext) {
      $loaded = extension_loaded($ext);
      $checks[] = [
        'label' => 'PHP extension: ' . $ext,
        'status' => $loaded ? 'pass' : 'fail',
        'message' => $loaded ? 'Loaded' : 'Not loaded',
        'blocking' => true,
      ];
    }

    $configWritable = self::isConfigWritable($rootPath);
    $checks[] = [
      'label' => 'config.php writable',
      'status' => $configWritable ? 'pass' : 'fail',
      'message' => $configWritable
        ? 'Project root is writable'
        : 'Cannot write config.php — check directory permissions',
      'blocking' => true,
    ];

    $uploadsPath = $rootPath . '/uploads';
    $uploadsWritable = is_dir($uploadsPath) && is_writable($uploadsPath);
    $checks[] = [
      'label' => '/uploads writable',
      'status' => $uploadsWritable ? 'pass' : 'fail',
      'message' => $uploadsWritable
        ? 'Uploads directory is writable'
        : 'uploads/ must exist and be writable',
      'blocking' => true,
    ];

    $passed = true;
    foreach ($checks as $check) {
      if ($check['blocking'] && $check['status'] !== 'pass') {
        $passed = false;
        break;
      }
    }

    return ['passed' => $passed, 'checks' => $checks];
  }

  private static function isConfigWritable(string $rootPath): bool
  {
    $configFile = $rootPath . '/config.php';

    if (file_exists($configFile)) {
      return is_writable($configFile);
    }

    return is_writable($rootPath);
  }
}
