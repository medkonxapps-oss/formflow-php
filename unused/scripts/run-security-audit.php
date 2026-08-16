<?php

declare(strict_types=1);

/**
 * Automated security checks for SECURITY_AUDIT.md generation.
 *
 * Usage: php unused/scripts/run-security-audit.php [base_url]
 */

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');

$results = [];

function check(string $name, bool $pass, string $detail = ''): void
{
    global $results;
    $results[] = ['name' => $name, 'pass' => $pass, 'detail' => $detail];
    $status = $pass ? 'PASS' : 'FAIL';
    echo "[{$status}] {$name}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

function http(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $code, 'raw' => is_string($raw) ? $raw : ''];
}

// 1. Blocked paths
foreach (['/core/bootstrap.php', '/includes/PHPMailer/src/PHPMailer.php', '/config.php', '/uploads/.htaccess'] as $path) {
    $r = http('GET', $base . $path);
    check("Blocked path {$path}", $r['code'] === 403 || $r['code'] === 404, "HTTP {$r['code']}");
}

// 2. Login rate limit (with CSRF + session cookie)
$cookieFile = tempnam(sys_get_temp_dir(), 'ffcookie');
$loginPage = http('GET', $base . '/login');
preg_match('/name="_csrf" value="([^"]+)"/', $loginPage['raw'], $m);
$csrf = $m[1] ?? '';
preg_match_all('/^Set-Cookie:\s*([^\r\n]+)/mi', $loginPage['raw'], $cookies);
$cookieHeader = implode('; ', array_map(fn ($c) => explode(';', $c)[0], $cookies[1] ?? []));

$limited = false;
$email = 'ratelimit-' . time() . '@example.com';
for ($i = 0; $i < 8; $i++) {
    $body = http_build_query(['email' => $email, 'password' => 'WrongPassword123!', '_csrf' => $csrf, 'remember' => '']);
    $ch = curl_init($base . '/login');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => ['Cookie: ' . $cookieHeader],
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $raw = (string) curl_exec($ch);
    curl_close($ch);
    if (str_contains($raw, 'Too many failed attempts') || str_contains($raw, 'Please wait')) {
        $limited = true;
        break;
    }
}
@unlink($cookieFile);

// 2b. Login backoff via Auth layer (separate process — avoids session/header conflicts)
$phpBin = PHP_BINARY ?: 'php';
$backoffOut = shell_exec('"' . $phpBin . '" "' . str_replace('\\', '/', __DIR__) . '/security-auth-backoff-test.php" 2>nul');
$backoffActive = is_string($backoffOut) && str_contains($backoffOut, 'BACKOFF_OK');
check('Login brute-force backoff (Auth layer)', $backoffActive, $backoffActive ? 'backoff after 6 failures' : 'no backoff');

// 3. Submit rate limit
$submitLimited = false;
for ($i = 0; $i < 15; $i++) {
    $r = http('POST', $base . '/submit/contact-us', [
        'Content-Type: application/json',
        'Accept: application/json',
    ], json_encode(['f_name' => 'T', 'f_email' => 't@t.com', 'f_message' => '1234567890']));
    if ($r['code'] === 429) {
        $submitLimited = true;
        break;
    }
}
check('Submit endpoint rate limit (429)', $submitLimited, $submitLimited ? 'HTTP 429 received' : 'no 429 in 15 requests');

// 4. Error leakage (health with invalid action - use non-existent route)
$r = http('GET', $base . '/admin/forms/999999/edit');
$noLeak = !str_contains($r['raw'], 'Stack trace') && !str_contains($r['raw'], 'PDOException') && !str_contains($r['raw'], 'Fatal error');
check('No stack trace on admin 404/redirect', $noLeak, 'HTTP ' . $r['code']);

// 5. Session cookie flags (login page Set-Cookie)
$r = http('GET', $base . '/login');
$hasHttpOnly = stripos($r['raw'], 'httponly') !== false;
$hasSameSite = stripos($r['raw'], 'samesite=strict') !== false || stripos($r['raw'], 'SameSite=Strict') !== false;
check('Session cookie HttpOnly', $hasHttpOnly);
check('Session cookie SameSite=Strict', $hasSameSite);

// 6. Production error handler (CLI subprocess)
$errTest = shell_exec('"' . $phpBin . '" "' . str_replace('\\', '/', __DIR__) . '/security-error-test.php" 2>nul');
$errOk = false;
if (is_string($errTest) && preg_match('/<!DOCTYPE.+$/s', $errTest, $htmlMatch)) {
    $htmlOnly = $htmlMatch[0];
    $errOk = str_contains($htmlOnly, 'An unexpected error occurred') && !str_contains($htmlOnly, 'audit-test');
}
check('Error handler hides exception details', $errOk, $errOk ? 'generic message only' : 'possible leak');

echo PHP_EOL . 'Done. ' . count(array_filter($results, fn ($x) => $x['pass'])) . '/' . count($results) . ' passed.' . PHP_EOL;
