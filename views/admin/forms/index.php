<?php

declare(strict_types=1);

use FormFlow\FormRepository;

$userId = (int) ($currentUser['id'] ?? 0);
$repo = new FormRepository($config);
$forms = $repo->listForUser($userId);
$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);
?>

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="text-3xl font-bold tracking-tight text-zinc-900">Forms</h2>
        <p class="mt-2 text-sm text-zinc-500">Manage your forms, analyze performance, and view submissions.</p>
    </div>
    <?php if ($isEditor): ?>
        <a href="/admin/forms/new" class="shadcn-btn shadcn-btn-primary gap-2">
            <i data-lucide="plus" class="h-4 w-4"></i>
            New Form
        </a>
    <?php endif; ?>
</div>

<div class="rounded-xl border border-border bg-white shadow-sm" x-data="{ search: '', filterStatus: 'all' }">
    <!-- Header with Search -->
    <div class="border-b border-border p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative max-w-sm w-full">
            <i data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-zinc-400"></i>
            <input type="text" x-model="search" placeholder="Search forms..." class="shadcn-input !pl-9 w-full rounded-full bg-zinc-50 border-none shadow-sm text-sm h-9">
        </div>
        <div class="flex items-center gap-2">
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="shadcn-btn shadcn-btn-outline gap-2 h-9 text-xs">
                    <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                    Filter
                </button>
                <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 top-full mt-1 w-32 rounded-md border border-border bg-white p-1 shadow-md z-50 text-left">
                    <button type="button" @click="filterStatus = 'all'; open = false" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100" :class="filterStatus === 'all' ? 'font-semibold bg-zinc-50' : ''">All</button>
                    <button type="button" @click="filterStatus = 'active'; open = false" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100" :class="filterStatus === 'active' ? 'font-semibold bg-zinc-50' : ''">Active</button>
                    <button type="button" @click="filterStatus = 'paused'; open = false" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100" :class="filterStatus === 'paused' ? 'font-semibold bg-zinc-50' : ''">Paused</button>
                </div>
            </div>
        </div>
    </div>

    <div class="p-0">
        <?php if ($forms === []): ?>
            <div class="flex flex-col items-center justify-center p-16 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-zinc-50 border border-zinc-100 mb-4">
                    <i data-lucide="layout" class="h-8 w-8 text-zinc-300"></i>
                </div>
                <h3 class="text-lg font-medium text-zinc-900">No forms yet</h3>
                <p class="mt-1 text-sm text-zinc-500">Create your first form to start collecting data.</p>
                <?php if ($isEditor): ?>
                    <a href="/admin/forms/new" class="mt-6 shadcn-btn shadcn-btn-primary gap-2">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Create Form
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50/50 text-zinc-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Name</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold">Submissions</th>
                            <th class="px-6 py-4 font-semibold">Updated</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($forms as $form): ?>
                            <tr class="hover:bg-zinc-50/80 transition-colors group"
                                x-data="{ text: '<?= e(strtolower((string) $form['name'] . ' ' . $form['slug'])) ?>', status: '<?= e(strtolower((string) $form['status'])) ?>' }"
                                x-show="(search === '' || text.includes(search.toLowerCase())) && (filterStatus === 'all' || status === filterStatus)">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 border border-zinc-200 text-zinc-500 group-hover:bg-white group-hover:shadow-sm transition-all">
                                            <i data-lucide="layout" class="h-4 w-4"></i>
                                        </div>
                                        <div>
                                            <a href="/admin/forms/<?= (int) $form['id'] ?>/edit" class="font-semibold text-zinc-900 hover:underline"><?= e((string) $form['name']) ?></a>
                                            <div class="text-xs text-zinc-400 mt-0.5 font-mono">/<?= e((string) $form['slug']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $form['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20' ?>">
                                        <?php if($form['status'] === 'active'): ?>
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                        <?php else: ?>
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                                        <?php endif; ?>
                                        <?= ucfirst(e((string) $form['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="/admin/submissions?form_id=<?= (int) $form['id'] ?>" class="inline-flex items-center gap-1.5 text-zinc-700 hover:text-zinc-900 font-medium">
                                        <i data-lucide="inbox" class="h-4 w-4 text-zinc-400"></i>
                                        <?= (int) ($form['submission_count'] ?? 0) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-zinc-500 text-xs">
                                    <?= date('M d, Y', strtotime((string)$form['updated_at'])) ?>
                                </td>
                                <td class="relative px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2" x-data="{ menuOpen: false }">
                                        <a href="/admin/forms/<?= (int) $form['id'] ?>/edit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-900" title="Edit Form">
                                            <i data-lucide="pen-line" class="h-4 w-4"></i>
                                        </a>
                                        <a href="/admin/forms/<?= (int) $form['id'] ?>/analytics" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-900" title="Analytics">
                                            <i data-lucide="bar-chart-2" class="h-4 w-4"></i>
                                        </a>
                                        
                                        <?php if ($isEditor): ?>
                                            <div class="relative" @click.away="menuOpen = false" :class="menuOpen ? 'z-30' : ''">
                                                <button type="button" @click="menuOpen = !menuOpen" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-400 transition-colors hover:bg-zinc-100 hover:text-zinc-900" aria-haspopup="true" :aria-expanded="menuOpen">
                                                    <i data-lucide="more-horizontal" class="h-4 w-4"></i>
                                                </button>
                                                
                                                <div x-show="menuOpen" x-transition.opacity x-cloak class="absolute right-0 bottom-full z-50 mb-1 w-48 rounded-md border border-border bg-white p-1 text-left shadow-lg">
                                                    
                                                    <form method="post" action="/admin/forms/toggle" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                                                        <button type="submit" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100">
                                                            <i data-lucide="<?= $form['status'] === 'active' ? 'pause' : 'play' ?>" class="h-3.5 w-3.5"></i>
                                                            <?= $form['status'] === 'active' ? 'Pause Form' : 'Activate Form' ?>
                                                        </button>
                                                    </form>

                                                    <form method="post" action="/admin/forms/duplicate" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                                                        <button type="submit" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-xs text-zinc-700 hover:bg-zinc-100">
                                                            <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                                            Duplicate Form
                                                        </button>
                                                    </form>
                                                    
                                                    <div class="my-1 border-t border-zinc-100"></div>
                                                    
                                                    <form method="post" action="/admin/forms/delete" class="m-0" onsubmit="return confirm('Are you sure you want to permanently delete this form and all its submissions?')">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
                                                        <button type="submit" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-xs text-red-600 hover:bg-red-50">
                                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                            Delete Form
                                                        </button>
                                                    </form>

                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border p-4 flex items-center justify-between text-sm text-zinc-500">
                <span>Showing <?= count($forms) ?> form(s)</span>
            </div>
        <?php endif; ?>
    </div>
</div>
