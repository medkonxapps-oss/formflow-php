<?php

declare(strict_types=1);

$db = $state['database'] ?? [];
?>

<p class="mb-6 text-sm text-zinc-600">Enter your MySQL database credentials. Tables will be created automatically.</p>

<form
    method="post"
    action="/install/database"
    class="space-y-4"
    x-data="databaseStep()"
    @submit="if (!connectionOk) { $event.preventDefault(); testConnection(); }"
>
    <?= csrf_field() ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2 sm:col-span-2">
            <label class="text-sm font-medium" for="host">Host</label>
            <input id="host" name="host" type="text" required value="<?= e(old('host', $db['host'] ?? '127.0.0.1')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="port">Port</label>
            <input id="port" name="port" type="number" required value="<?= e(old('port', (string) ($db['port'] ?? '3306'))) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="prefix">Table prefix</label>
            <input id="prefix" name="prefix" type="text" value="<?= e(old('prefix', $db['prefix'] ?? '')) ?>" placeholder="ff_"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2 sm:col-span-2">
            <label class="text-sm font-medium" for="name">Database name</label>
            <input id="name" name="name" type="text" required value="<?= e(old('name', $db['name'] ?? 'formflow')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="user">Username</label>
            <input id="user" name="user" type="text" required value="<?= e(old('user', $db['user'] ?? 'root')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
        <div class="space-y-2">
            <label class="text-sm font-medium" for="password">Password</label>
            <input id="password" name="password" type="password" value="<?= e(old('password', $db['password'] ?? '')) ?>"
                class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
        </div>
    </div>

    <div x-show="message" x-cloak class="rounded-lg border px-4 py-3 text-sm"
         :class="connectionOk ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'">
        <span x-text="message"></span>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <button type="button" @click="testConnection()" :disabled="testing"
            class="inline-flex h-10 flex-1 items-center justify-center rounded-md border border-zinc-300 bg-white px-4 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50">
            <span x-show="!testing">Test Connection</span>
            <span x-show="testing" x-cloak>Testing…</span>
        </button>
        <button type="submit"
            class="inline-flex h-10 flex-1 items-center justify-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white hover:bg-zinc-800">
            Continue &amp; Run Migrations
        </button>
    </div>
</form>

<script>
function databaseStep() {
    return {
        testing: false,
        connectionOk: false,
        message: '',
        async testConnection() {
            this.testing = true;
            this.message = '';
            const form = this.$el;
            const data = new FormData(form);
            try {
                const res = await fetch('/install/api/test-database', { method: 'POST', body: data });
                const json = await res.json();
                this.connectionOk = !!json.success;
                this.message = json.message || json.error || (json.success ? 'Connected.' : 'Failed.');
            } catch (e) {
                this.connectionOk = false;
                this.message = 'Request failed.';
            }
            this.testing = false;
        }
    };
}
</script>
