<?php

declare(strict_types=1);

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__, 2) . '/core/bootstrap.php';

use FormFlow\Auth;

$auth = new Auth($config);
$email = 'backoff-audit-' . time() . '@example.com';
for ($i = 0; $i < 6; $i++) {
    $auth->attemptLogin($email, 'WrongPassword123!', '127.0.0.1', 'audit');
}
$result = $auth->attemptLogin($email, 'WrongPassword123!', '127.0.0.1', 'audit');
echo str_contains((string) ($result['error'] ?? ''), 'Too many failed attempts') ? 'BACKOFF_OK' : 'BACKOFF_FAIL';
