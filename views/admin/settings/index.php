<?php

declare(strict_types=1);

use FormFlow\ApiKeyRepository;
use FormFlow\Auth;
use FormFlow\ConfigManager;
use FormFlow\InviteRepository;
use FormFlow\LoginActivityRepository;

$requestedTab = (string) ($_GET['tab'] ?? 'general');
$tab = in_array($requestedTab, ['general', 'smtp', 'security', 'api', 'team', 'backup'], true)
    ? $requestedTab
    : 'general';

$manager = new ConfigManager($config);
$app = is_array($config['app'] ?? null) ? $config['app'] : [];
$smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
$security = $manager->security();
$userId = (int) ($currentUser['id'] ?? 0);

$apiKeys = [];
try {
    $apiKeys = (new ApiKeyRepository($config))->listForUser($userId);
} catch (Throwable $e) {
    $apiKeys = [];
}
$loginActivity = [];
try {
    $loginActivity = (new LoginActivityRepository($config))->recent(30);
} catch (Throwable $e) {
    $loginActivity = [];
}
$teamInvites = [];
try {
    $teamInvites = (new InviteRepository($config))->pending();
} catch (Throwable $e) {
    $teamInvites = [];
}
$teamMembers = [];
try {
    $teamMembers = (new Auth($config))->listUsers();
} catch (Throwable $e) {
    $teamMembers = [];
}
$newApiKey = $_SESSION['_new_api_key'] ?? null;
unset($_SESSION['_new_api_key']);
$inviteLink = $_SESSION['_invite_link'] ?? null;
unset($_SESSION['_invite_link']);

$timezones = timezone_identifiers_list();
$dateFormats = ['Y-m-d' => '2026-08-14', 'd/m/Y' => '14/08/2026', 'M j, Y' => 'Aug 14, 2026'];
$locales = ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German'];

$tabs = [
    'general' => ['label' => 'General', 'desc' => 'Site name, timezone, locale', 'icon' => 'sliders-horizontal'],
    'smtp' => ['label' => 'Email / SMTP', 'desc' => 'Outgoing mail server', 'icon' => 'mail'],
    'security' => ['label' => 'Security', 'desc' => 'CAPTCHA, rate limits, IPs', 'icon' => 'shield'],
    'api' => ['label' => 'API keys', 'desc' => 'REST access tokens', 'icon' => 'key-round'],
    'team' => ['label' => 'Team', 'desc' => 'Invite editors and viewers', 'icon' => 'users'],
    'backup' => ['label' => 'Backup', 'desc' => 'Export and import snapshots', 'icon' => 'download'],
];
?>

<div class="mb-8">
    <h2 class="text-3xl font-bold tracking-tight text-zinc-900">Settings</h2>
    <p class="mt-2 text-sm text-zinc-500">Configure your workspace, email, security, and team access.</p>
</div>

<div x-data="{ tab: '<?= e($tab) ?>' }" class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
    <nav class="space-y-1">
        <?php foreach ($tabs as $key => $meta): ?>
            <a href="?tab=<?= e($key) ?>"
               @click.prevent="tab='<?= e($key) ?>'; history.replaceState(null,'','?tab=<?= e($key) ?>')"
               :class="tab === '<?= e($key) ?>' ? 'border-zinc-900 bg-zinc-900 text-white shadow-sm' : 'border-transparent text-zinc-700 hover:bg-white hover:border-zinc-200'"
               class="flex items-start gap-3 rounded-xl border px-3 py-3 text-sm transition-colors">
                <i data-lucide="<?= e($meta['icon']) ?>" class="mt-0.5 h-4 w-4 shrink-0"></i>
                <span>
                    <span class="block font-medium"><?= e($meta['label']) ?></span>
                    <span class="mt-0.5 block text-xs opacity-70"><?= e($meta['desc']) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="min-w-0 space-y-6">
        <!-- General -->
        <div x-show="tab === 'general'" x-cloak class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-100 px-6 py-5">
                <h3 class="text-base font-semibold text-zinc-900">General</h3>
                <p class="mt-1 text-sm text-zinc-500">These values appear in emails, the public site, and the admin header.</p>
            </div>
            <form method="post" action="/admin/settings/general" class="space-y-5 p-6">
                <?= csrf_field() ?>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-zinc-700">Site name</label>
                        <input type="text" name="site_name" value="<?= e((string) ($app['name'] ?? '')) ?>" class="shadcn-input mt-1.5 bg-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-zinc-700">Public site URL</label>
                        <input type="url" name="site_url" value="<?= e((string) ($app['url'] ?? '')) ?>" placeholder="https://forms.example.com" class="shadcn-input mt-1.5 bg-white">
                        <p class="mt-1 text-xs text-zinc-500">Used in embed/iframe links. Localhost cannot be iframed from a public website.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">Timezone</label>
                        <select name="timezone" class="shadcn-input mt-1.5 bg-white">
                            <?php foreach ($timezones as $tz): ?>
                                <option value="<?= e($tz) ?>" <?= ($app['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">Date format</label>
                        <select name="date_format" class="shadcn-input mt-1.5 bg-white">
                            <?php foreach ($dateFormats as $fmt => $example): ?>
                                <option value="<?= e($fmt) ?>" <?= ($app['date_format'] ?? 'Y-m-d') === $fmt ? 'selected' : '' ?>><?= e($example) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">Language</label>
                        <select name="locale" class="shadcn-input mt-1.5 bg-white">
                            <?php foreach ($locales as $code => $label): ?>
                                <option value="<?= e($code) ?>" <?= ($app['locale'] ?? 'en') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end border-t border-zinc-100 pt-5">
                    <button type="submit" class="shadcn-btn shadcn-btn-primary">Save general</button>
                </div>
            </form>
        </div>

        <!-- SMTP -->
        <div x-show="tab === 'smtp'" x-cloak class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-100 px-6 py-5">
                <h3 class="text-base font-semibold text-zinc-900">Email / SMTP</h3>
                <p class="mt-1 text-sm text-zinc-500">Used for password resets, team invites, and submission notifications.</p>
            </div>
            <form method="post" action="/admin/settings/smtp" class="space-y-5 p-6">
                <?= csrf_field() ?>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-zinc-700">SMTP host</label>
                        <input type="text" name="host" value="<?= e((string) ($smtp['host'] ?? '')) ?>" placeholder="smtp.example.com" class="shadcn-input mt-1.5 bg-white">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">Port</label>
                        <input type="number" name="port" value="<?= (int) ($smtp['port'] ?? 587) ?>" class="shadcn-input mt-1.5 bg-white">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">Encryption</label>
                        <select name="encryption" class="shadcn-input mt-1.5 bg-white">
                            <option value="tls" <?= ($smtp['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (recommended)</option>
                            <option value="ssl" <?= ($smtp['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="" <?= ($smtp['encryption'] ?? '') === '' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">Username</label>
                        <input type="text" name="username" value="<?= e((string) ($smtp['username'] ?? '')) ?>" class="shadcn-input mt-1.5 bg-white" autocomplete="off">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">Password</label>
                        <input type="password" name="password" value="" placeholder="<?= !empty($smtp['password']) ? 'Saved — leave blank to keep' : 'SMTP password' ?>" class="shadcn-input mt-1.5 bg-white" autocomplete="new-password">
                        <p class="mt-1 text-xs text-zinc-500">Encrypted with your app secret.</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">From email</label>
                        <input type="email" name="from_email" value="<?= e((string) ($smtp['from_email'] ?? '')) ?>" class="shadcn-input mt-1.5 bg-white">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-700">From name</label>
                        <input type="text" name="from_name" value="<?= e((string) ($smtp['from_name'] ?? '')) ?>" class="shadcn-input mt-1.5 bg-white">
                    </div>
                </div>
                <div class="flex justify-end border-t border-zinc-100 pt-5">
                    <button type="submit" class="shadcn-btn shadcn-btn-primary">Save SMTP</button>
                </div>
            </form>
            <form method="post" action="/admin/settings/smtp/test" class="flex flex-col gap-3 border-t border-zinc-100 bg-zinc-50 px-6 py-5 sm:flex-row sm:items-center">
                <?= csrf_field() ?>
                <p class="flex-1 text-sm text-zinc-600">Send a test message to confirm delivery.</p>
                <input type="email" name="test_email" placeholder="you@example.com" required class="shadcn-input h-9 max-w-xs bg-white">
                <button type="submit" class="shadcn-btn shadcn-btn-outline h-9">Send test</button>
            </form>
        </div>

        <!-- Security -->
        <div x-show="tab === 'security'" x-cloak class="space-y-6">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-6 py-5">
                    <h3 class="text-base font-semibold text-zinc-900">Security</h3>
                    <p class="mt-1 text-sm text-zinc-500">Global defaults for spam protection, IP filters, and session lifetime. Allow/block lists apply to public form submissions. Block list also applies to sign-in.</p>
                </div>
                <form method="post" action="/admin/settings/security" class="space-y-5 p-6">
                    <?= csrf_field() ?>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-sm font-medium text-zinc-700">reCAPTCHA site key</label>
                            <input type="text" name="recaptcha_site_key" value="<?= e((string) ($security['recaptcha_site_key'] ?? '')) ?>" class="shadcn-input mt-1.5 bg-white font-mono text-xs">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-sm font-medium text-zinc-700">reCAPTCHA secret key</label>
                            <input type="text" name="recaptcha_secret_key" value="<?= e((string) ($security['recaptcha_secret_key'] ?? '')) ?>" class="shadcn-input mt-1.5 bg-white font-mono text-xs">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-700">Rate limit (per minute)</label>
                            <input type="number" name="rate_limit_per_minute" min="1" value="<?= (int) ($security['rate_limit_per_minute'] ?? 10) ?>" class="shadcn-input mt-1.5 bg-white">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-700">Session timeout (minutes)</label>
                            <input type="number" name="session_timeout_minutes" min="5" value="<?= (int) ($security['session_timeout_minutes'] ?? 120) ?>" class="shadcn-input mt-1.5 bg-white">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-700">IP allow list</label>
                            <textarea name="ip_allowlist" rows="4" placeholder="One IP per line" class="mt-1.5 w-full rounded-md border border-zinc-200 px-3 py-2 font-mono text-xs"><?= e(implode("\n", (array) ($security['ip_allowlist'] ?? []))) ?></textarea>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-700">IP block list</label>
                            <textarea name="ip_blocklist" rows="4" placeholder="One IP per line" class="mt-1.5 w-full rounded-md border border-zinc-200 px-3 py-2 font-mono text-xs"><?= e(implode("\n", (array) ($security['ip_blocklist'] ?? []))) ?></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-zinc-100 pt-5">
                        <button type="submit" class="shadcn-btn shadcn-btn-primary">Save security</button>
                    </div>
                </form>
            </div>
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-6 py-5">
                    <h3 class="text-base font-semibold text-zinc-900">Login activity</h3>
                    <p class="mt-1 text-sm text-zinc-500">Recent sign-in attempts and security events.</p>
                </div>
                <div class="p-6">
                    <?php if ($loginActivity === []): ?>
                        <p class="text-sm text-zinc-500">No login activity recorded yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-zinc-500">
                                        <th class="pb-3 pr-4 font-medium">When</th>
                                        <th class="pb-3 pr-4 font-medium">Event</th>
                                        <th class="pb-3 pr-4 font-medium">Who</th>
                                        <th class="pb-3 font-medium">IP</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    <?php foreach ($loginActivity as $row): ?>
                                        <tr>
                                            <td class="py-3 pr-4 text-zinc-500 whitespace-nowrap"><?= e((string) ($row['created_at'] ?? '')) ?></td>
                                            <td class="py-3 pr-4">
                                                <?php if (($row['type'] ?? '') === 'audit'): ?>
                                                    <?= e((string) ($row['action'] ?? '')) ?>
                                                <?php else: ?>
                                                    <?= !empty($row['success']) ? 'Sign-in ok' : 'Sign-in failed' ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 pr-4 text-zinc-700">
                                                <?php if (($row['type'] ?? '') === 'audit'): ?>
                                                    <?= e((string) ($row['user_email'] ?? $row['user_name'] ?? '')) ?>
                                                <?php else: ?>
                                                    <?= e((string) ($row['identifier'] ?? '')) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 font-mono text-xs text-zinc-500"><?= e((string) ($row['ip_address'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- API Keys -->
        <div x-show="tab === 'api'" x-cloak class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-100 px-6 py-5">
                <h3 class="text-base font-semibold text-zinc-900">API keys</h3>
                <p class="mt-1 text-sm text-zinc-500">Authenticate programmatic access to forms and submissions.</p>
            </div>
            <div class="space-y-6 p-6">
                <?php if ($newApiKey): ?>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
                        <p class="font-medium text-amber-900">Copy this key now — it will not be shown again.</p>
                        <code class="mt-2 block break-all rounded-md bg-white px-3 py-2 font-mono text-xs text-zinc-800"><?= e((string) $newApiKey) ?></code>
                    </div>
                <?php endif; ?>
                <form method="post" action="/admin/settings/api-keys/generate" class="flex flex-col gap-2 sm:flex-row">
                    <?= csrf_field() ?>
                    <input type="text" name="key_name" placeholder="e.g. Production Zapier" required class="shadcn-input flex-1 bg-white">
                    <button type="submit" class="shadcn-btn shadcn-btn-primary">Generate key</button>
                </form>
                <div class="rounded-lg border border-zinc-100 bg-zinc-50 p-4 text-sm text-zinc-600">
                    <p class="font-medium text-zinc-900">How to call the API</p>
                    <p class="mt-1">Send <code class="rounded bg-white px-1">Authorization: Bearer ff_…</code> or <code class="rounded bg-white px-1">X-Api-Key</code>.</p>
                    <ul class="mt-3 space-y-1 font-mono text-xs">
                        <li>GET /api/v1/forms</li>
                        <li>GET /api/v1/submissions?form_id=&amp;page=</li>
                        <li>GET /api/v1/forms/{formId}/submissions/{id}</li>
                    </ul>
                </div>
                <?php if ($apiKeys === []): ?>
                    <p class="text-sm text-zinc-500">No API keys yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-zinc-500">
                                    <th class="pb-3 pr-4 font-medium">Name</th>
                                    <th class="pb-3 pr-4 font-medium">Prefix</th>
                                    <th class="pb-3 pr-4 font-medium">Created</th>
                                    <th class="pb-3 pr-4 font-medium">Last used</th>
                                    <th class="pb-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <?php foreach ($apiKeys as $key): ?>
                                    <tr>
                                        <td class="py-3 pr-4 font-medium text-zinc-900"><?= e((string) $key['name']) ?></td>
                                        <td class="py-3 pr-4 font-mono text-xs text-zinc-500"><?= e((string) $key['key_prefix']) ?>…</td>
                                        <td class="py-3 pr-4 text-zinc-500"><?= e((string) $key['created_at']) ?></td>
                                        <td class="py-3 pr-4 text-zinc-500"><?= e((string) ($key['last_used_at'] ?? '—')) ?></td>
                                        <td class="py-3">
                                            <?php if (!empty($key['revoked_at'])): ?>
                                                <span class="text-xs text-zinc-400">Revoked</span>
                                            <?php else: ?>
                                                <form method="post" action="/admin/settings/api-keys/revoke" class="inline" onsubmit="return confirm('Revoke this API key?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="key_id" value="<?= (int) $key['id'] ?>">
                                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Revoke</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Team -->
        <div x-show="tab === 'team'" x-cloak class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="border-b border-zinc-100 px-6 py-5">
                <h3 class="text-base font-semibold text-zinc-900">Team</h3>
                <p class="mt-1 text-sm text-zinc-500">Invite people by email. Links expire after 7 days.</p>
            </div>
            <div class="space-y-6 p-6">
                <form method="post" action="/admin/settings/team/invite" class="flex flex-col gap-2 sm:flex-row">
                    <?= csrf_field() ?>
                    <input type="email" name="email" required placeholder="teammate@example.com" class="shadcn-input flex-1 bg-white">
                    <select name="role" class="shadcn-input w-full bg-white sm:w-36">
                        <option value="viewer">Viewer</option>
                        <option value="editor" selected>Editor</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button type="submit" class="shadcn-btn shadcn-btn-primary">Send invite</button>
                </form>
                <?php if (is_string($inviteLink) && $inviteLink !== ''): ?>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm">
                        <p class="font-medium text-amber-900">Invite link (copy if email did not arrive)</p>
                        <code class="mt-2 block break-all rounded-md bg-white px-3 py-2 font-mono text-xs text-zinc-800"><?= e($inviteLink) ?></code>
                    </div>
                <?php endif; ?>
                <?php if ($teamMembers !== []): ?>
                    <div>
                        <h4 class="mb-3 text-sm font-semibold text-zinc-900">Members</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-zinc-500">
                                        <th class="pb-3 pr-4 font-medium">Name</th>
                                        <th class="pb-3 pr-4 font-medium">Email</th>
                                        <th class="pb-3 pr-4 font-medium">Role</th>
                                        <th class="pb-3 font-medium">Last login</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    <?php foreach ($teamMembers as $member): ?>
                                        <tr>
                                            <td class="py-3 pr-4 font-medium text-zinc-900"><?= e((string) ($member['name'] ?? '')) ?></td>
                                            <td class="py-3 pr-4 text-zinc-600"><?= e((string) ($member['email'] ?? '')) ?></td>
                                            <td class="py-3 pr-4 capitalize text-zinc-600"><?= e((string) ($member['role'] ?? '')) ?></td>
                                            <td class="py-3 text-zinc-500"><?= e((string) ($member['last_login_at'] ?? '—')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($teamInvites === []): ?>
                    <p class="text-sm text-zinc-500">No invites yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-zinc-100 text-xs uppercase tracking-wide text-zinc-500">
                                    <th class="pb-3 pr-4 font-medium">Email</th>
                                    <th class="pb-3 pr-4 font-medium">Role</th>
                                    <th class="pb-3 pr-4 font-medium">Expires</th>
                                    <th class="pb-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <?php foreach ($teamInvites as $inv): ?>
                                    <tr>
                                        <td class="py-3 pr-4 text-zinc-900"><?= e((string) $inv['email']) ?></td>
                                        <td class="py-3 pr-4 capitalize text-zinc-600"><?= e((string) $inv['role']) ?></td>
                                        <td class="py-3 pr-4 text-zinc-500"><?= e((string) $inv['expires_at']) ?></td>
                                        <td class="py-3">
                                            <?php if (!empty($inv['accepted_at'])): ?>
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Accepted</span>
                                            <?php else: ?>
                                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Backup -->
        <div x-show="tab === 'backup'" x-cloak class="space-y-6">
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-6 py-5">
                    <h3 class="text-base font-semibold text-zinc-900">Export</h3>
                    <p class="mt-1 text-sm text-zinc-500">Downloads live users, forms, submissions, notes, API keys, settings, and uploaded files (JSON).</p>
                </div>
                <div class="flex flex-wrap gap-3 p-6">
                    <a href="/admin/settings/backup/json" class="shadcn-btn shadcn-btn-primary gap-2">
                        <i data-lucide="file-json" class="h-4 w-4"></i>
                        Export JSON (recommended)
                    </a>
                    <a href="/admin/settings/backup/sql" class="shadcn-btn shadcn-btn-outline gap-2">
                        <i data-lucide="database" class="h-4 w-4"></i>
                        Export SQL
                    </a>
                </div>
            </div>
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="border-b border-zinc-100 px-6 py-5">
                    <h3 class="text-base font-semibold text-zinc-900">Import</h3>
                    <p class="mt-1 text-sm text-zinc-500">Restore from a JSON file exported here. This replaces current FormFlow data.</p>
                </div>
                <form method="post" action="/admin/settings/backup/import" enctype="multipart/form-data" class="space-y-4 p-6" onsubmit="return confirm('This will replace existing forms, submissions, and related data. Continue?')">
                    <?= csrf_field() ?>
                    <input type="file" name="backup" accept="application/json,.json" required class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white">
                    <p class="text-xs text-zinc-500">SQL files are for phpMyAdmin / mysql. In-app restore only accepts the JSON export.</p>
                    <button type="submit" class="shadcn-btn shadcn-btn-primary gap-2">
                        <i data-lucide="upload" class="h-4 w-4"></i>
                        Import JSON backup
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
