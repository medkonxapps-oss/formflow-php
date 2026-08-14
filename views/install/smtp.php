<?php

declare(strict_types=1);

$smtp = $state['smtp'] ?? [];
?>

<p class="mb-6 text-sm text-zinc-600">Configure outbound email for notifications. You can skip this and set it up later in Settings.</p>

<form method="post" action="/install/smtp" class="space-y-4" x-data="smtpStep()">
    <?= csrf_field() ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2 sm:col-span-2">
            <label class="text-sm font-medium" for="host">SMTP host</label>
            <input id="host" name="host" type="text" value="<?= e(old('host', $smtp['host'] ?? '')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="port">Port</label>
            <input id="port" name="port" type="number" value="<?= e(old('port', (string) ($smtp['port'] ?? '587'))) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="encryption">Encryption</label>
            <select id="encryption" name="encryption"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
                <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'] as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= (old('encryption', $smtp['encryption'] ?? 'tls') === $val) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="username">Username</label>
            <input id="username" name="username" type="text" value="<?= e(old('username', $smtp['username'] ?? '')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="password">Password</label>
            <input id="password" name="password" type="password" value="<?= e(old('password', $smtp['password'] ?? '')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="from_email">From email</label>
            <input id="from_email" name="from_email" type="email" value="<?= e(old('from_email', $smtp['from_email'] ?? '')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="from_name">From name</label>
            <input id="from_name" name="from_name" type="text" value="<?= e(old('from_name', $smtp['from_name'] ?? 'FormFlow')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2 sm:col-span-2">
            <label class="text-sm font-medium" for="test_email">Send test email to</label>
            <input id="test_email" name="test_email" type="email" placeholder="you@example.com" x-model="testEmail"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
    </div>

    <div x-show="message" x-cloak class="rounded-lg border px-4 py-3 text-sm"
         :class="emailOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'">
        <span x-text="message"></span>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <button type="button" @click="sendTest()" :disabled="testing"
            class="inline-flex h-10 flex-1 items-center justify-center rounded-md border border-zinc-300 bg-white px-4 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50">
            <span x-show="!testing">Send Test Email</span>
            <span x-show="testing" x-cloak>Sending…</span>
        </button>
        <button type="submit"
            class="inline-flex h-10 flex-1 items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white hover:bg-zinc-800">
            Save &amp; Continue
        </button>
    </div>
</form>

<form method="post" action="/install/smtp/skip" class="mt-4">
    <?= csrf_field() ?>
    <button type="submit" class="w-full text-center text-sm font-medium text-zinc-500 hover:text-zinc-800 hover:underline">
        Skip for now, configure later in Settings
    </button>
</form>

<script>
function smtpStep() {
    return {
        testing: false,
        emailOk: false,
        message: '',
        testEmail: '',
        async sendTest() {
            this.testing = true;
            this.message = '';
            const form = this.$el.closest('form');
            const data = new FormData(form);
            data.set('test_email', this.testEmail);
            try {
                const res = await fetch('/install/api/test-email', { method: 'POST', body: data });
                const json = await res.json();
                this.emailOk = !!json.success;
                this.message = json.message || json.error || '';
            } catch (e) {
                this.emailOk = false;
                this.message = 'Request failed.';
            }
            this.testing = false;
        }
    };
}
</script>
