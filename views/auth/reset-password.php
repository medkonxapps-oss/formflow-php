<?php

declare(strict_types=1);

$token = (string) ($routeParams['token'] ?? '');
$error = flash('error');
?>

<?php if ($error): ?>
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<p class="mb-6 text-sm text-muted-foreground">
    Choose a new password. Must be at least 12 characters.
</p>

<form
    method="post"
    action="/reset-password"
    class="space-y-5"
    x-data="{ showPassword: false, showConfirm: false, score: 0, scoreLabel: '' }"
    x-init="
        if (window.zxcvbn) {
            const pw = document.getElementById('password');
            pw.addEventListener('input', () => {
                const r = zxcvbn(pw.value);
                score = r.score;
                scoreLabel = ['Very weak','Weak','Fair','Strong','Very strong'][r.score];
            });
        }
    "
>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="space-y-2">
        <label for="password" class="text-sm font-medium leading-none text-zinc-900">New password</label>
        <div class="relative">
            <input
                :type="showPassword ? 'text' : 'password'"
                id="password"
                name="password"
                required
                minlength="12"
                autocomplete="new-password"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                placeholder="At least 12 characters"
            >
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-400 hover:text-zinc-600"
                @click="showPassword = !showPassword"
                tabindex="-1"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </button>
        </div>
        <div class="space-y-1" x-show="scoreLabel" x-cloak>
            <div class="flex h-1.5 overflow-hidden rounded-full bg-zinc-100">
                <div class="h-full rounded-full transition-all duration-300"
                     :class="{
                        'w-1/5 bg-red-500': score === 0,
                        'w-2/5 bg-orange-500': score === 1,
                        'w-3/5 bg-yellow-500': score === 2,
                        'w-4/5 bg-lime-500': score === 3,
                        'w-full bg-emerald-500': score === 4
                     }"></div>
            </div>
            <p class="text-xs text-muted-foreground" x-text="scoreLabel"></p>
        </div>
    </div>

    <div class="space-y-2">
        <label for="password_confirmation" class="text-sm font-medium leading-none text-zinc-900">Confirm password</label>
        <div class="relative">
            <input
                :type="showConfirm ? 'text' : 'password'"
                id="password_confirmation"
                name="password_confirmation"
                required
                minlength="12"
                autocomplete="new-password"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                placeholder="Repeat your password"
            >
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-400 hover:text-zinc-600"
                @click="showConfirm = !showConfirm"
                tabindex="-1"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </button>
        </div>
    </div>

    <button
        type="submit"
        class="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
        Reset password
    </button>
</form>

<script src="https://cdn.jsdelivr.net/npm/zxcvbn@4.4.2/dist/zxcvbn.js"></script>
