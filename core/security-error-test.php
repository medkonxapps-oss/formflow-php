<?php

declare(strict_types=1);

define('FORMFLOW_DEBUG', false);
require __DIR__ . '/ErrorHandler.php';

FormFlow\ErrorHandler::register();
FormFlow\ErrorHandler::handleException(new Exception('audit-test'));
