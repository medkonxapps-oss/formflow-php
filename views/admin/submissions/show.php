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
$notes = [];
try {
    $notes = $subs->notesFor($submissionId, $formId, $userId);
} catch (\Throwable $e) {
    $notes = [];
}

$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);
?>

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <div class="flex items-center gap-2 text-sm text-zinc-500 mb-2">
            <i data-lucide="inbox" class="h-4 w-4"></i>
            <span>Submission #<?= (int) $submission['id'] ?></span>
        </div>
        <h2 class="text-3xl font-bold tracking-tight text-zinc-900"><?= e((string) $form['name']) ?></h2>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="/admin/submissions" class="shadcn-btn shadcn-btn-outline h-9 text-xs">Back to inbox</a>
        <?php if ($isEditor): ?>
        <form method="post" action="/admin/submissions/action">
            <?= csrf_field() ?>
            <input type="hidden" name="form_id" value="<?= $formId ?>">
            <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
            <input type="hidden" name="bulk_action" value="<?= !empty($submission['is_starred']) ? 'unstar' : 'star' ?>">
            <button type="submit" class="shadcn-btn shadcn-btn-outline h-9 gap-2 text-xs">
                <i data-lucide="star" class="h-3.5 w-3.5 <?= !empty($submission['is_starred']) ? 'text-amber-500' : '' ?>"></i>
                <?= !empty($submission['is_starred']) ? 'Unstar' : 'Star' ?>
            </button>
        </form>
        <form method="post" action="/admin/submissions/action">
            <?= csrf_field() ?>
            <input type="hidden" name="form_id" value="<?= $formId ?>">
            <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
            <input type="hidden" name="bulk_action" value="<?= !empty($submission['is_spam']) ? 'not_spam' : 'spam' ?>">
            <button type="submit" class="shadcn-btn shadcn-btn-outline h-9 gap-2 text-xs">
                <?= !empty($submission['is_spam']) ? 'Not spam' : 'Mark spam' ?>
            </button>
        </form>
        <form method="post" action="/admin/submissions/action" onsubmit="return confirm('Delete this submission permanently?')">
            <?= csrf_field() ?>
            <input type="hidden" name="form_id" value="<?= $formId ?>">
            <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
            <input type="hidden" name="bulk_action" value="delete">
            <button type="submit" class="shadcn-btn shadcn-btn-outline h-9 gap-2 text-xs text-red-600 hover:bg-red-50 hover:text-red-700">
                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                Delete
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="rounded-xl border border-border bg-white shadow-sm overflow-hidden">
            <div class="border-b border-border bg-zinc-50/50 px-6 py-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-zinc-900 flex items-center gap-2">
                    <i data-lucide="list-checks" class="h-4 w-4 text-zinc-500"></i>
                    Form Response
                </h3>
            </div>
            <div class="p-0">
                <dl class="divide-y divide-zinc-100">
                    <?php if (empty($fields)): ?>
                        <div class="p-8 text-center text-zinc-500 text-sm">
                            No fields found for this response.
                        </div>
                    <?php endif; ?>
                    
                    <?php foreach ($fields as $field): ?>
                        <?php if (!is_array($field)) continue;
                        $type = (string) ($field['type'] ?? '');
                        if (in_array($type, ['heading', 'paragraph', 'html', 'video'], true)) continue;
                        $fid = (string) ($field['id'] ?? '');
                        $val = $data[$fid] ?? '';
                        if (is_array($val)) $val = implode(', ', $val);
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 px-6 py-4 hover:bg-zinc-50/50 transition-colors">
                            <dt class="text-sm font-medium text-zinc-500 md:col-span-1 flex items-start pt-0.5">
                                <?= e((string) ($field['label'] ?? $fid)) ?>
                            </dt>
                            <dd class="mt-1 text-sm text-zinc-900 md:col-span-2 md:mt-0 break-words whitespace-pre-wrap font-medium">
                                <?= $val !== '' ? e((string) $val) : '<span class="text-zinc-400 italic">No response</span>' ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-xl border border-border bg-white shadow-sm overflow-hidden">
            <div class="border-b border-border bg-zinc-50/50 px-6 py-4">
                <h3 class="text-sm font-semibold text-zinc-900 flex items-center gap-2">
                    <i data-lucide="info" class="h-4 w-4 text-zinc-500"></i>
                    Submission Details
                </h3>
            </div>
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-wider">Status</span>
                    <div class="flex gap-1.5">
                        <?php if (!empty($submission['is_spam'])): ?>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20">Spam</span>
                        <?php else: ?>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Verified</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <span class="block text-xs font-medium text-zinc-500 uppercase tracking-wider mb-1">Submitted On</span>
                    <div class="text-sm text-zinc-900 flex items-center gap-2">
                        <i data-lucide="calendar" class="h-4 w-4 text-zinc-400"></i>
                        <?= date('M d, Y', strtotime((string)$submission['created_at'])) ?> at <?= date('g:i A', strtotime((string)$submission['created_at'])) ?>
                    </div>
                </div>

                <div class="pt-4 border-t border-zinc-100">
                    <span class="block text-xs font-medium text-zinc-500 uppercase tracking-wider mb-2">Technical Meta</span>
                    <dl class="space-y-3">
                        <div class="flex flex-col gap-1">
                            <dt class="text-xs text-zinc-500 flex items-center gap-1.5"><i data-lucide="globe" class="h-3.5 w-3.5"></i> IP Address</dt>
                            <dd class="font-mono text-xs text-zinc-900 bg-zinc-50 px-2 py-1 rounded border border-zinc-100 inline-block w-fit"><?= e((string) ($submission['ip_address'] ?? 'Unknown')) ?></dd>
                        </div>
                        <div class="flex flex-col gap-1">
                            <dt class="text-xs text-zinc-500 flex items-center gap-1.5"><i data-lucide="monitor" class="h-3.5 w-3.5"></i> User Agent</dt>
                            <dd class="text-xs text-zinc-600 leading-relaxed"><?= e((string) ($submission['user_agent'] ?? 'Unknown')) ?></dd>
                        </div>
                        <?php if (!empty($submission['referrer'])): ?>
                        <div class="flex flex-col gap-1">
                            <dt class="text-xs text-zinc-500 flex items-center gap-1.5"><i data-lucide="link" class="h-3.5 w-3.5"></i> Referrer</dt>
                            <dd class="text-xs text-zinc-600 truncate" title="<?= e((string) $submission['referrer']) ?>"><?= e((string) $submission['referrer']) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-white shadow-sm overflow-hidden">
            <div class="border-b border-border bg-zinc-50/50 px-6 py-4">
                <h3 class="text-sm font-semibold text-zinc-900">Internal notes</h3>
            </div>
            <div class="p-6 space-y-3">
                <?php if ($notes === []): ?>
                    <p class="text-sm text-zinc-500">No notes yet.</p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($notes as $note): ?>
                            <li class="rounded-lg bg-zinc-50 p-3 text-sm">
                                <p class="text-zinc-800 whitespace-pre-wrap"><?= e((string) $note['body']) ?></p>
                                <p class="mt-1 text-xs text-zinc-400"><?= e((string) ($note['author'] ?? '')) ?> · <?= e((string) ($note['created_at'] ?? '')) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post" action="/admin/submissions/note" class="pt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_id" value="<?= $formId ?>">
                    <input type="hidden" name="submission_id" value="<?= $submissionId ?>">
                    <textarea name="body" required rows="3" placeholder="Add a private note…" class="w-full rounded-md border border-zinc-200 px-3 py-2 text-sm"></textarea>
                    <button type="submit" class="mt-2 shadcn-btn shadcn-btn-primary h-9 text-xs">Add note</button>
                </form>
            </div>
        </div>
    </div>
</div>
