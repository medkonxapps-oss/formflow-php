<?php

declare(strict_types=1);

/**
 * Simulate installer completion on an extracted release tree (CLI smoke test).
 *
 * Usage: php unused/scripts/simulate-install-test.php /path/to/extracted/formflow
 */

$extracted = rtrim($argv[1] ?? '', '/\\');
if ($extracted === '' || !is_dir($extracted)) {
    fwrite(STDERR, "Usage: php unused/scripts/simulate-install-test.php <extracted-root>\n");
    exit(1);
}

$devConfig = dirname(__DIR__, 2) . '/config.php';
if (!is_readable($devConfig)) {
    echo "SKIP: simulate-install-test (no local config.php for DB credentials)\n";
    exit(0);
}

/** @var array<string, mixed> $dev */
$dev = require $devConfig;
$db = $dev['database'] ?? [];
$testDbName = 'formflow_install_verify_' . date('YmdHis');

define('FORMFLOW_ROOT', $extracted);

spl_autoload_register(static function (string $class) use ($extracted): void {
    if (str_starts_with($class, 'FormFlow\\')) {
        $file = $extracted . '/core/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (is_readable($file)) {
            require $file;
        }
    }
});

require $extracted . '/core/helpers.php';

use FormFlow\Auth;
use FormFlow\ConfigWriter;
use FormFlow\Database;
use FormFlow\InstallState;
use FormFlow\Migrator;
use FormFlow\Session;
use FormFlow\TemplateSeeder;

Session::start(['app' => ['session_secure' => false]]);

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'] ?? '127.0.0.1', (int) ($db['port'] ?? 3306)),
    (string) ($db['user'] ?? 'root'),
    (string) ($db['password'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
$pdo->exec("CREATE DATABASE `{$testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

InstallState::put([
    'requirements_passed' => true,
    'database_configured' => true,
    'database' => [
        'host' => $db['host'] ?? '127.0.0.1',
        'port' => (int) ($db['port'] ?? 3306),
        'name' => $testDbName,
        'user' => $db['user'] ?? 'root',
        'password' => $db['password'] ?? '',
        'charset' => 'utf8mb4',
        'prefix' => '',
    ],
    'smtp_configured' => true,
    'smtp_skipped' => true,
    'smtp' => [
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from_email' => 'noreply@localhost',
        'from_name' => 'FormFlow',
    ],
    'admin_created' => true,
    'admin' => [
        'name' => 'Release Test Admin',
        'email' => 'release-test@formflow.local',
        'password' => 'SecurePassword123!',
    ],
    'settings_saved' => true,
    'settings' => [
        'site_name' => 'FormFlow Release Test',
        'timezone' => 'UTC',
        'locale' => 'en',
    ],
    'app_secret' => base64_encode(random_bytes(32)),
]);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';

$config = InstallState::buildConfig($extracted);
if (!ConfigWriter::write($extracted, $config)) {
    fwrite(STDERR, "FAIL: Could not write config.php\n");
    exit(1);
}

Database::resetInstance();
$migrator = new Migrator($config);
$migrator->runAll();
Database::resetInstance();
TemplateSeeder::run($config);

$auth = new Auth($config);
$reg = $auth->register(
    'Release Test Admin',
    'release-test@formflow.local',
    'SecurePassword123!',
    'admin'
);

if (!$reg['success']) {
    fwrite(STDERR, 'FAIL: Admin registration — ' . ($reg['error'] ?? 'unknown') . "\n");
    exit(1);
}

$login = $auth->attemptLogin('release-test@formflow.local', 'SecurePassword123!', '127.0.0.1', 'release-test');
if (!$login['success']) {
    fwrite(STDERR, "FAIL: Login after install\n");
    exit(1);
}

// Cleanup test DB and generated config in temp tree
@unlink($extracted . '/config.php');
$pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");

echo "OK: Simulated full install (migrations + admin + login) on fresh DB `{$testDbName}`.\n";
exit(0);
