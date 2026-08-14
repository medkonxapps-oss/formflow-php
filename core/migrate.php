<?php

declare(strict_types=1);

/**
 * CLI migration runner.
 *
 * Usage: php core/migrate.php
 */

$config = require __DIR__ . '/bootstrap.php';

use FormFlow\Migrator;
use FormFlow\TemplateSeeder;

try {
  $migrator = new Migrator($config);
  $applied = $migrator->runAll();

  echo "Migrations applied:\n";
  foreach ($applied as $file) {
    echo "  - {$file}\n";
  }

  $seeded = TemplateSeeder::run($config);
  echo "Templates seeded: {$seeded} new\n";
} catch (Throwable $e) {
  fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
  exit(1);
}
