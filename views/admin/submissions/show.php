<?php

declare(strict_types=1);

use FormFlow\FormRepository;
use FormFlow\SubmissionRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$formId = (int) ($routeParams['formId'] ?? 0);
$submissionId = (int) ($routeParams['id'] ?? 0);

$forms = new FormRepository($config);
$form = $forms->findForUser($formId, $userId);

if ($form === null) {
    http_response_code(404);
    echo '<p>Form not found.</p>';
    return;
}

$subs = new SubmissionRepository($config);
$submission = $subs->findForForm($submissionId, $formId, $userId);

if ($submission === null) {
    http_response_code(404);
    echo '<p>Submission not found.</p>';
    return;
}

$subs->markRead($submissionId, $formId, $userId);
$data = is_array($submission['data'] ?? null) ? $submission['data'] : [];
$fields = is_array($form['fields']) ? $form['fields'] : [];
?>

<div class="mb-6">
    <a href="/admin/forms/<?= $formId ?>/submissions" class="text-sm text-zinc-500 hover:underline">← Back to inbox</a>
    <h2 class="mt-2 text-2xl font-semibold text-zinc-900">Submission #<?= (int) $submission['id'] ?></h2>
    <p class="text-sm text-zinc-500"><?= e((string) $form['name']) ?> · <?= e((string) $submission['created_at']) ?></p>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">Field values</h3>
            <dl class="space-y-4">
                <?php foreach ($fields as $field): ?>
                    <?php if (!is_array($field)) continue;
                    $type = (string) ($field['type'] ?? '');
                    if (in_array($type, ['heading', 'paragraph'], true)) continue;
                    $fid = (string) ($field['id'] ?? '');
                    $val = $data[$fid] ?? '';
                    if (is_array($val)) $val = implode(', ', $val);
                    ?>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500"><?= e((string) ($field['label'] ?? $fid)) ?></dt>
                        <dd class="mt-1 whitespace-pre-wrap text-sm text-zinc-900"><?= e((string) $val) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500">Metadata</h3>
            <dl class="space-y-3 text-sm">
                <div><dt class="text-xs text-zinc-500">IP address</dt><dd class="font-mono"><?= e((string) ($submission['ip_address'] ?? '—')) ?></dd></div>
                <div><dt class="text-xs text-zinc-500">User agent</dt><dd class="break-all text-xs"><?= e((string) ($submission['user_agent'] ?? '—')) ?></dd></div>
                <div><dt class="text-xs text-zinc-500">Referrer</dt><dd class="break-all text-xs"><?= e((string) ($submission['referrer'] ?? '—')) ?></dd></div>
                <div><dt class="text-xs text-zinc-500">Submitted</dt><dd><?= e((string) $submission['created_at']) ?></dd></div>
                <div class="flex gap-2 pt-2">
                    <?php if (!empty($submission['is_starred'])): ?><span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Starred</span><?php endif; ?>
                    <?php if (!empty($submission['is_spam'])): ?><span class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800">Spam</span><?php endif; ?>
                    <?php if (!empty($submission['is_read'])): ?><span class="rounded bg-zinc-100 px-2 py-0.5 text-xs">Read</span><?php endif; ?>
                </div>
            </dl>
        </div>
    </div>
</div>
