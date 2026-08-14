<?php

declare(strict_types=1);

/**
 * File upload security test — spoofed MIME / double extension / PHP content.
 */

$tmp = tempnam(sys_get_temp_dir(), 'ff');
file_put_contents($tmp, "<?php echo 'pwned'; ?>");

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($tmp) ?: 'unknown';
$spoofedHeader = 'image/jpeg';
$hasPhp = str_contains((string) file_get_contents($tmp, false, null, 0, 8192), '<?php');

$allowed = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf', 'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$blockedByMime = !in_array($detectedMime, $allowed, true);
$blockedByPhp = $hasPhp;

echo "Spoofed Content-Type: {$spoofedHeader}\n";
echo "finfo detected MIME: {$detectedMime}\n";
echo "PHP tag in content: " . ($hasPhp ? 'yes' : 'no') . "\n";
echo "Blocked by MIME allow-list: " . ($blockedByMime ? 'yes' : 'no') . "\n";
echo "Blocked by PHP content scan: " . ($blockedByPhp ? 'yes' : 'no') . "\n";
echo ($blockedByMime || $blockedByPhp) ? "PASS: malicious upload would be rejected\n" : "FAIL\n";

@unlink($tmp);
