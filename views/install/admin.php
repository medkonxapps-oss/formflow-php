<?php

declare(strict_types=1);

$admin = $state['admin'] ?? [];
$alreadyCreated = !empty($state['admin_created']);
?>

<?php if ($alreadyCreated): ?>
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        Admin account <strong><?= e((string) ($admin['email'] ?? '')) ?></strong> has been created.
    </div>
    <a href="/install/settings" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-zinc-900 text-sm font-medium text-white hover:bg-zinc-800">
        Continue to Site Settings
    </a>
<?php else: ?>
    <p class="mb-6 text-sm text-zinc-600">Create the first administrator account. This is the only account that can be created without an invite.</p>

    <form method="post" action="/install/admin" class="space-y-4" x-data="{ showPassword: false, score: 0, scoreLabel: '' }"
          x-init="if (window.zxcvbn) { document.getElementById('admin_password').addEventListener('input', (e) => { const r = zxcvbn(e.target.value); score = r.score; scoreLabel = ['Very weak','Weak','Fair','Strong','Very strong'][r.score]; }); }">
        <?= csrf_field() ?>

        <div class="space-y-2">
            <label class="text-sm font-medium" for="name">Full name</label>
            <input id="name" name="name" type="text" required value="<?= e(old('name')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="email">Email</label>
            <input id="email" name="email" type="email" required value="<?= e(old('email')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="admin_password">Password</label>
            <input id="admin_password" name="password" type="password" required minlength="12" autocomplete="new-password"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
            <div class="space-y-1" x-show="scoreLabel" x-cloak>
                <div class="flex h-1.5 overflow-hidden rounded-full bg-zinc-100">
                    <div class="h-full rounded-full transition-all duration-300"
                         :class="{
                            'w-1/5 bg-red-500': score === 0, 'w-2/5 bg-orange-500': score === 1,
                            'w-3/5 bg-yellow-500': score === 2, 'w-4/5 bg-lime-500': score === 3, 'w-full bg-emerald-500': score === 4
                         }"></div>
                </div>
                <p class="text-xs text-zinc-500" x-text="scoreLabel"></p>
            </div>
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>

        <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-zinc-900 text-sm font-medium text-white hover:bg-zinc-800">
            Create Admin Account
        </button>
    </form>
    <script src="https://cdn.jsdelivr.net/npm/zxcvbn@4.4.2/dist/zxcvbn.js"></script>
<?php endif; ?>
