<?php

declare(strict_types=1);

use FormFlow\ApiKeyRepository;
use FormFlow\ConfigManager;
use FormFlow\LoginActivityRepository;

$tab = in_array($_GET['tab'] ?? 'general', ['general', 'smtp', 'security', 'api', 'backup'], true)
    ? (string) $_GET['tab']
    : 'general';

$manager = new ConfigManager($config);
$app = $config['app'] ?? [];
$smtp = $config['smtp'] ?? [];
$security = $manager->security();
$userId = (int) ($currentUser['id'] ?? 0);

$apiKeys = (new ApiKeyRepository($config))->listForUser($userId);
$loginActivity = (new LoginActivityRepository($config))->recent(30);
$newApiKey = $_SESSION['_new_api_key'] ?? null;
unset($_SESSION['_new_api_key']);

$timezones = timezone_identifiers_list();
$dateFormats = ['Y-m-d' => '2026-08-14', 'd/m/Y' => '14/08/2026', 'M j, Y' => 'Aug 14, 2026'];
$locales = ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German'];
?>

<div class="mb-6">
    <h2 class="text-2xl font-semibold text-zinc-900">Settings</h2>
    <p class="mt-1 text-sm text-zinc-500">Site configuration — admin only.</p>
</div>

<div x-data="{ tab: '<?= e($tab) ?>' }" class="flex flex-col gap-6 lg:flex-row">
    <nav class="flex shrink-0 flex-row flex-wrap gap-1 lg:w-48 lg:flex-col">
        <?php foreach (['general' => 'General', 'smtp' => 'SMTP / Email', 'security' => 'Security', 'api' => 'API Keys', 'backup' => 'Backup / Export'] as $key => $label): ?>
            <a href="?tab=<?= $key ?>"
               @click.prevent="tab='<?= $key ?>'; history.replaceState(null,'','?tab=<?= $key ?>')"
               :class="tab === '<?= $key ?>' ? 'bg-zinc-900 text-white' : 'text-zinc-700 hover:bg-zinc-100'"
               class="rounded-md px-3 py-2 text-sm font-medium"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="min-w-0 flex-1">
        <!-- General -->
        <div x-show="tab === 'general'" x-cloak class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-zinc-900">General</h3>
            <form method="post" action="/admin/settings/general" class="space-y-4 max-w-lg">
                <?= csrf_field() ?>
                <div>
                    <label class="text-sm font-medium">Site name</label>
                    <input type="text" name="site_name" value="<?= e((string) ($app['name'] ?? '')) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">Timezone</label>
                    <select name="timezone" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?= e($tz) ?>" <?= ($app['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Date format</label>
                    <select name="date_format" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                        <?php foreach ($dateFormats as $fmt => $example): ?>
                            <option value="<?= e($fmt) ?>" <?= ($app['date_format'] ?? 'Y-m-d') === $fmt ? 'selected' : '' ?>><?= e($example) ?> (<?= e($fmt) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Language</label>
                    <select name="locale" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                        <?php foreach ($locales as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= ($app['locale'] ?? 'en') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Save</button>
            </form>
        </div>

        <!-- SMTP -->
        <div x-show="tab === 'smtp'" x-cloak class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-zinc-900">SMTP / Email</h3>
            <form method="post" action="/admin/settings/smtp" class="space-y-4 max-w-lg">
                <?= csrf_field() ?>
                <div>
                    <label class="text-sm font-medium">SMTP host</label>
                    <input type="text" name="host" value="<?= e((string) ($smtp['host'] ?? '')) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Port</label>
                        <input type="number" name="port" value="<?= (int) ($smtp['port'] ?? 587) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Encryption</label>
                        <select name="encryption" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                            <option value="tls" <?= ($smtp['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="" <?= ($smtp['encryption'] ?? '') === '' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium">Username</label>
                    <input type="text" name="username" value="<?= e((string) ($smtp['username'] ?? '')) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm" autocomplete="off">
                </div>
                <div>
                    <label class="text-sm font-medium">Password</label>
                    <input type="password" name="password" value="" placeholder="<?= !empty($smtp['password']) ? '•••••••• (leave blank to keep)' : '' ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm" autocomplete="new-password">
                    <p class="mt-1 text-xs text-zinc-500">Stored encrypted at rest using your app secret.</p>
                </div>
                <div>
                    <label class="text-sm font-medium">From email</label>
                    <input type="email" name="from_email" value="<?= e((string) ($smtp['from_email'] ?? '')) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                </div>
                <div>
                    <label class="text-sm font-medium">From name</label>
                    <input type="text" name="from_name" value="<?= e((string) ($smtp['from_name'] ?? '')) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                </div>
                <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Save SMTP</button>
            </form>
            <form method="post" action="/admin/settings/smtp/test" class="mt-6 flex max-w-lg gap-2 border-t border-zinc-100 pt-6">
                <?= csrf_field() ?>
                <input type="email" name="test_email" placeholder="test@example.com" required class="h-10 flex-1 rounded-md border border-zinc-300 px-3 text-sm">
                <button type="submit" class="rounded-md border border-zinc-200 bg-white px-4 py-2 text-sm font-medium hover:bg-zinc-50">Send test</button>
            </form>
        </div>

        <!-- Security -->
        <div x-show="tab === 'security'" x-cloak class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold text-zinc-900">Security</h3>
                <form method="post" action="/admin/settings/security" class="space-y-4 max-w-lg">
                    <?= csrf_field() ?>
                    <div>
                        <label class="text-sm font-medium">Global reCAPTCHA site key</label>
                        <input type="text" name="recaptcha_site_key" value="<?= e((string) ($security['recaptcha_site_key'] ?? '')) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm font-mono text-xs">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Global reCAPTCHA secret key</label>
                        <input type="text" name="recaptcha_secret_key" value="<?= e((string) ($security['recaptcha_secret_key'] ?? '')) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm font-mono text-xs">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Global rate limit (per minute)</label>
                        <input type="number" name="rate_limit_per_minute" min="1" value="<?= (int) ($security['rate_limit_per_minute'] ?? 10) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Session timeout (minutes)</label>
                        <input type="number" name="session_timeout_minutes" min="5" value="<?= (int) ($security['session_timeout_minutes'] ?? 120) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                    </div>
                    <div>
                        <label class="text-sm font-medium">IP allow list (one per line)</label>
                        <textarea name="ip_allowlist" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm font-mono"><?= e(implode("\n", (array) ($security['ip_allowlist'] ?? []))) ?></textarea>
                    </div>
                    <div>
                        <label class="text-sm font-medium">IP block list (one per line)</label>
                        <textarea name="ip_blocklist" rows="3" class="mt-1 w-full rounded-md border border-zinc-300 px-3 py-2 text-sm font-mono"><?= e(implode("\n", (array) ($security['ip_blocklist'] ?? []))) ?></textarea>
                    </div>
                    <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Save security</button>
                </form>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold text-zinc-900">Login activity (read-only)</h3>
                <?php if ($loginActivity === []): ?>
                    <p class="text-sm text-zinc-500">No login activity recorded yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto text-sm">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-zinc-100 text-left text-zinc-500">
                                    <th class="pb-2 pr-4">When</th>
                                    <th class="pb-2 pr-4">Event</th>
                                    <th class="pb-2 pr-4">Identifier</th>
                                    <th class="pb-2">IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-50">
                                <?php foreach ($loginActivity as $row): ?>
                                    <tr>
                                        <td class="py-2 pr-4 text-zinc-500"><?= e((string) ($row['created_at'] ?? '')) ?></td>
                                        <td class="py-2 pr-4">
                                            <?php if (($row['type'] ?? '') === 'audit'): ?>
                                                <?= e((string) ($row['action'] ?? '')) ?>
                                            <?php else: ?>
                                                <?= !empty($row['success']) ? 'attempt.ok' : 'attempt.fail' ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 pr-4 text-zinc-700">
                                            <?php if (($row['type'] ?? '') === 'audit'): ?>
                                                <?= e((string) ($row['user_email'] ?? $row['user_name'] ?? '')) ?>
                                            <?php else: ?>
                                                <?= e((string) ($row['identifier'] ?? '')) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 text-zinc-500"><?= e((string) ($row['ip_address'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- API Keys -->
        <div x-show="tab === 'api'" x-cloak class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-zinc-900">API Keys</h3>
            <?php if ($newApiKey): ?>
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
                    <p class="font-medium text-amber-900">Copy your new API key now:</p>
                    <code class="mt-2 block break-all rounded bg-white px-2 py-1 font-mono text-xs"><?= e((string) $newApiKey) ?></code>
                </div>
            <?php endif; ?>
            <form method="post" action="/admin/settings/api-keys/generate" class="mb-6 flex max-w-lg gap-2">
                <?= csrf_field() ?>
                <input type="text" name="key_name" placeholder="Key name" required class="h-10 flex-1 rounded-md border border-zinc-300 px-3 text-sm">
                <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Generate</button>
            </form>
            <?php if ($apiKeys === []): ?>
                <p class="text-sm text-zinc-500">No API keys yet.</p>
            <?php else: ?>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-100 text-left text-zinc-500">
                            <th class="pb-2 pr-4">Name</th>
                            <th class="pb-2 pr-4">Prefix</th>
                            <th class="pb-2 pr-4">Created</th>
                            <th class="pb-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-50">
                        <?php foreach ($apiKeys as $key): ?>
                            <tr>
                                <td class="py-2 pr-4 font-medium"><?= e((string) $key['name']) ?></td>
                                <td class="py-2 pr-4 font-mono text-xs"><?= e((string) $key['key_prefix']) ?>…</td>
                                <td class="py-2 pr-4 text-zinc-500"><?= e((string) $key['created_at']) ?></td>
                                <td class="py-2">
                                    <?php if (!empty($key['revoked_at'])): ?>
                                        <span class="text-xs text-zinc-400">revoked</span>
                                    <?php else: ?>
                                        <form method="post" action="/admin/settings/api-keys/revoke" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="key_id" value="<?= (int) $key['id'] ?>">
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Backup -->
        <div x-show="tab === 'backup'" x-cloak class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold text-zinc-900">Backup / Export</h3>
            <p class="mb-4 text-sm text-zinc-600">Download a full snapshot of your database.</p>
            <div class="flex flex-wrap gap-3">
                <a href="/admin/settings/backup/sql" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800">Export SQL</a>
                <a href="/admin/settings/backup/json" class="rounded-md border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">Export JSON</a>
            </div>
        </div>
    </div>
</div>
