<?php

declare(strict_types=1);

use FormFlow\InviteRepository;

$token = (string) ($routeParams['token'] ?? '');
$invites = new InviteRepository($config);
$invite = $invites->findValidByToken($token);

if ($invite === null) {
    echo '<div class="text-center"><h1 class="text-xl font-semibold">Invite expired</h1><p class="mt-2 text-sm text-zinc-500">This invite is invalid or has already been used.</p><a href="/login" class="mt-4 inline-block text-sm underline">Sign in</a></div>';
    return;
}
?>

<div>
    <h1 class="text-2xl font-semibold text-zinc-900">Join <?= e((string) ($config['app']['name'] ?? 'FormFlow')) ?></h1>
    <p class="mt-1 text-sm text-zinc-500">
        You were invited as <strong><?= e((string) $invite['role']) ?></strong> using <?= e((string) $invite['email']) ?>.
    </p>
    <form method="post" action="/invite/accept" class="mt-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div>
            <label class="text-sm font-medium">Your name</label>
            <input type="text" name="name" required class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
        </div>
        <div>
            <label class="text-sm font-medium">Password</label>
            <input type="password" name="password" required minlength="12" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
            <p class="mt-1 text-xs text-zinc-500">At least 12 characters.</p>
        </div>
        <div>
            <label class="text-sm font-medium">Confirm password</label>
            <input type="password" name="password_confirmation" required minlength="12" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
        </div>
        <button type="submit" class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800">Create account</button>
    </form>
</div>
