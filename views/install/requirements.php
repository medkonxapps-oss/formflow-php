<?php

declare(strict_types=1);

/** @var array{passed: bool, checks: list<array{label: string, status: string, message: string, blocking: bool}>} $requirements */
?>

<p class="mb-6 text-sm text-zinc-600">
    We will verify your server meets the minimum requirements before continuing.
</p>

<ul class="mb-8 space-y-3">
    <?php foreach ($requirements['checks'] as $check): ?>
        <li class="flex items-start gap-3 rounded-lg border px-4 py-3 <?= $check['status'] === 'pass' ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' ?>">
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold <?= $check['status'] === 'pass' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' ?>">
                <?= $check['status'] === 'pass' ? '✓' : '✕' ?>
            </span>
            <div>
                <p class="text-sm font-medium text-zinc-900"><?= e($check['label']) ?></p>
                <p class="text-xs text-zinc-600"><?= e($check['message']) ?></p>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<form method="post" action="/install/requirements">
    <?= csrf_field() ?>
    <button
        type="submit"
        class="inline-flex h-10 w-full items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white shadow hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50"
        <?= $requirements['passed'] ? '' : 'disabled' ?>
    >
        Continue
    </button>
</form>

<?php if (!$requirements['passed']): ?>
    <p class="mt-3 text-center text-xs text-red-600">Resolve all failed checks before you can continue.</p>
<?php endif; ?>
