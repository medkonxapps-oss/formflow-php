<?php

declare(strict_types=1);

$settings = $state['settings'] ?? [];
$timezones = \DateTimeZone::listIdentifiers();
$languages = [
    'en' => 'English',
    'hi' => 'Hindi',
    'es' => 'Spanish',
    'fr' => 'French',
    'de' => 'German',
    'pt' => 'Portuguese',
    'ar' => 'Arabic',
    'ja' => 'Japanese',
    'zh' => 'Chinese',
];
?>

<p class="mb-6 text-sm text-zinc-600">Configure basic site settings. You can change these later in the dashboard.</p>

<form method="post" action="/install/settings" class="space-y-4">
    <?= csrf_field() ?>

    <div class="space-y-2">
        <label class="text-sm font-medium" for="site_name">Site name</label>
        <input id="site_name" name="site_name" type="text" required
            value="<?= e(old('site_name', $settings['site_name'] ?? 'FormFlow')) ?>"
            class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
    </div>

    <div class="space-y-2">
        <label class="text-sm font-medium" for="timezone">Timezone</label>
        <select id="timezone" name="timezone"
            class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
            <?php foreach ($timezones as $tz): ?>
                <option value="<?= e($tz) ?>" <?= (old('timezone', $settings['timezone'] ?? 'UTC') === $tz) ? 'selected' : '' ?>><?= e($tz) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="space-y-2">
        <label class="text-sm font-medium" for="locale">Default language</label>
        <select id="locale" name="locale"
            class="flex h-10 w-full rounded-md border border-zinc-300 px-3 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-zinc-900">
            <?php foreach ($languages as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= (old('locale', $settings['locale'] ?? 'en') === $code) ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="inline-flex h-10 w-full items-center justify-center rounded-md bg-zinc-900 text-sm font-medium text-white hover:bg-zinc-800">
        Continue
    </button>
</form>
