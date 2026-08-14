<?php

declare(strict_types=1);

/** @var array<string, mixed> $form */
/** @var bool $isNew */
/** @var array{html: string, fetch: string, endpoint: string}|null $embed */

use FormFlow\FormDefaults;

$settings = is_array($form['settings'] ?? null) ? $form['settings'] : FormDefaults::settings();
$success = is_array($settings['success'] ?? null) ? $settings['success'] : [];
$notifications = is_array($settings['notifications'] ?? null) ? $settings['notifications'] : [];
$spam = is_array($settings['spam'] ?? null) ? $settings['spam'] : [];
$fieldsJson = json_encode($form['fields'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$fieldTypes = FormDefaults::FIELD_TYPES;
?>

<div x-data="formBuilder(<?= $fieldsJson ?>)">
    <form method="post" action="<?= $isNew ? '/admin/forms' : '/admin/forms/update' ?>" @submit="prepareSubmit">
        <?= csrf_field() ?>
        <?php if (!$isNew): ?>
            <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="fields_json" :value="JSON.stringify(fields)">
        <input type="hidden" name="settings_json" value="{}">

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-zinc-900"><?= $isNew ? 'New Form' : 'Edit Form' ?></h2>
                <?php if (!$isNew): ?>
                    <a href="/admin/forms/<?= (int) $form['id'] ?>/submissions" class="text-sm text-zinc-500 hover:underline">View submissions →</a>
                <?php endif; ?>
            </div>
            <button type="submit" class="inline-flex h-10 items-center rounded-md bg-zinc-900 px-4 text-sm font-medium text-white hover:bg-zinc-800">Save Form</button>
        </div>

        <div class="mb-4 flex gap-2 border-b border-zinc-200">
            <button type="button" @click="tab='fields'" :class="tab==='fields' ? 'border-b-2 border-zinc-900 text-zinc-900' : 'text-zinc-500'" class="px-3 py-2 text-sm font-medium">Fields</button>
            <button type="button" @click="tab='settings'" :class="tab==='settings' ? 'border-b-2 border-zinc-900 text-zinc-900' : 'text-zinc-500'" class="px-3 py-2 text-sm font-medium">Settings</button>
            <?php if (!$isNew && $embed !== null): ?>
                <button type="button" @click="tab='embed'" :class="tab==='embed' ? 'border-b-2 border-zinc-900 text-zinc-900' : 'text-zinc-500'" class="px-3 py-2 text-sm font-medium">Embed Code</button>
            <?php endif; ?>
        </div>

        <!-- Fields tab -->
        <div x-show="tab==='fields'" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="space-y-1 sm:col-span-2">
                    <label class="text-sm font-medium">Form name</label>
                    <input type="text" name="name" required value="<?= e((string) ($form['name'] ?? '')) ?>" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Status</label>
                    <select name="status" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                        <option value="active" <?= ($form['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="paused" <?= ($form['status'] ?? '') === 'paused' ? 'selected' : '' ?>>Paused</option>
                    </select>
                </div>
                <?php if (!$isNew): ?>
                <div class="space-y-1 sm:col-span-3">
                    <label class="text-sm font-medium">Slug</label>
                    <input type="text" name="slug" value="<?= e((string) ($form['slug'] ?? '')) ?>" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-wrap gap-2">
                <?php foreach ($fieldTypes as $type): ?>
                    <button type="button" @click="addField('<?= e($type) ?>')" class="rounded-md border border-zinc-200 bg-white px-2 py-1 text-xs hover:bg-zinc-50">+ <?= e($type) ?></button>
                <?php endforeach; ?>
            </div>

            <template x-if="fields.length === 0">
                <p class="rounded-lg border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">Add fields using the buttons above.</p>
            </template>

            <div class="space-y-3">
                <template x-for="(field, index) in fields" :key="field.id">
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500" x-text="field.type"></span>
                            <div class="flex gap-1">
                                <button type="button" @click="moveField(index,-1)" class="rounded border px-2 text-xs" :disabled="index===0">↑</button>
                                <button type="button" @click="moveField(index,1)" class="rounded border px-2 text-xs" :disabled="index===fields.length-1">↓</button>
                                <button type="button" @click="fields.splice(index,1)" class="rounded border border-red-200 px-2 text-xs text-red-600">Remove</button>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="text-xs text-zinc-500">Label</label><input type="text" x-model="field.label" class="mt-1 h-9 w-full rounded border px-2 text-sm"></div>
                            <div x-show="!['heading','paragraph','hidden','single-checkbox'].includes(field.type)"><label class="text-xs text-zinc-500">Placeholder</label><input type="text" x-model="field.placeholder" class="mt-1 h-9 w-full rounded border px-2 text-sm"></div>
                            <div x-show="['select','radio','checkbox'].includes(field.type)" class="sm:col-span-2">
                                <label class="text-xs text-zinc-500">Options (label:value per line)</label>
                                <textarea x-model="field._optionsText" @input="syncOptions(field)" rows="2" class="mt-1 w-full rounded border px-2 text-sm"></textarea>
                            </div>
                            <div x-show="!['heading','paragraph'].includes(field.type)"><label class="text-xs text-zinc-500">Default value</label><input type="text" x-model="field.default" class="mt-1 h-9 w-full rounded border px-2 text-sm"></div>
                            <div x-show="!['heading','paragraph'].includes(field.type)" class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="field.required"> Required</label></div>
                            <div x-show="['text','textarea','email'].includes(field.type)"><label class="text-xs text-zinc-500">Min length</label><input type="number" x-model.number="field.validation.min_length" class="mt-1 h-9 w-full rounded border px-2 text-sm"></div>
                            <div x-show="['text','textarea','email'].includes(field.type)"><label class="text-xs text-zinc-500">Max length</label><input type="number" x-model.number="field.validation.max_length" class="mt-1 h-9 w-full rounded border px-2 text-sm"></div>
                            <div x-show="['text','textarea'].includes(field.type)" class="sm:col-span-2"><label class="text-xs text-zinc-500">Regex pattern</label><input type="text" x-model="field.validation.regex" class="mt-1 h-9 w-full rounded border px-2 text-sm"></div>
                            <div x-show="['text','textarea'].includes(field.type)" class="sm:col-span-2"><label class="text-xs text-zinc-500">Custom error message</label><input type="text" x-model="field.validation.error_message" class="mt-1 h-9 w-full rounded border px-2 text-sm"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Settings tab -->
        <div x-show="tab==='settings'" x-cloak class="space-y-6">
            <fieldset class="space-y-3 rounded-lg border border-zinc-200 p-4">
                <legend class="px-1 text-sm font-semibold">Success behavior</legend>
                <div class="flex gap-4">
                    <label class="text-sm"><input type="radio" name="success_type" value="message" <?= ($success['type'] ?? 'message') !== 'redirect' ? 'checked' : '' ?>> Inline message</label>
                    <label class="text-sm"><input type="radio" name="success_type" value="redirect" <?= ($success['type'] ?? '') === 'redirect' ? 'checked' : '' ?>> Redirect URL</label>
                </div>
                <input type="text" name="success_message" value="<?= e((string) ($success['message'] ?? '')) ?>" placeholder="Thank you message" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                <input type="url" name="success_redirect_url" value="<?= e((string) ($success['redirect_url'] ?? '')) ?>" placeholder="https://example.com/thanks" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
            </fieldset>

            <fieldset class="space-y-3 rounded-lg border border-zinc-200 p-4">
                <legend class="px-1 text-sm font-semibold">Notifications</legend>
                <input type="text" name="notification_recipients" value="<?= e(implode(', ', (array) ($notifications['recipients'] ?? []))) ?>" placeholder="email1@example.com, email2@example.com" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                <input type="text" name="notification_subject" value="<?= e((string) ($notifications['subject'] ?? 'New form submission')) ?>" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                <label class="text-sm"><input type="checkbox" name="auto_reply" value="1" <?= !empty($notifications['auto_reply']) ? 'checked' : '' ?>> Auto-reply to submitter</label>
            </fieldset>

            <fieldset class="space-y-3 rounded-lg border border-zinc-200 p-4">
                <legend class="px-1 text-sm font-semibold">Spam protection</legend>
                <label class="text-sm"><input type="checkbox" name="honeypot" value="1" <?= ($spam['honeypot'] ?? true) ? 'checked' : '' ?>> Honeypot field (recommended)</label>
                <label class="text-sm block"><input type="checkbox" name="recaptcha" value="1" <?= !empty($spam['recaptcha']) ? 'checked' : '' ?>> Enable reCAPTCHA</label>
                <input type="text" name="recaptcha_site_key" value="<?= e((string) ($spam['recaptcha_site_key'] ?? '')) ?>" placeholder="reCAPTCHA site key" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                <input type="text" name="recaptcha_secret_key" value="<?= e((string) ($spam['recaptcha_secret_key'] ?? '')) ?>" placeholder="reCAPTCHA secret key" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
            </fieldset>

            <fieldset class="space-y-3 rounded-lg border border-zinc-200 p-4">
                <legend class="px-1 text-sm font-semibold">Integrations</legend>
                <input type="text" name="allowed_domains" value="<?= e(implode(', ', (array) ($settings['allowed_domains'] ?? []))) ?>" placeholder="Allowed CORS domains (comma-separated)" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
                <input type="url" name="webhook_url" value="<?= e((string) ($settings['webhook_url'] ?? '')) ?>" placeholder="Webhook URL" class="h-10 w-full rounded-md border border-zinc-300 px-3 text-sm">
            </fieldset>
        </div>

        <?php if (!$isNew && $embed !== null): ?>
        <div x-show="tab==='embed'" x-cloak class="space-y-4">
            <div>
                <label class="text-sm font-medium">Endpoint</label>
                <input type="text" readonly value="<?= e($embed['endpoint']) ?>" class="mt-1 h-10 w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 text-sm font-mono">
            </div>
            <div>
                <label class="text-sm font-medium">HTML form snippet</label>
                <textarea readonly rows="10" class="mt-1 w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 font-mono text-xs"><?= e($embed['html']) ?></textarea>
            </div>
            <div>
                <label class="text-sm font-medium">JavaScript fetch() example</label>
                <textarea readonly rows="10" class="mt-1 w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 font-mono text-xs"><?= e($embed['fetch']) ?></textarea>
            </div>
            <p class="text-xs text-zinc-500">Submissions are accepted at <code><?= e($embed['endpoint']) ?></code>. See the project README for integration examples.</p>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function formBuilder(initialFields) {
    const defaults = <?= json_encode(FormDefaults::field('text'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const fields = (initialFields || []).map(f => {
        if (['select','radio','checkbox'].includes(f.type) && f.options) {
            f._optionsText = f.options.map(o => `${o.label}:${o.value}`).join('\n');
        }
        return f;
    });
    return {
        tab: 'fields',
        fields,
        addField(type) {
            const f = JSON.parse(JSON.stringify(defaults));
            f.id = 'f_' + Math.random().toString(16).slice(2, 10);
            f.type = type;
            f.label = type.charAt(0).toUpperCase() + type.slice(1).replace('-', ' ');
            f.required = !['heading','paragraph','hidden'].includes(type);
            if (['select','radio','checkbox'].includes(type)) {
                f.options = [{label:'Option 1', value:'option_1'}];
                f._optionsText = 'Option 1:option_1';
            }
            this.fields.push(f);
        },
        moveField(index, dir) {
            const n = index + dir;
            if (n < 0 || n >= this.fields.length) return;
            const t = this.fields[n];
            this.fields[n] = this.fields[index];
            this.fields[index] = t;
        },
        syncOptions(field) {
            field.options = (field._optionsText || '').split('\n').filter(Boolean).map(line => {
                const [label, value] = line.split(':');
                return { label: (label||'').trim(), value: (value||label||'').trim() };
            });
        },
        prepareSubmit() {
            this.fields.forEach(f => { if (f._optionsText !== undefined) this.syncOptions(f); delete f._optionsText; });
        }
    };
}
</script>
