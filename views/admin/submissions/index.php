<?php

declare(strict_types=1);

use FormFlow\FormRepository;
use FormFlow\SubmissionRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$formId = (int) ($routeParams['formId'] ?? 0);
$forms = new FormRepository($config);
$form = $forms->findForUser($formId, $userId);

if ($form === null) {
    http_response_code(404);
    echo '<p>Form not found.</p>';
    return;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'is_read' => $_GET['is_read'] ?? '',
    'is_starred' => $_GET['is_starred'] ?? '',
    'is_spam' => isset($_GET['is_spam']) ? (int) $_GET['is_spam'] : null,
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to' => (string) ($_GET['date_to'] ?? ''),
];

$subs = new SubmissionRepository($config);
$result = $subs->listForForm($formId, $userId, $filters, $page, 20);
$items = $result['items'];
$total = $result['total'];
$totalPages = (int) max(1, ceil($total / $result['per_page']));

$inputFields = array_values(array_filter(
    is_array($form['fields']) ? $form['fields'] : [],
    fn ($f) => is_array($f) && !in_array($f['type'] ?? '', ['heading', 'paragraph'], true)
));
$displayFields = array_slice($inputFields, 0, 4);
$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);

$queryBase = http_build_query(array_filter([
    'q' => $filters['q'] ?: null,
    'is_read' => $filters['is_read'] !== '' ? $filters['is_read'] : null,
    'is_starred' => $filters['is_starred'] !== '' ? $filters['is_starred'] : null,
    'is_spam' => $filters['is_spam'] !== null ? $filters['is_spam'] : null,
    'date_from' => $filters['date_from'] ?: null,
    'date_to' => $filters['date_to'] ?: null,
]));
?>

<div class="mb-6">
    <a href="/admin/forms" class="text-sm text-zinc-500 hover:underline">← Forms</a>
    <h2 class="mt-2 text-2xl font-semibold text-zinc-900"><?= e((string) $form['name']) ?></h2>
    <p class="text-sm text-zinc-500"><?= (int) $total ?> submission(s)</p>
</div>

<form method="get" class="mb-4 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
    <input type="hidden" name="form_id" value="<?= $formId ?>">
    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search…" class="h-9 rounded-md border border-zinc-300 px-3 text-sm lg:col-span-2">
    <select name="is_read" class="h-9 rounded-md border border-zinc-300 px-2 text-sm">
        <option value="">All read status</option>
        <option value="0" <?= $filters['is_read'] === '0' ? 'selected' : '' ?>>Unread</option>
        <option value="1" <?= $filters['is_read'] === '1' ? 'selected' : '' ?>>Read</option>
    </select>
    <select name="is_starred" class="h-9 rounded-md border border-zinc-300 px-2 text-sm">
        <option value="">All starred</option>
        <option value="1" <?= $filters['is_starred'] === '1' ? 'selected' : '' ?>>Starred</option>
        <option value="0" <?= $filters['is_starred'] === '0' ? 'selected' : '' ?>>Not starred</option>
    </select>
    <select name="is_spam" class="h-9 rounded-md border border-zinc-300 px-2 text-sm">
        <option value="">Inbox + spam</option>
        <option value="0" <?= $filters['is_spam'] === 0 ? 'selected' : '' ?>>Inbox only</option>
        <option value="1" <?= $filters['is_spam'] === 1 ? 'selected' : '' ?>>Spam only</option>
    </select>
    <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>" class="h-9 rounded-md border border-zinc-300 px-2 text-sm">
    <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>" class="h-9 rounded-md border border-zinc-300 px-2 text-sm">
    <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
        <button type="submit" class="h-9 rounded-md bg-zinc-900 px-4 text-sm text-white">Filter</button>
        <a href="/admin/forms/<?= $formId ?>/submissions/export?<?= e($queryBase) ?>" class="inline-flex h-9 items-center rounded-md border border-zinc-300 px-4 text-sm hover:bg-zinc-50">Export CSV</a>
    </div>
</form>

<?php if ($isEditor): ?>
<form method="post" action="/admin/forms/submissions/bulk" id="bulk-form">
    <?= csrf_field() ?>
    <input type="hidden" name="form_id" value="<?= $formId ?>">
    <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
    <div class="mb-3 flex flex-wrap gap-2">
        <select name="bulk_action" required class="h-9 rounded-md border border-zinc-300 px-2 text-sm">
            <option value="">Bulk action…</option>
            <option value="read">Mark read</option>
            <option value="unread">Mark unread</option>
            <option value="star">Star</option>
            <option value="unstar">Unstar</option>
            <option value="spam">Mark spam</option>
            <option value="not_spam">Not spam</option>
            <option value="delete">Delete</option>
        </select>
        <button type="submit" class="h-9 rounded-md border border-zinc-300 px-4 text-sm hover:bg-zinc-50">Apply</button>
    </div>
<?php endif; ?>

<div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-zinc-200 text-sm">
        <thead class="bg-zinc-50">
            <tr>
                <?php if ($isEditor): ?><th class="px-3 py-3"><input type="checkbox" onclick="document.querySelectorAll('.sub-cb').forEach(c=>c.checked=this.checked)"></th><?php endif; ?>
                <th class="px-3 py-3 text-left font-medium text-zinc-600">Date</th>
                <?php foreach ($displayFields as $field): ?>
                    <th class="px-3 py-3 text-left font-medium text-zinc-600"><?= e((string) ($field['label'] ?? $field['id'] ?? '')) ?></th>
                <?php endforeach; ?>
                <th class="px-3 py-3 text-left font-medium text-zinc-600">Flags</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100">
            <?php if ($items === []): ?>
                <tr><td colspan="10" class="px-4 py-8 text-center text-zinc-500">No submissions found.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $sub): ?>
                <?php $data = is_array($sub['data'] ?? null) ? $sub['data'] : []; ?>
                <tr class="hover:bg-zinc-50 <?= empty($sub['is_read']) ? 'bg-blue-50/30' : '' ?>">
                    <?php if ($isEditor): ?>
                        <td class="px-3 py-3"><input type="checkbox" class="sub-cb" name="submission_ids[]" value="<?= (int) $sub['id'] ?>" form="bulk-form"></td>
                    <?php endif; ?>
                    <td class="px-3 py-3 whitespace-nowrap">
                        <a href="/admin/forms/<?= $formId ?>/submissions/<?= (int) $sub['id'] ?>" class="font-medium text-zinc-900 hover:underline">
                            <?= e((string) $sub['created_at']) ?>
                        </a>
                    </td>
                    <?php foreach ($displayFields as $field): ?>
                        <?php
                        $fid = (string) ($field['id'] ?? '');
                        $val = $data[$fid] ?? '';
                        if (is_array($val)) {
                            $val = implode(', ', $val);
                        }
                        ?>
                        <td class="max-w-[200px] truncate px-3 py-3 text-zinc-700"><?= e((string) $val) ?></td>
                    <?php endforeach; ?>
                    <td class="px-3 py-3 text-xs">
                        <?php if (!empty($sub['is_starred'])): ?><span class="text-amber-600">★</span><?php endif; ?>
                        <?php if (!empty($sub['is_spam'])): ?><span class="text-red-600">spam</span><?php endif; ?>
                        <?php if (empty($sub['is_read'])): ?><span class="text-blue-600">new</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($isEditor): ?></form><?php endif; ?>

<?php if ($totalPages > 1): ?>
    <div class="mt-4 flex justify-center gap-2">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="/admin/forms/<?= $formId ?>/submissions?<?= e($queryBase . ($queryBase ? '&' : '') . 'page=' . $p) ?>"
               class="rounded px-3 py-1 text-sm <?= $p === $page ? 'bg-zinc-900 text-white' : 'border border-zinc-200 hover:bg-zinc-50' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
