<?php

declare(strict_types=1);

$admin = $state['admin'] ?? [];
$settings = $state['settings'] ?? [];
?>

<div class="text-center">
    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-600">✓</div>
    <h2 class="text-xl font-semibold text-zinc-900">Ready to install</h2>
    <p class="mt-2 text-sm text-zinc-600">
        Click the button below to write <code class="rounded bg-zinc-100 px-1">config.php</code> and finish setup.
        The installer will be locked immediately after.
    </p>
</div>

<dl class="mt-8 space-y-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm">
    <div class="flex justify-between gap-4">
        <dt class="text-zinc-500">Admin</dt>
        <dd class="font-medium text-zinc-900"><?= e((string) ($admin['email'] ?? '')) ?></dd>
    </div>
    <div class="flex justify-between gap-4">
        <dt class="text-zinc-500">Site name</dt>
        <dd class="font-medium text-zinc-900"><?= e((string) ($settings['site_name'] ?? 'FormFlow')) ?></dd>
    </div>
    <div class="flex justify-between gap-4">
        <dt class="text-zinc-500">Timezone</dt>
        <dd class="font-medium text-zinc-900"><?= e((string) ($settings['timezone'] ?? 'UTC')) ?></dd>
    </div>
    <div class="flex justify-between gap-4">
        <dt class="text-zinc-500">SMTP</dt>
        <dd class="font-medium text-zinc-900"><?= !empty($state['smtp_skipped']) ? 'Skipped' : 'Configured' ?></dd>
    </div>
</dl>

<form method="post" action="/install/complete" class="mt-8">
    <?= csrf_field() ?>
    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-zinc-900 text-sm font-medium text-white hover:bg-zinc-800">
        Finish Installation
    </button>
</form>

<p class="mt-4 text-center text-xs text-zinc-500">
    To reinstall later, delete <code class="rounded bg-zinc-100 px-1">config.php</code> from the project root.
</p>
