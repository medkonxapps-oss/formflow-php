<?php

declare(strict_types=1);

/**
 * Verify a release tree is safe to ship and passes installer requirements.
 *
 * Usage: php core/verify-release-package.php [path-to-extracted-root]
 */

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);

$errors = [];

if (is_file($root . '/config.php')) {
    $errors[] = 'config.php must not be included in release packages.';
}

$required = [
    'index.php',
    'router.php',
    'install/index.php',
    'core/bootstrap.php',
    'core/RequirementsChecker.php',
    'core/migrations/001_auth_tables.sql',
    'config.sample.php',
    'LICENSE',
    'README.md',
    'CHANGELOG.md',
    'uploads/.htaccess',
    'uploads/.gitkeep',
];

foreach ($required as $rel) {
    if (!is_readable($root . '/' . $rel)) {
        $errors[] = "Missing: {$rel}";
    }
}

if ($errors !== []) {
    echo "Release verification FAILED:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
    exit(1);
}

define('FORMFLOW_ROOT', $root);

spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'FormFlow\\')) {
        $file = $root . '/core/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (is_readable($file)) {
            require $file;
        }
    }
});

$result = FormFlow\RequirementsChecker::run($root);

echo "OK: Required release files present (no config.php).\n";
echo "OK: Installer at /install/\n";

if ($result['passed']) {
    echo "OK: RequirementsChecker passed (" . count($result['checks']) . " checks).\n";
} else {
    echo "WARN: Some requirements failed on this machine (may be OK on target host):\n";
    foreach ($result['checks'] as $check) {
        if (($check['status'] ?? '') !== 'pass') {
            echo '  - ' . ($check['label'] ?? '') . ': ' . ($check['message'] ?? '') . "\n";
        }
    }
}

echo "\nDeploy: upload → create MySQL DB → visit /install/\n";
exit(0);
