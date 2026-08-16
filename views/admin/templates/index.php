<?php

declare(strict_types=1);

use FormFlow\TemplateRepository;

$templates = new TemplateRepository($config);
$list = $templates->listAll();
$isEditor = in_array((string) ($currentUser['role'] ?? ''), ['admin', 'editor'], true);
?>

<div x-data="templateManager()">
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-zinc-900">Templates</h2>
        <p class="mt-1 text-sm text-zinc-500">Start from a pre-built form and customize it in the builder. <?= count($list) ?> templates across industries.</p>
    </div>

<?php if ($list === []): ?>
    <div class="rounded-xl border border-dashed border-zinc-300 bg-white p-12 text-center">
        <p class="text-zinc-600">No templates available. Re-run the installer, or <code class="rounded bg-zinc-100 px-1">php core/seed-templates.php</code>.</p>
    </div>
<?php else: ?>
    <?php
    $categories = [];
    foreach ($list as $tpl) {
        $cat = (string) ($tpl['category'] ?? 'general');
        $categories[$cat] = ($categories[$cat] ?? 0) + 1;
    }
    ksort($categories);
    ?>
    <div class="mb-6 flex flex-wrap gap-2">
        <button type="button" @click="filter = 'all'"
            :class="filter === 'all' ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700 border-zinc-200'"
            class="rounded-full border px-3 py-1.5 text-xs font-medium">All (<?= count($list) ?>)</button>
        <?php foreach ($categories as $cat => $count): ?>
            <button type="button" @click="filter = <?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>"
                :class="filter === <?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?> ? 'bg-zinc-900 text-white' : 'bg-white text-zinc-700 border-zinc-200'"
                class="rounded-full border px-3 py-1.5 text-xs font-medium"><?= e(str_replace('-', ' ', $cat)) ?> (<?= (int) $count ?>)</button>
        <?php endforeach; ?>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($list as $tpl): ?>
            <div x-show="filter === 'all' || filter === <?= htmlspecialchars(json_encode((string) $tpl['category']), ENT_QUOTES, 'UTF-8') ?>"
                class="flex flex-col rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-zinc-400"><?= e(str_replace('-', ' ', (string) $tpl['category'])) ?></span>
                <h3 class="mt-1 text-lg font-semibold text-zinc-900"><?= e((string) $tpl['name']) ?></h3>
                <p class="mt-2 flex-1 text-sm text-zinc-600"><?= e((string) ($tpl['description'] ?? '')) ?></p>
                <?php if ($isEditor): ?>
                    <div class="mt-4 flex gap-2">
                        <button type="button" @click="openPreview(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES, 'UTF-8') ?>)" class="flex-1 rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                            Preview
                        </button>
                        <form method="post" action="/admin/templates/use" class="flex-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="template_id" value="<?= (int) $tpl['id'] ?>">
                            <button type="submit" class="w-full rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800">
                                Use
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="mt-4 flex gap-2">
                        <button type="button" @click="openPreview(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES, 'UTF-8') ?>)" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                            Preview
                        </button>
                    </div>
                    <p class="mt-3 text-xs text-zinc-400 text-center">Editor role required to create forms.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <!-- Preview Modal -->
    <div x-show="previewOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="previewOpen" x-transition.opacity class="fixed inset-0 bg-zinc-900/50 backdrop-blur-sm transition-opacity" @click="previewOpen = false"></div>

            <!-- Modal panel -->
            <div x-show="previewOpen" x-transition class="inline-block transform rounded-xl bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle">
                <div class="border-b border-zinc-100 px-4 py-4 sm:flex sm:items-center sm:justify-between sm:px-6">
                    <h3 class="text-lg font-semibold leading-6 text-zinc-900" id="modal-title">
                        Preview: <span x-text="activeTemplate?.name"></span>
                    </h3>
                    <button type="button" class="ml-auto flex h-8 w-8 items-center justify-center rounded-md bg-white text-zinc-400 hover:bg-zinc-50 hover:text-zinc-500" @click="previewOpen = false">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
                
                <div class="px-4 py-5 sm:p-6 bg-zinc-50">
                    <div class="mx-auto max-w-xl bg-white p-6 shadow-sm ring-1 ring-zinc-900/5 sm:rounded-xl">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold tracking-tight text-zinc-900" x-text="activeTemplate?.name"></h2>
                            <p class="mt-1 text-sm text-zinc-500" x-text="activeTemplate?.description"></p>
                        </div>
                        
                        <div class="space-y-5">
                            <template x-for="field in getFields()" :key="field.id">
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700">
                                        <span x-text="field.label"></span>
                                        <template x-if="field.required">
                                            <span class="text-red-500 ml-1">*</span>
                                        </template>
                                    </label>
                                    
                                    <div class="mt-1.5">
                                        <!-- Text/Email/Number/Date/Time/Phone inputs -->
                                        <template x-if="['text', 'email', 'number', 'date', 'time', 'phone'].includes(field.type)">
                                            <input :type="field.type === 'phone' ? 'tel' : field.type" disabled class="block w-full rounded-md border-0 py-2 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 bg-zinc-50 sm:text-sm sm:leading-6" :placeholder="field.placeholder">
                                        </template>

                                        <!-- Textarea -->
                                        <template x-if="field.type === 'textarea'">
                                            <textarea disabled rows="3" class="block w-full rounded-md border-0 py-2 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 bg-zinc-50 sm:text-sm sm:leading-6" :placeholder="field.placeholder"></textarea>
                                        </template>

                                        <!-- Select -->
                                        <template x-if="field.type === 'select'">
                                            <select disabled class="block w-full rounded-md border-0 py-2 text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 bg-zinc-50 sm:text-sm sm:leading-6">
                                                <option value="">Select an option</option>
                                                <template x-for="opt in field.options" :key="opt.value">
                                                    <option x-text="opt.label"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <!-- Radio -->
                                        <template x-if="field.type === 'radio'">
                                            <div class="space-y-2 mt-2">
                                                <template x-for="opt in field.options" :key="opt.value">
                                                    <div class="flex items-center">
                                                        <input type="radio" disabled class="h-4 w-4 border-zinc-300 text-zinc-600">
                                                        <label class="ml-2 block text-sm text-zinc-700" x-text="opt.label"></label>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <!-- File -->
                                        <template x-if="field.type === 'file'">
                                            <div class="mt-1 flex justify-center rounded-lg border border-dashed border-zinc-300 px-6 py-6 bg-zinc-50">
                                                <div class="text-center">
                                                    <i data-lucide="upload-cloud" class="mx-auto h-8 w-8 text-zinc-300"></i>
                                                    <div class="mt-2 flex text-sm leading-6 text-zinc-600">
                                                        <span class="relative cursor-not-allowed rounded-md bg-transparent font-semibold text-zinc-600 focus-within:outline-none hover:text-zinc-500">
                                                            <span>Upload a file</span>
                                                        </span>
                                                        <p class="pl-1">or drag and drop</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <div class="mt-8">
                            <button type="button" disabled class="w-full rounded-md bg-zinc-900 px-3.5 py-2.5 text-center text-sm font-semibold text-white shadow-sm opacity-50 cursor-not-allowed">
                                Submit Form
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-zinc-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 rounded-b-xl border-t border-zinc-100">
                    <?php if ($isEditor): ?>
                        <form method="post" action="/admin/templates/use" class="m-0 sm:ml-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="template_id" :value="activeTemplate?.id">
                            <button type="submit" class="inline-flex w-full justify-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 sm:w-auto">
                                Use This Template
                            </button>
                        </form>
                    <?php endif; ?>
                    <button type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50 sm:mt-0 sm:w-auto" @click="previewOpen = false">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function templateManager() {
    return {
        filter: 'all',
        previewOpen: false,
        activeTemplate: null,
        openPreview(template) {
            this.activeTemplate = template;
            this.previewOpen = true;
            this.$nextTick(() => {
                if (window.lucide) {
                    lucide.createIcons();
                }
            });
        },
        getFields() {
            if (!this.activeTemplate || !this.activeTemplate.fields_json) {
                return [];
            }
            try {
                return typeof this.activeTemplate.fields_json === 'string' 
                    ? JSON.parse(this.activeTemplate.fields_json) 
                    : this.activeTemplate.fields_json;
            } catch (e) {
                return [];
            }
        }
    }
}
</script>
