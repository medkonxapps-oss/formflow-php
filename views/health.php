<?php

declare(strict_types=1);

use FormFlow\Database;

/**
 * Health / status endpoint — reused by the Phase 2 installer system check.
 */

$requiredExtensions = [
    'pdo_mysql',
    'mbstring',
    'openssl',
    'curl',
    'gd',
];

$extensionChecks = [];
foreach ($requiredExtensions as $ext) {
    $extensionChecks[$ext] = extension_loaded($ext);
}

$phpVersion = PHP_VERSION;
$phpVersionOk = version_compare($phpVersion, '7.4.0', '>=');

$dbResult = ['connected' => false, 'error' => 'pdo_mysql extension not loaded'];

if ($extensionChecks['pdo_mysql']) {
    $dbResult = Database::testConnection($config);
}

$allExtensionsOk = !in_array(false, $extensionChecks, true);
$healthy = $phpVersionOk && $allExtensionsOk && $dbResult['connected'];

$payload = [
    'status' => $healthy ? 'ok' : 'error',
    'timestamp' => gmdate('c'),
    'php' => [
        'version' => $phpVersion,
        'ok' => $phpVersionOk,
        'required' => '7.4.0+',
    ],
    'extensions' => $extensionChecks,
    'database' => [
        'connected' => $dbResult['connected'],
        'error' => $dbResult['error'],
    ],
];

http_response_code($healthy ? 200 : 503);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
