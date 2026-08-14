<?php

declare(strict_types=1);

/**
 * Dev-only: create the first admin user.
 *
 * Usage: php core/seed-admin.php "Admin User" admin@example.com "your-secure-password"
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$config = require __DIR__ . '/bootstrap.php';

use FormFlow\Auth;
use FormFlow\Migrator;

$args = array_slice($argv, 1);
if (count($args) < 3) {
    fwrite(STDERR, "Usage: php core/seed-admin.php \"Name\" email@example.com \"password\"\n");
    exit(1);
}

[$name, $email, $password] = $args;

try {
    $migrator = new Migrator($config);
    $migrator->runAll();
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration warning: ' . $e->getMessage() . PHP_EOL);
}

$auth = new Auth($config);
$result = $auth->register($name, $email, $password, 'admin', true);

if (!$result['success']) {
    fwrite(STDERR, 'Failed: ' . ($result['error'] ?? 'unknown') . PHP_EOL);
    exit(1);
}

echo "Admin created (user_id: {$result['user_id']})\n";
echo "Sign in at /login\n";
