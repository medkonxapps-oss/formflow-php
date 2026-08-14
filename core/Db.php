<?php

declare(strict_types=1);

namespace FormFlow;

/**
 * Resolves database table names with optional prefix from config.
 */
class Db
{
  /**
   * @param array<string, mixed> $config
   */
  public static function table(string $name, array $config = []): string
  {
    $prefix = '';

    if ($config !== []) {
      $prefix = (string) ($config['database']['prefix'] ?? '');
    } elseif (defined('FORMFLOW_CONFIG') && is_array(FORMFLOW_CONFIG)) {
      $prefix = (string) (FORMFLOW_CONFIG['database']['prefix'] ?? '');
    }

    return $prefix . $name;
  }
}
