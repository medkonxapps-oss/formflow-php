<?php

declare(strict_types=1);

/**
 * Application bootstrap — loads configuration and registers autoloading.
 */

$configPath = dirname(__DIR__) . '/config.php';

if (!is_readable($configPath)) {
    header('Location: /install/', true, 302);
    exit;
}

/** @var array<string, mixed> $config */
$config = require $configPath;

define('FORMFLOW_ROOT', dirname(__DIR__));
define('FORMFLOW_DEBUG', !empty($config['app']['debug']));

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

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

use FormFlow\Auth;
use FormFlow\ErrorHandler;
use FormFlow\Session;

ErrorHandler::register();

Session::start($config);

$auth = new Auth($config);
$auth->attemptRememberLogin(
    (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
);

return $config;
