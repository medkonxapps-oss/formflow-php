<?php

declare(strict_types=1);

use FormFlow\FormRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$repo = new FormRepository($config);
$forms = $repo->listForUser($userId);
$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);
?>

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-2xl font-semibold text-zinc-900">Forms</h2>
        <p class="mt-1 text-sm text-zinc-500">Manage your forms and view submissions.</p>
    </div>
    <?php if ($isEditor): ?>
        <a href="/admin/forms/new" class="inline-flex h-10 items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white hover:bg-zinc-800">
            + New Form
        </a>
    <?php endif; ?>
</div>

<?php if ($forms === []): ?>
    <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-12 text-center">
        <p class="text-zinc-600">No forms yet.</p>
        <?php if ($isEditor): ?>
            <a href="/admin/forms/new" class="mt-4 inline-block text-sm font-medium text-zinc-900 hover:underline">Create your first form</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600">Submissions</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-600">Updated</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                <?php foreach ($forms as $form): ?>
                    <tr class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <a href="/admin/forms/<?= (int) $form['id'] ?>/edit" class="font-medium text-zinc-900 hover:underline"><?= e((string) $form['name']) ?></a>
                            <div class="text-xs text-zinc-400"><?= e((string) $form['slug']) ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium <?= $form['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                                <?= e((string) $form['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/admin/forms/<?= (int) $form['id'] ?>/submissions" class="text-zinc-700 hover:underline">
                                <?= (int) ($form['submission_count'] ?? 0) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-zinc-500"><?= e((string) $form['updated_at']) ?></td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <a href="/admin/forms/<?= (int) $form['id'] ?>/submissions" class="rounded-md border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50">Inbox</a>
                                <a href="/admin/forms/<?= (int) $form['id'] ?>/analytics" class="rounded-md border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50">Analytics</a>
                                <?php if ($isEditor): ?>
                                    <form method="post" action="/admin/forms/toggle" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                                        <button type="submit" class="rounded-md border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50">
                                            <?= $form['status'] === 'active' ? 'Pause' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="/admin/forms/duplicate" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                                        <button type="submit" class="rounded-md border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50">Duplicate</button>
                                    </form>
                                    <form method="post" action="/admin/forms/delete" class="inline" onsubmit="return confirm('Delete this form and all submissions?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                                        <button type="submit" class="rounded-md border border-red-200 px-2 py-1 text-xs text-red-700 hover:bg-red-50">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
