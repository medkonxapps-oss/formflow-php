<?php

declare(strict_types=1);

use FormFlow\TemplateRepository;

$templates = new TemplateRepository($config);
$list = $templates->listAll();
$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);
?>

<div class="mb-6">
    <h2 class="text-2xl font-semibold text-zinc-900">Templates</h2>
    <p class="mt-1 text-sm text-zinc-500">Start from a pre-built form and customize it in the builder.</p>
</div>

<?php if ($list === []): ?>
    <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-12 text-center">
        <p class="text-zinc-600">No templates available. Run <code class="rounded bg-zinc-100 px-1">php core/seed-templates.php</code>.</p>
    </div>
<?php else: ?>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($list as $tpl): ?>
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= e((string) $tpl['category']) ?></span>
                <h3 class="mt-1 text-lg font-semibold text-zinc-900"><?= e((string) $tpl['name']) ?></h3>
                <p class="mt-2 flex-1 text-sm text-zinc-600"><?= e((string) ($tpl['description'] ?? '')) ?></p>
                <?php if ($isEditor): ?>
                    <form method="post" action="/admin/templates/use" class="mt-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="template_id" value="<?= (int) $tpl['id'] ?>">
                        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">
                            Use Template
                        </button>
                    </form>
                <?php else: ?>
                    <p class="mt-4 text-xs text-zinc-400">Editor role required to create forms.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
