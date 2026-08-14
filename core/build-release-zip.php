<?php

declare(strict_types=1);

/**
 * Build a GitHub release ZIP (excludes config.php and local uploads).
 *
 * Usage: php core/build-release-zip.php
 */

$root = dirname(__DIR__);
$version = '1.0.0';
$zipName = "formflow-{$version}.zip";
$zipPath = $root . DIRECTORY_SEPARATOR . $zipName;

$excludeDirs = ['.git', 'node_modules', 'vendor'];
$excludeFiles = ['config.php', $zipName];
$excludePatterns = ['/formflow-.*\.zip$/'];

if (is_file($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot create {$zipPath}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $path = $file->getPathname();
    $rel = substr($path, strlen($root) + 1);
    $rel = str_replace('\\', '/', $rel);

    if ($rel === '' || str_starts_with($rel, '.git/')) {
        continue;
    }

    foreach ($excludeFiles as $ex) {
        if ($rel === $ex) {
            continue 2;
        }
    }

    foreach ($excludePatterns as $pattern) {
        if (preg_match($pattern, $rel)) {
            continue 2;
        }
    }

    // Skip upload contents but keep .htaccess and .gitkeep
    if (str_starts_with($rel, 'uploads/') && $rel !== 'uploads/.htaccess' && $rel !== 'uploads/.gitkeep') {
        if ($file->isFile()) {
            continue;
        }
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($path, $rel);
    }
}

$zip->close();

echo "Created: {$zipPath}\n";
echo 'Size: ' . round(filesize($zipPath) / 1024, 1) . " KB\n";

// Extract to temp and verify
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'formflow-release-verify-' . time();
mkdir($temp);
$zipVerify = new ZipArchive();
$zipVerify->open($zipPath);
$zipVerify->extractTo($temp);
$zipVerify->close();

passthru('"' . PHP_BINARY . '" "' . $root . '/core/verify-release-package.php" "' . $temp . '"', $code);

// Optional: simulate install if local config.php exists (dev machine only)
$configPath = $root . '/config.php';
if ($code === 0 && is_readable($configPath)) {
    passthru('"' . PHP_BINARY . '" "' . $root . '/core/simulate-install-test.php" "' . $temp . '"', $installCode);
    $code = $installCode !== 0 ? $installCode : $code;
}

// Cleanup temp
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($temp, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $f) {
    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}
rmdir($temp);

exit($code);
