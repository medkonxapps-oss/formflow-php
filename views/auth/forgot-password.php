<?php

declare(strict_types=1);

$success = flash('success');
$error = flash('error');
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

<p class="mb-6 text-sm text-muted-foreground">
    Enter your email address and we'll send you a link to reset your password.
</p>

<form method="post" action="/forgot-password" class="space-y-5">
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

    <button
        type="submit"
        class="inline-flex h-10 w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-zinc-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
        Send reset link
    </button>

    <p class="text-center text-sm">
        <a href="/login" class="font-medium text-zinc-600 hover:text-zinc-900 hover:underline">Back to sign in</a>
    </p>
</form>
