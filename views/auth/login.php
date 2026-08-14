<?php

declare(strict_types=1);

$success = flash('success');
$error = flash('error');
$requiresCaptcha = !empty($_SESSION['_requires_captcha']);
?>

<?php if ($success): ?>
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="alert">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="/login" class="space-y-5" x-data="{ showPassword: false }">
    <?= csrf_field() ?>

    <div class="space-y-2">
        <label for="email" class="text-sm font-medium leading-none text-zinc-900">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= e(old('email')) ?>"
            required
            autocomplete="email"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            placeholder="you@example.com"
        >
    </div>

    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <label for="password" class="text-sm font-medium leading-none text-zinc-900">Password</label>
            <a href="/forgot-password" class="text-sm font-medium text-zinc-600 hover:text-zinc-900 hover:underline">
                Forgot password?
            </a>
        </div>
        <div class="relative">
            <input
                :type="showPassword ? 'text' : 'password'"
                id="password"
                name="password"
                required
                autocomplete="current-password"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                placeholder="Enter your password"
            >
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-zinc-400 hover:text-zinc-600"
                @click="showPassword = !showPassword"
                tabindex="-1"
                aria-label="Toggle password visibility"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
            </button>
        </div>
    </div>

    <?php if ($requiresCaptcha): ?>
        <div class="sr-only" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <p class="text-xs text-muted-foreground">Additional verification is required due to repeated failed attempts.</p>
    <?php endif; ?>

    <div class="flex items-center space-x-2">
        <input
            type="checkbox"
            id="remember"
            name="remember"
            value="1"
            class="h-4 w-4 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900"
        >
        <label for="remember" class="text-sm font-medium leading-none text-zinc-700">Remember me for 30 days</label>
    </div>

    <button
        type="submit"
        class="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
        Sign in
    </button>
</form>
