<?php

declare(strict_types=1);

/**
 * IDOR test: two users, cross-access attempts.
 *
 * Usage: php unused/scripts/security-idor-test.php
 */

/** @var array<string, mixed> $config */
$config = require dirname(__DIR__, 2) . '/core/bootstrap.php';

use FormFlow\Auth;
use FormFlow\Database;
use FormFlow\Db;
use FormFlow\FormRepository;

$db = Database::getInstance($config);
$auth = new Auth($config);

$emailA = 'idor-a@formflow.test';
$emailB = 'idor-b@formflow.test';
$password = 'SecurePassword123!';

foreach ([[$emailA, 'User A'], [$emailB, 'User B']] as [$email, $name]) {
    $exists = $db->fetchOne('SELECT id FROM ' . Db::table('users', $config) . ' WHERE email = ?', [$email]);
    if ($exists === null) {
        $auth->register($name, $email, $password, 'admin');
        echo "Created {$email}\n";
    }
}

$userA = $db->fetchOne('SELECT id FROM ' . Db::table('users', $config) . ' WHERE email = ?', [$emailA]);
$userB = $db->fetchOne('SELECT id FROM ' . Db::table('users', $config) . ' WHERE email = ?', [$emailB]);
$idA = (int) ($userA['id'] ?? 0);
$idB = (int) ($userB['id'] ?? 0);

$formsA = new FormRepository($config);
$formResult = $formsA->create($idA, ['name' => 'IDOR Test Form A', 'fields' => [], 'settings' => []]);
$formIdA = (int) ($formResult['form_id'] ?? 0);

$matrix = [
    ['Form edit (B→A form)', $formsA->findForUser($formIdA, $idB) === null],
    ['Form list scope', count(array_filter($formsA->listForUser($idB), fn ($f) => (int) $f['id'] === $formIdA)) === 0],
];

$tblSubs = Db::table('submissions', $config);
$db->query(
    "INSERT INTO {$tblSubs} (form_id, data_json, ip_address, is_spam, is_read, is_starred, created_at)
     VALUES (?, ?, '127.0.0.1', 0, 0, 0, UTC_TIMESTAMP())",
    [$formIdA, '{"f_name":"secret"}']
);
$subId = (int) $db->pdo()->lastInsertId();

$subsRepo = new \FormFlow\SubmissionRepository($config);
$matrix[] = ['Submission view (B→A)', $subsRepo->findForForm($subId, $formIdA, $idB) === null];
$matrix[] = ['Submission list (B→A form)', $subsRepo->listForForm($formIdA, $idB)['total'] === 0];

$tblFiles = Db::table('submission_files', $config);
$tblForms = Db::table('forms', $config);
$tblSubs = Db::table('submissions', $config);
$db->query(
    "INSERT INTO {$tblFiles} (submission_id, field_name, original_filename, stored_path, mime_type, size, created_at)
     VALUES (?, 'f_file', 'test.txt', 'uploads/1/nonexistent.txt', 'text/plain', 0, UTC_TIMESTAMP())",
    [$subId]
);
$fileId = (int) $db->pdo()->lastInsertId();
$owned = $db->fetchOne(
    "SELECT sf.id FROM {$tblFiles} sf
     INNER JOIN {$tblSubs} s ON s.id = sf.submission_id
     INNER JOIN {$tblForms} f ON f.id = s.form_id
     WHERE sf.id = ? AND sf.submission_id = ? AND f.user_id = ?
     LIMIT 1",
    [$fileId, $subId, $idB]
);
$matrix[] = ['File download auth (B→A file)', $owned === null];

$apiRepo = new \FormFlow\ApiKeyRepository($config);
$keyA = $apiRepo->generate($idA, 'A key');
$keyB = $apiRepo->listForUser($idB);
$matrix[] = ['API keys isolated', !array_filter($keyB, fn ($k) => str_starts_with((string) ($keyA['raw_key'] ?? ''), 'ff_'))];

echo "\nIDOR Matrix (logged in as User B accessing User A resources):\n";
echo str_repeat('-', 60) . "\n";
$allPass = true;
foreach ($matrix as [$label, $blocked]) {
    $status = $blocked ? 'BLOCKED (expected)' : 'LEAK!';
    if (!$blocked) {
        $allPass = false;
    }
    echo sprintf("%-35s %s\n", $label, $status);
}
echo str_repeat('-', 60) . "\n";
echo $allPass ? "All IDOR checks passed.\n" : "IDOR VULNERABILITIES FOUND.\n";
