<?php

declare(strict_types=1);

/**
 * Seeds bundled form templates (run after 005_templates_api_keys.sql).
 *
 * Usage: php core/seed-templates.php
 */

/** @var array<string, mixed> $config */
$config = require __DIR__ . '/bootstrap.php';

use FormFlow\TemplateSeeder;

$count = TemplateSeeder::run($config);
echo "Template seed complete ({$count} inserted).\n";
