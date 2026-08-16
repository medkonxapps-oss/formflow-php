<?php

declare(strict_types=1);

use FormFlow\FormRepository;
use FormFlow\SubmissionRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$filters = [
    'form_id' => (int) ($_GET['form_id'] ?? 0),
    'q' => trim((string) ($_GET['q'] ?? '')),
    'is_read' => $_GET['is_read'] ?? '',
    'is_starred' => $_GET['is_starred'] ?? '',
    'is_spam' => isset($_GET['is_spam']) && $_GET['is_spam'] !== '' ? (int) $_GET['is_spam'] : null,
];

$subs = new SubmissionRepository($config);
$result = $subs->listForUser($userId, $filters, $page, 20);
$items = $result['items'];
$total = $result['total'];
$totalPages = (int) max(1, ceil($total / $result['per_page']));
$forms = (new FormRepository($config))->listForUser($userId);

$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);

$queryBase = http_build_query(array_filter([
    'form_id' => $filters['form_id'] ?: null,
    'q' => $filters['q'] ?: null,
    'is_read' => $filters['is_read'] !== '' ? $filters['is_read'] : null,
    'is_starred' => $filters['is_starred'] !== '' ? $filters['is_starred'] : null,
    'is_spam' => $filters['is_spam'] !== null ? $filters['is_spam'] : null,
], static fn ($v) => $v !== null && $v !== ''));
?>

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-3xl font-bold tracking-tight text-zinc-900">Submissions</h2>
        <p class="mt-2 text-sm text-zinc-500"><?= (int) $total ?> result<?= $total === 1 ? '' : 's' ?> across your forms.</p>
    </div>
    <a href="/admin/submissions/export?<?= e($queryBase) ?>" class="shadcn-btn shadcn-btn-outline gap-2 self-start sm:self-auto">
        <i data-lucide="download" class="h-4 w-4"></i>
        Export CSV
    </a>
</div>

<div class="mb-4 overflow-hidden rounded-xl border border-border bg-white shadow-sm">
    <form method="get" action="/admin/submissions" class="flex flex-wrap items-center gap-3 p-4">
        <div class="relative min-w-[220px] flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"></i>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search submissions..." class="shadcn-input h-10 w-full bg-zinc-50 !pl-10">
        </div>
        <select name="form_id" class="shadcn-input h-10 w-full bg-white sm:w-44">
            <option value="0">All forms</option>
            <?php foreach ($forms as $form): ?>
                <option value="<?= (int) $form['id'] ?>" <?= $filters['form_id'] === (int) $form['id'] ? 'selected' : '' ?>><?= e((string) $form['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="is_read" class="shadcn-input h-10 w-full bg-white sm:w-36">
            <option value="">Status: All</option>
            <option value="0" <?= $filters['is_read'] === '0' ? 'selected' : '' ?>>Unread</option>
            <option value="1" <?= $filters['is_read'] === '1' ? 'selected' : '' ?>>Read</option>
        </select>
        <select name="is_starred" class="shadcn-input h-10 w-full bg-white sm:w-36">
            <option value="">Starred: All</option>
            <option value="1" <?= $filters['is_starred'] === '1' ? 'selected' : '' ?>>Starred</option>
            <option value="0" <?= $filters['is_starred'] === '0' ? 'selected' : '' ?>>Not starred</option>
        </select>
        <select name="is_spam" class="shadcn-input h-10 w-full bg-white sm:w-36">
            <option value="">Inbox + Spam</option>
            <option value="0" <?= $filters['is_spam'] === 0 ? 'selected' : '' ?>>Inbox only</option>
            <option value="1" <?= $filters['is_spam'] === 1 ? 'selected' : '' ?>>Spam only</option>
        </select>
        <button type="submit" class="shadcn-btn shadcn-btn-primary h-10 gap-2">
            <i data-lucide="filter" class="h-4 w-4"></i>
            Apply
        </button>
        <?php if ($queryBase !== ''): ?>
            <a href="/admin/submissions" class="text-sm text-zinc-500 hover:text-zinc-900">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div class="overflow-hidden rounded-xl border border-border bg-white shadow-sm">
    <?php if ($isEditor && $items !== []): ?>
    <form method="post" action="/admin/forms/submissions/bulk" id="bulk-form" onsubmit="if(this.bulk_action.value==='delete'){return confirm('Delete selected submissions permanently?');}">
        <?= csrf_field() ?>
        <?php if ($filters['form_id'] > 0): ?>
            <input type="hidden" name="form_id" value="<?= $filters['form_id'] ?>">
        <?php endif; ?>
        <div class="flex flex-wrap items-center gap-3 border-b border-border bg-zinc-50/70 px-4 py-3">
            <select name="bulk_action" required class="shadcn-input h-9 w-full bg-white sm:w-52">
                <option value="">Bulk action…</option>
                <option value="read">Mark as read</option>
                <option value="unread">Mark as unread</option>
                <option value="star">Add star</option>
                <option value="unstar">Remove star</option>
                <option value="spam">Report spam</option>
                <option value="not_spam">Not spam</option>
                <option value="delete">Delete permanently</option>
            </select>
            <button type="submit" class="shadcn-btn shadcn-btn-outline h-9 bg-white">Apply to selected</button>
            <p class="text-xs text-zinc-500">Select rows below, then apply an action.</p>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-zinc-50 text-xs uppercase tracking-wider text-zinc-500">
                <tr>
                    <?php if ($isEditor): ?>
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" class="rounded border-zinc-300" onclick="document.querySelectorAll('.sub-cb').forEach(c=>c.checked=this.checked)" <?= $items === [] ? 'disabled' : '' ?>>
                        </th>
                    <?php endif; ?>
                    <th class="px-4 py-3 font-semibold">Form</th>
                    <th class="px-4 py-3 font-semibold">Preview</th>
                    <th class="px-4 py-3 font-semibold">Received</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 bg-white">
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-zinc-500">
                            <i data-lucide="inbox" class="mx-auto mb-3 h-8 w-8 text-zinc-300"></i>
                            <p class="text-sm">No submissions match these filters.</p>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($items as $sub): ?>
                    <?php
                    $data = is_array($sub['data'] ?? null) ? $sub['data'] : [];
                    $preview = '';
                    foreach ($data as $val) {
                        if (is_string($val) && $val !== '') {
                            $preview = $val;
                            break;
                        }
                    }
                    ?>
                    <tr class="transition-colors hover:bg-zinc-50 <?= empty($sub['is_read']) ? 'bg-zinc-50/80' : '' ?>">
                        <?php if ($isEditor): ?>
                            <td class="px-4 py-3">
                                <input type="checkbox" class="sub-cb rounded border-zinc-300" name="submission_ids[]" value="<?= (int) $sub['id'] ?>">
                            </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 font-medium text-zinc-900"><?= e((string) ($sub['form_name'] ?? 'Unknown')) ?></td>
                        <td class="max-w-[280px] truncate px-4 py-3 text-zinc-600">
                            <a href="/admin/forms/<?= (int) $sub['form_id'] ?>/submissions/<?= (int) $sub['id'] ?>" class="hover:underline">
                                <?= e($preview !== '' ? $preview : '(Empty)') ?>
                            </a>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-zinc-500">
                            <?= date('M d, Y', strtotime((string) $sub['created_at'])) ?>
                            <span class="text-zinc-400"><?= date('g:i A', strtotime((string) $sub['created_at'])) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                <?php if (!empty($sub['is_starred'])): ?>
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Starred</span>
                                <?php endif; ?>
                                <?php if (!empty($sub['is_spam'])): ?>
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Spam</span>
                                <?php else: ?>
                                    <?php if (empty($sub['is_read'])): ?>
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">New</span>
                                    <?php else: ?>
                                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600">Read</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <?php if ($isEditor): ?>
                                    <button type="submit" form="row-star-<?= (int) $sub['id'] ?>" class="shadcn-btn shadcn-btn-outline h-8 px-2 text-xs" title="<?= !empty($sub['is_starred']) ? 'Unstar' : 'Star' ?>">
                                        <i data-lucide="star" class="h-3.5 w-3.5 <?= !empty($sub['is_starred']) ? 'text-amber-500' : '' ?>"></i>
                                    </button>
                                    <button type="submit" form="row-del-<?= (int) $sub['id'] ?>" class="shadcn-btn shadcn-btn-outline h-8 px-2 text-xs text-red-600" title="Delete" onclick="return confirm('Delete this submission permanently?')">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="/admin/forms/<?= (int) $sub['form_id'] ?>/submissions/<?= (int) $sub['id'] ?>" class="shadcn-btn shadcn-btn-outline h-8 gap-1 px-3 text-xs">
                                    View
                                    <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($isEditor && $items !== []): ?>
    </form>
    <?php foreach ($items as $sub): ?>
        <form id="row-star-<?= (int) $sub['id'] ?>" method="post" action="/admin/submissions/action" class="hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="form_id" value="<?= (int) $sub['form_id'] ?>">
            <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
            <input type="hidden" name="bulk_action" value="<?= !empty($sub['is_starred']) ? 'unstar' : 'star' ?>">
        </form>
        <form id="row-del-<?= (int) $sub['id'] ?>" method="post" action="/admin/submissions/action" class="hidden">
            <?= csrf_field() ?>
            <input type="hidden" name="form_id" value="<?= (int) $sub['form_id'] ?>">
            <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>">
            <input type="hidden" name="bulk_action" value="delete">
        </form>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="mt-6 flex flex-wrap justify-center gap-2">
        <?php for ($p = 1; $p <= min($totalPages, 20); $p++): ?>
            <a href="/admin/submissions?<?= e($queryBase . ($queryBase !== '' ? '&' : '') . 'page=' . $p) ?>"
               class="inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-medium <?= $p === $page ? 'bg-zinc-900 text-white' : 'border border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50' ?>">
               <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
