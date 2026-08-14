<?php

declare(strict_types=1);

/**
 * Installer bootstrap — no config.php required.
 */

define('FORMFLOW_ROOT', dirname(__DIR__));
define('FORMFLOW_DEBUG', true);

date_default_timezone_set('UTC');

spl_autoload_register(static function (string $class): void {
    $prefix = 'FormFlow\\';
    $baseDir = FORMFLOW_ROOT . '/core/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($file)) {
        require $file;
    }
});

require FORMFLOW_ROOT . '/core/helpers.php';

use FormFlow\Session;

Session::start([
    'app' => [
        'env' => 'local',
        'session_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ],
]);
