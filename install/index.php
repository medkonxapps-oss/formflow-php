<?php

declare(strict_types=1);

/**
 * Installer entry point — locked when config.php exists (PRD §5.1 / §6.5).
 */

$configPath = dirname(__DIR__) . '/config.php';

if (is_readable($configPath)) {
    header('Location: /login', true, 302);
    exit;
}

require __DIR__ . '/bootstrap.php';

use FormFlow\InstallController;

$controller = new InstallController();
$controller->dispatch();
