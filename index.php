<?php

declare(strict_types=1);

/**
 * FormFlow front controller.
 */

$config = require __DIR__ . '/core/bootstrap.php';

use FormFlow\Router;

$router = new Router($config);
$router->dispatch();
