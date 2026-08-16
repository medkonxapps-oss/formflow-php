<?php

declare(strict_types=1);

/** @var array<string, mixed> $form */
/** @var bool $isNew */
/** @var array{html: string, fetch: string, endpoint: string}|null $embed */

use FormFlow\FormDefaults;

$settings      = is_array($form['settings'] ?? null) ? $form['settings'] : FormDefaults::settings();
$success       = is_array($settings['success'] ?? null) ? $settings['success'] : [];
$notifications = is_array($settings['notifications'] ?? null) ? $settings['notifications'] : [];
$spam          = is_array($settings['spam'] ?? null) ? $settings['spam'] : [];
$formId        = (int) ($form['id'] ?? 0);
$formName      = (string) ($form['name'] ?? 'Untitled Form');
$formSlug      = (string) ($form['slug'] ?? '');
$formStatus    = (string) ($form['status'] ?? 'paused');
$baseUrl       = rtrim((string) ($config['app']['url'] ?? ''), '/');
$abVariants    = [];
if (!$isNew) {
    try {
        $abVariants = (new \FormFlow\AbTestRepository($config))->getVariantsForForm($formId);
    } catch (Throwable $e) {
        $abVariants = [];
    }
}

$initialFields = $form['fields'] ?? [];
if ($isNew && $initialFields === []) {
    $initialFields = [
        array_merge(FormDefaults::field('text'),     ['id' => 'f_name',    'label' => 'Full Name',     'placeholder' => 'John Doe',              'required' => true]),
        array_merge(FormDefaults::field('email'),    ['id' => 'f_email',   'label' => 'Email Address',  'placeholder' => 'john@example.com',      'required' => true]),
        array_merge(FormDefaults::field('text'),     ['id' => 'f_subject', 'label' => 'Subject',        'placeholder' => 'How can we help?',      'required' => false]),
        array_merge(FormDefaults::field('textarea'), ['id' => 'f_message', 'label' => 'Message',        'placeholder' => 'Write your message...', 'required' => true]),
    ];
}

$theme          = is_array($settings['theme'] ?? null) ? $settings['theme'] : (FormDefaults::settings()['theme'] ?? []);
$abTestEnabled  = !empty($settings['ab_test']['enabled']);

$fieldsJson      = json_encode($initialFields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$fieldDefaultsJson = json_encode(FormDefaults::field('text'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$builderConfig   = json_encode([
    'isNew'        => $isNew,
    'formId'       => $formId,
    'formName'     => $formName,
    'formSlug'     => $formSlug,
    'formStatus'   => $formStatus,
    'baseUrl'      => $baseUrl,
    'hasEmbed'     => $embed !== null,
    'theme'        => $theme,
    'abTestEnabled'=> $abTestEnabled,
    'abVariants'   => $abVariants,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<script>
window.__FF_FIELD_DEFAULTS = <?= $fieldDefaultsJson ?: '{}' ?>;
window.__FF_FIELD_DEFAULTS.theme = <?= json_encode($theme, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.__FF_BUILDER = {
    fields: <?= $fieldsJson ?: '[]' ?>,
    config: <?= $builderConfig ?: '{}' ?>
};
</script>

<div class="flex h-screen flex-col" style="font-family:'Inter',system-ui,sans-serif;"
     x-data="visualFormBuilder(window.__FF_BUILDER.fields, window.__FF_BUILDER.config)"
     x-init="init()">

  <form id="form-builder-form" method="post"
        action="<?= $isNew ? '/admin/forms' : '/admin/forms/update' ?>"
        @submit="prepareSubmit"
        class="flex h-full flex-col">
    <?= csrf_field() ?>
    <?php if (!$isNew): ?>
      <input type="hidden" name="form_id" value="<?= $formId ?>">
    <?php endif; ?>
    <input type="hidden" name="fields_json"   :value="JSON.stringify(fields)">
    <input type="hidden" name="settings_json" value="{}">
    <input type="hidden" name="name"          :value="formName">
    <input type="hidden" name="slug"          :value="formSlug">
    <input type="hidden" name="status"        :value="formStatus">
    <input type="hidden" name="ab_test_enabled" id="ab-test-input" value="">
    <input type="hidden" name="variants_json" id="variants-json-input" value="">

    <!-- ═══════════════════════ TOAST ═══════════════════════ -->
    <div x-show="toast" x-cloak x-transition
         class="builder-toast fixed bottom-6 left-1/2 z-[100] flex items-center gap-2 -translate-x-1/2 rounded-xl px-5 py-2.5 text-sm font-medium text-white shadow-2xl"
         :class="toastClass()">
      <span x-text="toast"></span>
    </div>

    <!-- ═══════════════════════ HEADER ═══════════════════════ -->
    <header class="builder-header relative flex h-14 shrink-0 items-center justify-between border-b border-white/8 bg-[#141722]/95 px-4 backdrop-blur-xl z-30">
      <div class="flex min-w-0 items-center gap-3">
        <!-- Back -->
        <a href="/admin/forms"
           class="builder-toolbar-btn border border-white/8 rounded-lg">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          Back
        </a>

        <div class="h-4 w-px bg-white/10"></div>

        <!-- Form name -->
        <input type="text" x-model="formName"
               class="builder-form-name-input"
               placeholder="Form name…">

        <!-- Status badge -->
        <span class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider"
              :class="formStatus === 'active' ? 'builder-status-live' : 'builder-status-draft'"
              x-text="formStatus === 'active' ? 'Live' : 'Draft'">
        </span>

        <!-- Slug -->
        <span class="hidden text-xs text-white/30 sm:inline font-mono"
              x-show="formSlug" x-text="'/' + formSlug"></span>
      </div>

      <!-- CENTER: view mode + zoom -->
      <div class="hidden items-center gap-2 md:flex">
        <!-- View mode -->
        <div class="flex items-center gap-0.5 rounded-lg border border-white/8 bg-white/4 p-1">
          <button type="button" @click="viewMode='desktop'"
                  :class="viewMode==='desktop' ? 'bg-white/15 text-white' : 'text-white/40'"
                  class="builder-toolbar-btn rounded-md px-2 py-1.5 text-xs" title="Desktop">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          </button>
          <button type="button" @click="viewMode='tablet'"
                  :class="viewMode==='tablet' ? 'bg-white/15 text-white' : 'text-white/40'"
                  class="builder-toolbar-btn rounded-md px-2 py-1.5 text-xs" title="Tablet">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </button>
          <button type="button" @click="viewMode='mobile'"
                  :class="viewMode==='mobile' ? 'bg-white/15 text-white' : 'text-white/40'"
                  class="builder-toolbar-btn rounded-md px-2 py-1.5 text-xs" title="Mobile">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </button>
        </div>

        <!-- Zoom -->
        <div class="builder-zoom-controls">
          <button type="button" @click="zoomOut()" class="builder-zoom-btn" title="Zoom out">−</button>
          <span class="builder-zoom-label" x-text="zoom + '%'"></span>
          <button type="button" @click="zoomIn()"  class="builder-zoom-btn" title="Zoom in">+</button>
          <button type="button" @click="zoomReset()" class="builder-zoom-btn text-[9px]" title="Reset zoom">⟳</button>
        </div>

        <div class="h-4 w-px bg-white/10"></div>

        <!-- A/B Test -->
        <label class="flex cursor-pointer items-center gap-2 text-[11px] text-white/40" title="Track form variants">
          <span>A/B</span>
          <div class="builder-toggle" :class="abTestEnabled ? 'on' : ''" @click="abTestEnabled = !abTestEnabled; document.getElementById('ab-test-input').value = abTestEnabled ? '1' : ''; if(abTestEnabled) rightTab='abtest';"></div>
        </label>
      </div>

      <!-- RIGHT: actions -->
      <div class="flex items-center gap-1.5">
        <!-- Undo / Redo with badge -->
        <button type="button" @click="undo()" :disabled="historyBack <= 0"
                class="builder-toolbar-btn relative disabled:opacity-25" title="Undo (Ctrl+Z)">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2M3 10l4-4M3 10l4 4"/></svg>
          <span x-show="historyBack > 0" class="builder-history-badge" x-text="historyBack"></span>
        </button>
        <button type="button" @click="redo()" :disabled="historyFwd <= 0"
                class="builder-toolbar-btn relative disabled:opacity-25" title="Redo (Ctrl+Y)">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 00-5 5v2M21 10l-4-4M21 10l-4 4"/></svg>
          <span x-show="historyFwd > 0" class="builder-history-badge" x-text="historyFwd"></span>
        </button>

        <div class="h-4 w-px bg-white/10 hidden sm:block"></div>

        <!-- Auto-save indicator -->
        <span class="hidden sm:flex items-center gap-1.5 text-[10px] text-white/30">
          <svg x-show="saving" class="builder-saving h-3 w-3" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="30" stroke-dashoffset="10"/></svg>
          <svg x-show="!saving && savedAt" class="h-3 w-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span x-show="saving">Saving…</span>
          <span x-show="!saving && savedAt" x-text="'Saved ' + savedAt"></span>
        </span>

        <div class="h-4 w-px bg-white/10 hidden sm:block"></div>

        <!-- Shortcuts -->
        <button type="button" @click="showShortcuts=true" class="builder-toolbar-btn" title="Keyboard shortcuts (?)">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M6 11h1M9 11h1M12 11h1M15 11h1M18 11h1M6 15h4M15 15h3"/></svg>
        </button>

        <template x-if="!isNew && formSlug">
          <a :href="baseUrl + '/preview/' + formSlug" target="_blank" class="builder-toolbar-btn hidden sm:flex">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Live
          </a>
        </template>

        <button type="button" @click="openPreview()" class="builder-toolbar-btn hidden sm:flex">
          Preview
        </button>

        <button type="button" @click="rightTab='settings'; showEmbedPanel=true"
                x-show="hasEmbed" class="builder-toolbar-btn hidden sm:flex">
          Embed
        </button>

        <template x-if="!isNew">
          <a :href="'/admin/forms/' + formId + '/analytics'" class="builder-toolbar-btn hidden sm:flex">Analytics</a>
        </template>

        <button type="submit" class="builder-btn-save">Save</button>

        <button type="button" @click="togglePublish()" class="builder-btn-publish"
                x-text="formStatus === 'active' ? '⏸ Unpublish' : '▶ Publish'"></button>
      </div>
    </header>

    <!-- ═══════════════════════ MAIN BODY ═══════════════════════ -->
    <div class="flex min-h-0 flex-1 overflow-hidden">

      <!-- ════════ LEFT SIDEBAR ════════ -->
      <aside class="builder-sidebar-left builder-scroll flex w-56 shrink-0 flex-col overflow-y-auto bg-[#141722] lg:w-64">

        <!-- Tabs: Blocks / Layers -->
        <div class="flex border-b border-white/8 bg-[#0d0f1a]/50">
          <button type="button" @click="leftTab='blocks'"
                  :class="leftTab==='blocks' ? 'active' : ''"
                  class="builder-tab-btn flex-1">Blocks</button>
          <button type="button" @click="leftTab='layers'"
                  :class="leftTab==='layers' ? 'active' : ''"
                  class="builder-tab-btn flex-1">
            Layers
            <span x-show="fields.length > 0"
                  class="ml-1 inline-flex h-4 px-1.5 min-w-[16px] items-center justify-center rounded-full bg-white/10 text-[9px] font-bold text-white/60"
                  x-text="fields.length"></span>
          </button>
        </div>

        <!-- ── BLOCKS panel ── -->
        <div x-show="leftTab==='blocks'" class="flex flex-1 flex-col">
          <!-- Search -->
          <div class="relative p-3">
            <svg class="absolute left-5.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-white/25 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
            <input type="search" x-model="blockSearch" placeholder="Search fields…"
                   class="builder-search" style="padding-left:30px">
          </div>

          <div class="flex-1 overflow-y-auto px-3 pb-4 space-y-4">
            <!-- Basic -->
            <template x-if="filteredBlocks('basic').length > 0">
              <div>
                <span class="builder-category-label">Basic</span>
                <div class="grid grid-cols-2 gap-2">
                  <template x-for="block in filteredBlocks('basic')" :key="block.key">
                    <button type="button"
                            draggable="true"
                            @dragstart="onBlockDragStart($event, block)"
                            @dragend="onBlockDragEnd()"
                            @click="addBlock(block)"
                            class="builder-block-btn flex flex-col items-center gap-2 p-3 text-center">
                      <span class="builder-block-icon text-lg" x-text="block.icon"></span>
                      <span class="text-[11px] font-medium text-white/60" x-text="block.label"></span>
                    </button>
                  </template>
                </div>
              </div>
            </template>

            <!-- Advanced -->
            <template x-if="filteredBlocks('advanced').length > 0">
              <div>
                <span class="builder-category-label">Advanced</span>
                <div class="grid grid-cols-2 gap-2">
                  <template x-for="block in filteredBlocks('advanced')" :key="block.key">
                    <button type="button"
                            draggable="true"
                            @dragstart="onBlockDragStart($event, block)"
                            @dragend="onBlockDragEnd()"
                            @click="addBlock(block)"
                            class="builder-block-btn flex flex-col items-center gap-2 p-3 text-center">
                      <span class="builder-block-icon text-lg" x-text="block.icon"></span>
                      <span class="text-[11px] font-medium text-white/60" x-text="block.label"></span>
                    </button>
                  </template>
                </div>
              </div>
            </template>

            <!-- Layout -->
            <template x-if="filteredBlocks('layout').length > 0">
              <div>
                <span class="builder-category-label">Layout</span>
                <div class="grid grid-cols-2 gap-2">
                  <template x-for="block in filteredBlocks('layout')" :key="block.key">
                    <button type="button"
                            draggable="true"
                            @dragstart="onBlockDragStart($event, block)"
                            @dragend="onBlockDragEnd()"
                            @click="addBlock(block)"
                            class="builder-block-btn flex flex-col items-center gap-2 p-3 text-center">
                      <span class="builder-block-icon text-lg" x-text="block.icon"></span>
                      <span class="text-[11px] font-medium text-white/60" x-text="block.label"></span>
                    </button>
                  </template>
                </div>
              </div>
            </template>

            <!-- No results -->
            <template x-if="filteredBlocks('basic').length === 0 && filteredBlocks('advanced').length === 0 && filteredBlocks('layout').length === 0">
              <p class="py-8 text-center text-xs text-white/25">No blocks match "<span x-text="blockSearch"></span>"</p>
            </template>
          </div>

          <!-- Footer -->
          <div class="border-t border-white/8 p-3 text-center text-[10px] text-white/25">
            <span x-text="fields.length + ' field' + (fields.length !== 1 ? 's' : '')"></span>
            · Click or drag to add
          </div>
        </div>

        <!-- ── LAYERS panel ── -->
        <div x-show="leftTab==='layers'" x-cloak class="flex flex-1 flex-col">
          <!-- Layer search -->
          <div class="relative p-3">
            <svg class="absolute left-5.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-white/25 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/></svg>
            <input type="search" x-model="layerSearch" placeholder="Find field…"
                   class="builder-search" style="padding-left:30px">
          </div>

          <!-- Multi-select bar -->
          <div x-show="selectedIds.length > 0" x-cloak class="builder-multiselect-bar mx-3 mb-2">
            <span x-text="selectedIds.length + ' selected'"></span>
            <button type="button" @click="bulkDelete()" class="ml-auto text-red-400 hover:text-red-300 text-xs font-medium">🗑 Delete</button>
            <button type="button" @click="clearMultiSelect()" class="text-white/40 hover:text-white text-xs">✕</button>
          </div>

          <div class="flex-1 overflow-y-auto px-2 pb-4" id="builder-layers-list">
            <template x-if="filteredLayers.length === 0">
              <p class="py-8 text-center text-xs text-white/25">No fields yet.</p>
            </template>
            <template x-for="field in filteredLayers" :key="field.id">
              <div @click="jumpToField(field._idx)"
                   @click.ctrl.stop="toggleMultiSelect($event, field.id)"
                   @click.meta.stop="toggleMultiSelect($event, field.id)"
                   :class="[
                     selectedIndex === field._idx ? 'active' : '',
                     isMultiSelected(field.id) ? 'builder-field-multi-selected' : ''
                   ]"
                   class="builder-layer-item"
                   :data-field-id="field.id">
                <span class="builder-layer-drag cursor-grab" title="Drag to reorder">⠿</span>
                <span class="text-[10px] text-white/25 w-4 text-right shrink-0" x-text="field._idx + 1"></span>
                <span class="text-base shrink-0" x-text="blocks.find(b=>b.type===field.type)?.icon || '📋'"></span>
                <span class="flex-1 min-w-0 truncate text-xs text-white/70" x-text="field.label || field.type"></span>
                <span x-show="field.conditional?.enabled" class="text-[9px] text-amber-400 shrink-0" title="Has logic">⚡</span>
                <span class="text-[9px] uppercase text-white/20 shrink-0" x-text="field.type"></span>
              </div>
            </template>
          </div>
        </div>
      </aside>

      <!-- ════════ CANVAS ════════ -->
      <main class="builder-scroll builder-canvas-bg relative flex flex-1 flex-col overflow-y-auto"
            @dragover="onCanvasDragOver($event)"
            @drop="onCanvasDrop($event)"
            @click="contextMenu.show=false">

        <div class="relative z-10 flex flex-1 flex-col p-6 pt-8">
          <div class="mx-auto w-full transition-all duration-300"
               :class="{
                 'builder-canvas-desktop': viewMode==='desktop',
                 'builder-canvas-tablet':  viewMode==='tablet',
                 'builder-canvas-mobile':  viewMode==='mobile'
               }">

            <!-- Zoom wrapper -->
            <div :style="zoomStyle()">

              <!-- Form card -->
              <div class="builder-form-card p-6 md:p-8">

                <div x-show="editingVariantIndex >= 0" x-cloak class="mb-4 flex items-center justify-between rounded-lg border border-violet-500/40 bg-violet-500/15 px-3 py-2">
                  <p class="text-xs text-violet-200">Editing variant: <span class="font-semibold" x-text="abVariants[editingVariantIndex]?.name"></span></p>
                  <button type="button" @click="doneEditAbVariant()" class="rounded-md bg-violet-600 px-2 py-1 text-[11px] font-medium text-white">Done</button>
                </div>

                <!-- Form title -->
                <h2 class="mb-1 text-center text-xl font-bold text-white" x-text="formName || 'Untitled Form'"></h2>
                <p class="mb-6 text-center text-xs text-white/30" x-show="formSlug" x-text="'/' + formSlug"></p>

                <!-- Multi-step progress bar -->
                <div x-show="totalSteps > 1" class="mb-4">
                  <div class="builder-progress-bar">
                    <div class="builder-progress-fill" :style="{ width: ((canvasStep / totalSteps) * 100) + '%' }"></div>
                  </div>
                  <!-- Step tabs -->
                  <div class="flex flex-wrap justify-center gap-2">
                    <template x-for="s in stepNumbers" :key="'step-'+s">
                      <button type="button" @click="canvasStep = s"
                              :class="canvasStep === s ? 'active' : ''"
                              class="builder-step-tab">
                        <span x-text="'Step ' + s"></span>
                        <span class="ml-1 opacity-50 text-[9px]"
                              x-text="'(' + fields.filter(f=>(f.step||1)===s).length + ')'"></span>
                      </button>
                    </template>
                    <!-- Add step -->
                    <button type="button" @click="addStep()"
                            class="builder-step-tab"
                            title="Add step">+ Step</button>
                    <button type="button" @click="removeStep()" x-show="totalSteps > 1"
                            class="builder-step-tab text-red-400" title="Remove current step">− Step</button>
                  </div>
                </div>

                <!-- Empty canvas -->
                <template x-if="fields.filter(f=>(f.step||1)===canvasStep).length === 0">
                  <div class="builder-empty-canvas"
                       :class="isDraggingBlock ? 'drag-over' : ''">
                    <div class="builder-empty-icon text-4xl mb-3">🎯</div>
                    <p class="text-sm font-semibold text-white/30">Drag fields here</p>
                    <p class="mt-1 text-xs text-white/20">or click any block in the left panel</p>
                    <!-- Starter templates -->
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                      <button type="button" @click="addBlock({type:'text',label:'Name',icon:'✏️',category:'basic'},true)"
                              class="builder-template-card text-xs text-white/40 px-3 py-2">
                        👤 Add Name
                      </button>
                      <button type="button" @click="addBlock({type:'email',label:'Email',icon:'📧',category:'basic'},true)"
                              class="builder-template-card text-xs text-white/40 px-3 py-2">
                        📧 Add Email
                      </button>
                      <button type="button" @click="addBlock({type:'textarea',label:'Message',icon:'📝',category:'basic'},true)"
                              class="builder-template-card text-xs text-white/40 px-3 py-2">
                        💬 Add Message
                      </button>
                    </div>
                  </div>
                </template>

                <!-- Fields list -->
                <div id="builder-canvas-fields">
                  <template x-for="(field, loopIdx) in canvasFields" :key="field.id">
                    <div>
                      <!-- Drop zone BEFORE this field -->
                      <div class="builder-drop-zone"
                           :class="dropZoneActive === loopIdx ? 'active' : ''"
                           @dragover.stop="onDropZoneOver($event, loopIdx - 1)"
                           @dragleave="onDropZoneLeave()"
                           @drop.stop="onDropZoneDrop($event, loopIdx - 1)">
                      </div>

                      <!-- Field card -->
                      <div @click.stop="selectFieldById(field.id); toggleMultiSelect($event, field.id)"
                           @contextmenu="openContextMenu($event, fields.findIndex(f=>f.id===field.id))"
                           :data-field-id="field.id"
                           :class="[
                             'builder-canvas-field group',
                             selectedIndex === fields.findIndex(f=>f.id===field.id) ? 'builder-field-selected' : '',
                             isMultiSelected(field.id) ? 'builder-field-multi-selected' : '',
                             field.width === 'half' ? 'inline-block align-top w-[calc(50%-6px)]' : 'block w-full'
                           ]"
                           class="mb-1 p-4 transition-all duration-100">

                        <!-- Drag handle -->
                        <span class="builder-drag-handle absolute left-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity"
                              title="Drag to reorder">⠿</span>

                        <!-- Field actions -->
                        <div class="builder-field-actions">
                          <button type="button" @click.stop="moveField(fields.findIndex(f=>f.id===field.id), -1)"
                                  class="builder-field-action-btn" title="Move up">↑</button>
                          <button type="button" @click.stop="moveField(fields.findIndex(f=>f.id===field.id), 1)"
                                  class="builder-field-action-btn" title="Move down">↓</button>
                          <button type="button" @click.stop="duplicateField(fields.findIndex(f=>f.id===field.id))"
                                  class="builder-field-action-btn" title="Duplicate (Ctrl+D)">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                          </button>
                          <button type="button" @click.stop="removeField(fields.findIndex(f=>f.id===field.id))"
                                  class="builder-field-action-btn danger" title="Delete">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                          </button>
                        </div>

                        <!-- ── Field preview rendering ── -->
                        <div class="pl-5 pr-2">

                          <!-- Heading -->
                          <template x-if="field.type === 'heading'">
                            <div>
                              <div class="mb-1 flex items-center gap-2">
                                <span class="text-[10px] text-white/25 font-mono">H</span>
                                <div class="flex-1 h-px bg-white/8"></div>
                              </div>
                              <h3 class="text-lg font-bold text-white" x-text="field.label || 'Heading'"></h3>
                            </div>
                          </template>

                          <!-- Paragraph -->
                          <template x-if="field.type === 'paragraph'">
                            <p class="text-sm text-white/50 leading-relaxed" x-text="field.label || 'Paragraph text goes here...'"></p>
                          </template>

                          <!-- Divider -->
                          <template x-if="field.type === 'divider'">
                            <div class="py-2">
                              <div class="builder-divider-preview"></div>
                            </div>
                          </template>

                          <!-- Image -->
                          <template x-if="field.type === 'image'">
                            <div>
                              <div class="builder-image-preview">
                                <span>🖼️</span>
                                <span x-text="field.image_url ? 'Image set' : 'Set image URL in properties'"></span>
                              </div>
                            </div>
                          </template>

                          <!-- Custom HTML -->
                          <template x-if="field.type === 'html'">
                            <div class="builder-html-preview">
                              &lt;!-- Custom HTML block --&gt;
                            </div>
                          </template>

                          <!-- Video -->
                          <template x-if="field.type === 'video'">
                            <div class="builder-video-preview">
                              <span class="builder-video-icon">▶️</span>
                              <span x-text="field.video_url ? field.video_url : 'No video URL set'"></span>
                            </div>
                          </template>

                          <!-- All other fields -->
                          <template x-if="!['heading','paragraph','divider','image','hidden','html','video'].includes(field.type)">
                            <div>
                              <!-- Label row -->
                              <div class="mb-2 flex items-center gap-2">
                                <label class="block text-sm font-medium text-white/80" :style="labelStyle(field)">
                                  <span x-text="field.label"></span>
                                  <span x-show="field.required" class="text-red-400 ml-0.5">*</span>
                                </label>
                                <span x-show="field.conditional?.enabled" class="text-[10px] text-amber-400" title="Has conditional logic">⚡ Logic</span>
                              </div>

                              <!-- Help text -->
                              <p x-show="field.help_text" class="mb-2 text-xs text-white/35" x-text="field.help_text"></p>

                              <!-- Textarea -->
                              <template x-if="field.type === 'textarea'">
                                <div class="builder-field-preview-textarea" x-text="field.placeholder || 'Enter text here…'"></div>
                              </template>

                              <!-- Select -->
                              <template x-if="field.type === 'select'">
                                <div class="builder-field-preview-select">
                                  <span x-text="'Select an option…'"></span>
                                  <span>▾</span>
                                </div>
                              </template>

                              <!-- Radio -->
                              <template x-if="field.type === 'radio'">
                                <div class="space-y-1.5">
                                  <template x-for="opt in (field.options || [])" :key="opt.value">
                                    <div class="flex items-center gap-2.5 text-sm text-white/40">
                                      <span class="h-4 w-4 rounded-full border border-white/20 shrink-0"></span>
                                      <span x-text="opt.label"></span>
                                    </div>
                                  </template>
                                </div>
                              </template>

                              <!-- Checkbox -->
                              <template x-if="field.type === 'checkbox'">
                                <div class="space-y-1.5">
                                  <template x-for="opt in (field.options || [])" :key="opt.value">
                                    <div class="flex items-center gap-2.5 text-sm text-white/40">
                                      <span class="h-4 w-4 rounded border border-white/20 shrink-0"></span>
                                      <span x-text="opt.label"></span>
                                    </div>
                                  </template>
                                </div>
                              </template>

                              <!-- Single checkbox -->
                              <template x-if="field.type === 'single-checkbox'">
                                <div class="flex items-center gap-2.5 text-sm text-white/40">
                                  <span class="h-4 w-4 rounded border border-white/20 shrink-0"></span>
                                  <span x-text="field.label"></span>
                                </div>
                              </template>

                              <!-- File upload -->
                              <template x-if="field.type === 'file'">
                                <div class="builder-field-preview-input border-dashed justify-center gap-2">
                                  <span>📎</span>
                                  <span>Choose file…</span>
                                </div>
                              </template>

                              <!-- Rating -->
                              <template x-if="field.type === 'rating'">
                                <div class="builder-rating-preview">
                                  <template x-for="i in (field.max_rating || 5)" :key="i">
                                    <span>⭐</span>
                                  </template>
                                </div>
                              </template>

                              <!-- Range -->
                              <template x-if="field.type === 'range'">
                                <div>
                                  <div class="builder-range-preview">
                                    <div class="builder-range-fill"></div>
                                    <div class="builder-range-thumb"></div>
                                  </div>
                                  <div class="mt-1 flex justify-between text-[10px] text-white/25">
                                    <span x-text="field.range_min ?? 0"></span>
                                    <span x-text="field.range_max ?? 100"></span>
                                  </div>
                                </div>
                              </template>

                              <!-- Signature -->
                              <template x-if="field.type === 'signature'">
                                <div class="builder-signature-preview">
                                  <span>✍️ Sign here</span>
                                </div>
                              </template>

                              <!-- Matrix -->
                              <template x-if="field.type === 'matrix'">
                                <div class="overflow-x-auto rounded-lg border border-white/8">
                                  <table class="builder-matrix-preview w-full">
                                    <thead>
                                      <tr>
                                        <th class="text-left"></th>
                                        <template x-for="col in (field.matrix_cols || [])" :key="col">
                                          <th x-text="col"></th>
                                        </template>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <template x-for="row in (field.matrix_rows || [])" :key="row">
                                        <tr>
                                          <td x-text="row"></td>
                                          <template x-for="col in (field.matrix_cols || [])" :key="col">
                                            <td>
                                              <span class="inline-block h-3 w-3 rounded-full border border-white/15"></span>
                                            </td>
                                          </template>
                                        </tr>
                                      </template>
                                    </tbody>
                                  </table>
                                </div>
                              </template>

                              <!-- Address -->
                              <template x-if="field.type === 'address'">
                                <div class="builder-address-grid">
                                  <div class="builder-address-full builder-field-preview-input">Street Address</div>
                                  <div class="builder-address-full builder-field-preview-input">Address Line 2</div>
                                  <div class="builder-field-preview-input">City</div>
                                  <div class="builder-field-preview-input">State / Province</div>
                                  <div class="builder-field-preview-input">Zip / Postal Code</div>
                                  <div class="builder-field-preview-input">Country</div>
                                </div>
                              </template>

                              <!-- NPS -->
                              <template x-if="field.type === 'nps'">
                                <div>
                                  <div class="builder-nps-scale">
                                    <template x-for="i in 11" :key="i">
                                      <div class="builder-nps-btn" x-text="i - 1"></div>
                                    </template>
                                  </div>
                                  <div class="builder-nps-labels">
                                    <span>Not likely at all</span>
                                    <span>Extremely likely</span>
                                  </div>
                                </div>
                              </template>

                              <!-- Toggle -->
                              <template x-if="field.type === 'toggle'">
                                <div class="flex items-center gap-3 text-sm text-white/40">
                                  <div class="builder-switch"></div>
                                  <span x-text="field.label"></span>
                                </div>
                              </template>

                              <!-- Color -->
                              <template x-if="field.type === 'color'">
                                <div class="builder-color-preview-wrapper">
                                  <div class="builder-color-swatch"></div>
                                  <span class="builder-color-text">#7c5cff</span>
                                </div>
                              </template>

                              <!-- Default input (text, email, number, date, etc.) -->
                              <template x-if="!['textarea','select','radio','checkbox','single-checkbox','file','rating','range','signature','matrix','address','nps','color','toggle'].includes(field.type)">
                                <div class="builder-field-preview-input" x-text="field.placeholder || 'Enter value…'"></div>
                              </template>
                            </div>
                          </template>

                          <!-- Hidden field badge -->
                          <template x-if="field.type === 'hidden'">
                            <div class="flex items-center gap-2 rounded-lg border border-dashed border-white/10 bg-white/3 px-3 py-2">
                              <span class="text-sm">👁️</span>
                              <span class="text-xs text-white/30">Hidden field</span>
                              <span class="ml-auto font-mono text-[10px] text-white/20" x-text="field.id"></span>
                            </div>
                          </template>
                        </div>
                      </div><!-- /.builder-canvas-field -->

                    </div><!-- /field wrapper -->
                  </template>

                  <!-- Drop zone at the very end -->
                  <div class="builder-drop-zone"
                       :class="dropZoneActive === canvasFields.length ? 'active' : ''"
                       @dragover.stop="onDropZoneOver($event, canvasFields.length - 1)"
                       @dragleave="onDropZoneLeave()"
                       @drop.stop="onDropZoneDrop($event, canvasFields.length - 1)">
                  </div>
                </div><!-- /#builder-canvas-fields -->

                <!-- Add field zone (click) -->
                <div class="builder-add-field-zone mt-2" @click="leftTab='blocks'">
                  <span>＋ Click to add a field, or drag from the panel</span>
                </div>

                <!-- Submit button -->
                <button type="button"
                        class="builder-preview-submit mt-6"
                        :style="buttonStyle()"
                        x-text="theme.button_text || 'Submit Form'">
                </button>

                <!-- Footer info -->
                <p class="mt-3 text-center text-[10px] text-white/20">
                  <span x-text="fields.length + ' field' + (fields.length !== 1 ? 's' : '')"></span>
                  · <span x-text="totalSteps + ' step' + (totalSteps !== 1 ? 's' : '')"></span>
                </p>
              </div><!-- /.builder-form-card -->
            </div><!-- /.builder-zoom-wrapper -->
          </div>
        </div>
      </main>

      <!-- ════════ RIGHT SIDEBAR: Properties ════════ -->
      <aside class="builder-sidebar-right builder-scroll flex w-72 shrink-0 flex-col overflow-y-auto bg-[#141722] lg:w-80">

        <!-- Tabs -->
        <div class="flex border-b border-white/8 bg-[#0d0f1a]/50 overflow-x-auto">
          <button type="button" @click="rightTab='field'; showEmbedPanel=false"
                  :class="rightTab==='field' && !showEmbedPanel ? 'active' : ''"
                  class="builder-tab-btn">Field</button>
          <button type="button" @click="rightTab='logic'; showEmbedPanel=false"
                  :class="rightTab==='logic' ? 'active' : ''"
                  class="builder-tab-btn">Logic</button>
          <button type="button" @click="rightTab='style'; showEmbedPanel=false"
                  :class="rightTab==='style' ? 'active' : ''"
                  class="builder-tab-btn">Style</button>
          <button type="button" @click="rightTab='settings'; showEmbedPanel=false"
                  :class="rightTab==='settings' && !showEmbedPanel ? 'active' : ''"
                  class="builder-tab-btn">Settings</button>
          <button type="button" @click="rightTab='abtest'; showEmbedPanel=false"
                  :class="rightTab==='abtest' && !showEmbedPanel ? 'active' : ''"
                  class="builder-tab-btn whitespace-nowrap">A/B Test</button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-5">

          <!-- ── FIELD PROPERTIES ── -->
          <div x-show="rightTab==='field' && !showEmbedPanel && selectedField" x-cloak>

            <!-- Field type badge -->
            <div class="mb-4 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-lg" x-text="blocks.find(b=>b.type===selectedField.type)?.icon || '📋'"></span>
                <div>
                  <p class="text-sm font-semibold text-white" x-text="fieldTypeLabel(selectedField.type)"></p>
                  <p class="font-mono text-[10px] text-white/25" x-text="'#' + selectedField.id"></p>
                </div>
              </div>
              <div class="flex gap-1">
                <button type="button" @click="moveField(selectedIndex,-1)" :disabled="selectedIndex===0"
                        class="builder-field-action-btn disabled:opacity-25" title="Move up">↑</button>
                <button type="button" @click="moveField(selectedIndex,1)" :disabled="selectedIndex>=fields.length-1"
                        class="builder-field-action-btn disabled:opacity-25" title="Move down">↓</button>
              </div>
            </div>

            <!-- ── Content section ── -->
            <div>
              <div class="builder-prop-section">Content</div>
              <div class="space-y-3">

                <div>
                  <label class="mb-1 block text-xs text-white/40">Field ID</label>
                  <input type="text" x-model="selectedField.id" class="builder-input font-mono text-xs">
                </div>

                <div x-show="!['divider'].includes(selectedField.type)">
                  <label class="mb-1 block text-xs text-white/40">Label</label>
                  <input type="text" x-model="selectedField.label" class="builder-input">
                </div>

                <div x-show="!['heading','paragraph','hidden','radio','checkbox','single-checkbox','divider','image','rating','range','signature','matrix','html','video'].includes(selectedField.type)">
                  <label class="mb-1 block text-xs text-white/40">Placeholder</label>
                  <input type="text" x-model="selectedField.placeholder" class="builder-input">
                </div>

                <div x-show="['hidden','text','email','number','url','phone'].includes(selectedField.type)">
                  <label class="mb-1 block text-xs text-white/40">Default Value</label>
                  <input type="text" x-model="selectedField.default" class="builder-input">
                </div>

                <div x-show="selectedField.type === 'file'">
                  <label class="mb-1 block text-xs text-white/40">Accepted types</label>
                  <input type="text" x-model="selectedField.validation.accept" placeholder=".pdf,.jpg,.png" class="builder-input font-mono text-xs">
                </div>

                <div x-show="!['heading','paragraph','divider','image','html','video'].includes(selectedField.type)">
                  <label class="mb-1 block text-xs text-white/40">Help Text</label>
                  <input type="text" x-model="selectedField.help_text" placeholder="Optional hint…" class="builder-input">
                </div>

                <!-- Options for select/radio/checkbox -->
                <div x-show="['select','radio','checkbox'].includes(selectedField.type)">
                  <label class="mb-1 block text-xs text-white/40">Options <span class="text-white/25">(label:value per line)</span></label>
                  <textarea x-model="selectedField._optionsText" @input="syncOptions(selectedField)"
                            rows="4" class="builder-textarea font-mono text-xs"></textarea>
                </div>

                <!-- Rating max stars -->
                <div x-show="selectedField.type === 'rating'">
                  <label class="mb-1 block text-xs text-white/40">Max stars</label>
                  <input type="number" min="3" max="10" x-model.number="selectedField.max_rating" class="builder-input">
                </div>

                <!-- Range config -->
                <div x-show="selectedField.type === 'range'" class="grid grid-cols-3 gap-2">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Min</label>
                    <input type="number" x-model.number="selectedField.range_min" class="builder-input">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Max</label>
                    <input type="number" x-model.number="selectedField.range_max" class="builder-input">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Step</label>
                    <input type="number" min="1" x-model.number="selectedField.range_step" class="builder-input">
                  </div>
                </div>

                <!-- Matrix rows/cols -->
                <div x-show="selectedField.type === 'matrix'" class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Rows (one per line)</label>
                    <textarea :value="matrixRowsText(selectedField)"
                              @input="syncMatrixRows(selectedField, $event.target.value)"
                              rows="3" class="builder-textarea font-mono text-xs"></textarea>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Columns</label>
                    <textarea :value="matrixColsText(selectedField)"
                              @input="syncMatrixCols(selectedField, $event.target.value)"
                              rows="3" class="builder-textarea font-mono text-xs"></textarea>
                  </div>
                </div>

                <!-- Image URL -->
                <div x-show="selectedField.type === 'image'">
                  <label class="mb-1 block text-xs text-white/40">Image URL</label>
                  <input type="url" x-model="selectedField.image_url" placeholder="https://…" class="builder-input">
                </div>

                <!-- Video URL -->
                <div x-show="selectedField.type === 'video'">
                  <label class="mb-1 block text-xs text-white/40">Video URL (YouTube/Vimeo)</label>
                  <input type="url" x-model="selectedField.video_url" placeholder="https://youtube.com/…" class="builder-input">
                </div>

                <!-- Custom HTML -->
                <div x-show="selectedField.type === 'html'">
                  <label class="mb-1 block text-xs text-white/40">HTML Code</label>
                  <textarea x-model="selectedField.html_content" rows="6" class="builder-textarea font-mono text-[10px]"></textarea>
                </div>
              </div>
            </div>

            <!-- ── Step / Behaviour / Layout ── -->
            <div class="mt-4 space-y-4">
              <!-- Step -->
              <div>
                <div class="builder-prop-section">Step</div>
                <div class="flex items-center gap-2">
                  <input type="number" min="1" x-model.number="selectedField.step" class="builder-input w-20 shrink-0">
                  <span class="text-xs text-white/30">of <span x-text="totalSteps"></span></span>
                </div>
              </div>

              <!-- Behaviour -->
              <div x-show="!['heading','paragraph','divider','image','html','video'].includes(selectedField.type)">
                <div class="builder-prop-section">Behaviour</div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-white/70">Required</span>
                  <div class="builder-toggle" :class="selectedField.required ? 'on' : ''" @click="selectedField.required = !selectedField.required"></div>
                </div>
              </div>

              <!-- Layout width -->
              <div>
                <div class="builder-prop-section">Layout</div>
                <div class="flex gap-2">
                  <button type="button" @click="selectedField.width='full'"
                          :class="selectedField.width !== 'half' ? 'active' : ''"
                          class="builder-width-btn">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2" stroke-width="2"/></svg>
                    Full
                  </button>
                  <button type="button" @click="selectedField.width='half'"
                          :class="selectedField.width === 'half' ? 'active' : ''"
                          class="builder-width-btn">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="6" width="8" height="12" rx="2" stroke-width="2"/><rect x="13" y="6" width="8" height="12" rx="2" stroke-width="2"/></svg>
                    Half
                  </button>
                </div>
              </div>
            </div>
          </div><!-- /field tab -->

          <!-- No field selected -->
          <div x-show="rightTab==='field' && !selectedField" x-cloak
               class="flex flex-col items-center justify-center py-16 text-center">
            <div class="text-4xl mb-3 opacity-30">👆</div>
            <p class="text-sm text-white/30">Click a field on the canvas</p>
            <p class="mt-1 text-xs text-white/20">to edit its properties</p>
          </div>

          <!-- ── LOGIC ── -->
          <div x-show="rightTab==='logic' && selectedField" x-cloak>

            <!-- Validation -->
            <div class="mb-5">
              <div class="builder-prop-section">Validation</div>
              <div class="space-y-3" x-show="!['heading','paragraph','divider','image'].includes(selectedField?.type)">

                <div class="grid grid-cols-2 gap-2"
                     x-show="['text','textarea','email','url','phone'].includes(selectedField.type)">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Min length</label>
                    <input type="number" min="0" x-model.number="selectedField.validation.min_length" class="builder-input">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Max length</label>
                    <input type="number" min="0" x-model.number="selectedField.validation.max_length" class="builder-input">
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2" x-show="selectedField.type === 'number'">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Min value</label>
                    <input type="number" x-model.number="selectedField.validation.min_value" class="builder-input">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Max value</label>
                    <input type="number" x-model.number="selectedField.validation.max_value" class="builder-input">
                  </div>
                </div>

                <div x-show="['text','textarea','email','url','phone'].includes(selectedField.type)">
                  <label class="mb-1 block text-xs text-white/40">Regex pattern</label>
                  <input type="text" x-model="selectedField.validation.regex"
                         placeholder="e.g. ^[A-Z]+$" class="builder-input font-mono text-xs">
                  <!-- Regex tester -->
                  <div x-show="selectedField.validation.regex" class="mt-2">
                    <input type="text" x-model="regexTestValue" placeholder="Test a value…"
                           class="builder-input text-xs mb-1">
                    <div x-show="regexTestValue"
                         class="builder-regex-result"
                         :class="regexValid === 'valid' ? 'valid' : (regexValid === 'invalid' ? 'invalid' : 'invalid')"
                         x-text="regexValid === 'valid' ? '✓ Pattern matches' : (regexValid === 'error' ? '⚠ Invalid regex' : '✗ No match')">
                    </div>
                  </div>
                </div>

                <div>
                  <label class="mb-1 block text-xs text-white/40">Custom error message</label>
                  <input type="text" x-model="selectedField.validation.error_message" class="builder-input">
                </div>
              </div>
            </div>

            <!-- Conditional Logic -->
            <div>
              <div class="builder-prop-section">Conditional Logic</div>
              <div class="mb-3 flex items-center justify-between">
                <span class="text-sm text-white/70">Enable conditions</span>
                <div class="builder-toggle" :class="selectedField.conditional.enabled ? 'on' : ''"
                     @click="selectedField.conditional.enabled = !selectedField.conditional.enabled"></div>
              </div>
              <div x-show="selectedField.conditional.enabled" class="space-y-3">
                <div class="flex gap-2">
                  <select x-model="selectedField.conditional.action" class="builder-select">
                    <option value="show">Show field when</option>
                    <option value="hide">Hide field when</option>
                  </select>
                  <select x-model="selectedField.conditional.match" class="builder-select w-24 shrink-0">
                    <option value="all">ALL</option>
                    <option value="any">ANY</option>
                  </select>
                </div>

                <template x-for="(rule, ri) in (selectedField.conditional.rules || [])" :key="ri">
                  <div class="builder-rule-card space-y-2">
                    <select x-model="rule.field_id" class="builder-select">
                      <template x-for="sf in logicSourceFields()" :key="sf.id">
                        <option :value="sf.id" x-text="sf.label"></option>
                      </template>
                    </select>
                    <div class="flex gap-1.5">
                      <select x-model="rule.operator" class="builder-select flex-1">
                        <template x-for="op in operators" :key="op.value">
                          <option :value="op.value" x-text="op.label"></option>
                        </template>
                      </select>
                      <input x-show="!['not_empty','empty'].includes(rule.operator)"
                             type="text" x-model="rule.value" placeholder="value"
                             class="builder-input w-20 shrink-0">
                      <button type="button" @click="removeConditionalRule(ri)"
                              class="builder-field-action-btn danger shrink-0">✕</button>
                    </div>
                  </div>
                </template>

                <button type="button" @click="addConditionalRule()"
                        class="builder-add-field-zone !mt-0 !py-2">
                  + Add rule
                </button>
              </div>
            </div>
          </div><!-- /logic tab -->

          <!-- ── STYLE ── -->
          <div x-show="rightTab==='style'" x-cloak class="space-y-5">

            <!-- Per-field style -->
            <div x-show="selectedField">
              <div class="builder-prop-section">Field Style</div>
              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-xs text-white/40">CSS Class</label>
                  <input type="text" x-model="selectedField.style.css_class" placeholder="my-class" class="builder-input font-mono text-xs">
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-white/70">Bold label</span>
                  <div class="builder-toggle" :class="selectedField.style.label_bold ? 'on' : ''"
                       @click="selectedField.style.label_bold = !selectedField.style.label_bold"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Label color</label>
                    <input type="color" x-model="selectedField.style.text_color" class="builder-input h-9 p-1">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Background</label>
                    <input type="color" x-model="selectedField.style.bg_color" class="builder-input h-9 p-1">
                  </div>
                </div>
              </div>
            </div>

            <hr class="border-white/8">

            <!-- Advanced field options -->
            <div x-show="selectedField && !['heading','paragraph','divider','image','hidden'].includes(selectedField?.type)">
              <div class="builder-prop-section">Advanced Field</div>
              <div class="space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-white/70">Read-only</span>
                  <div class="builder-toggle" :class="selectedField.advanced?.readonly ? 'on' : ''"
                       @click="selectedField.advanced.readonly = !selectedField.advanced.readonly"></div>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-white/70">Copy on click</span>
                  <div class="builder-toggle" :class="selectedField.advanced?.copy_on_click ? 'on' : ''"
                       @click="selectedField.advanced.copy_on_click = !selectedField.advanced.copy_on_click"></div>
                </div>
                <div>
                  <label class="mb-1 block text-xs text-white/40">Autocomplete</label>
                  <select x-model="selectedField.advanced.autocomplete" class="builder-select">
                    <option value="on">On</option>
                    <option value="off">Off</option>
                    <option value="name">Name</option>
                    <option value="email">Email</option>
                    <option value="tel">Phone</option>
                    <option value="url">URL</option>
                  </select>
                </div>
                <div x-show="!['textarea','select','radio','checkbox','file','rating','range','signature','matrix'].includes(selectedField.type)" class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Prefix</label>
                    <input type="text" x-model="selectedField.advanced.prefix" placeholder="$, @, +" class="builder-input">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Suffix</label>
                    <input type="text" x-model="selectedField.advanced.suffix" placeholder=".com, kg" class="builder-input">
                  </div>
                </div>
              </div>
            </div>

            <hr class="border-white/8">

            <!-- Form theme -->
            <div>
              <div class="builder-prop-section">Form Theme</div>
              <div class="space-y-3">
                <div>
                  <label class="mb-1 block text-xs text-white/40">Submit button text</label>
                  <input type="text" name="theme_button_text" x-model="theme.button_text" class="builder-input">
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Button color</label>
                    <input type="color" name="theme_button_color" x-model="theme.button_color" class="builder-input h-9 p-1">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Background</label>
                    <input type="color" name="theme_background_color" x-model="theme.background_color" class="builder-input h-9 p-1">
                  </div>
                </div>
                <div>
                  <label class="mb-1 block text-xs text-white/40">Label color</label>
                  <input type="color" name="theme_label_color" x-model="theme.label_color" class="builder-input h-9 p-1">
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Border radius (px)</label>
                    <input type="number" min="0" max="32" name="theme_border_radius" x-model="theme.border_radius" class="builder-input">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-white/40">Max width (px)</label>
                    <input type="number" min="320" max="1200" name="theme_max_width" x-model="theme.max_width" class="builder-input">
                  </div>
                </div>
                <div>
                  <label class="mb-1 block text-xs text-white/40">Font family</label>
                  <select name="theme_font_family" x-model="theme.font_family" class="builder-select">
                    <option value="Inter, sans-serif">Inter</option>
                    <option value="inherit">System default</option>
                    <option value="Georgia, serif">Georgia</option>
                    <option value="'Courier New', monospace">Monospace</option>
                    <option value="'Roboto', sans-serif">Roboto</option>
                    <option value="'Poppins', sans-serif">Poppins</option>
                  </select>
                </div>
              </div>
            </div>
          </div><!-- /style tab -->

          <!-- ── SETTINGS ── -->
          <div x-show="rightTab==='settings' || showEmbedPanel" x-cloak class="space-y-5">

            <!-- Embed panel -->
            <div x-show="showEmbedPanel && hasEmbed" class="space-y-3">
              <div class="builder-prop-section">Share & embed</div>
              <?php if ($embed !== null): ?>
              <div class="flex flex-wrap gap-1">
                <button type="button" @click="embedKind='link'" :class="embedKind==='link' ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/60'" class="rounded-md px-2 py-1 text-[11px]">Link</button>
                <button type="button" @click="embedKind='iframe'" :class="embedKind==='iframe' ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/60'" class="rounded-md px-2 py-1 text-[11px]">iFrame</button>
                <button type="button" @click="embedKind='html'" :class="embedKind==='html' ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/60'" class="rounded-md px-2 py-1 text-[11px]">HTML</button>
                <button type="button" @click="embedKind='popup'" :class="embedKind==='popup' ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/60'" class="rounded-md px-2 py-1 text-[11px]">Popup</button>
                <button type="button" @click="embedKind='script'" :class="embedKind==='script' ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/60'" class="rounded-md px-2 py-1 text-[11px]">JS</button>
                <button type="button" @click="embedKind='api'" :class="embedKind==='api' ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/60'" class="rounded-md px-2 py-1 text-[11px]">API</button>
              </div>

              <div x-show="embedKind==='link'">
                <p class="mb-1 text-[11px] text-white/40">Hosted form page — share this URL.</p>
                <div class="flex gap-1.5">
                  <input type="text" readonly id="embed-hosted" value="<?= e($embed['hosted']) ?>" class="builder-input min-w-0 flex-1 font-mono text-xs">
                  <button type="button" @click="copyEmbedById('embed-hosted')" class="builder-btn-publish shrink-0 text-xs px-2">Copy</button>
                </div>
                <a href="<?= e($embed['hosted']) ?>" target="_blank" rel="noopener" class="mt-1 inline-block text-[11px] text-violet-400 hover:underline">Open preview</a>
              </div>

              <div x-show="embedKind==='iframe'" x-cloak>
                <p class="mb-1 text-[11px] text-white/40">Paste on a page that can reach this FormFlow URL.</p>
                <?php
                $hostedHost = strtolower((string) (parse_url((string) ($embed['hosted'] ?? ''), PHP_URL_HOST) ?? ''));
                $iframeIsLocal = $hostedHost === 'localhost' || $hostedHost === '127.0.0.1' || str_ends_with($hostedHost, '.local') || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $hostedHost) === 1;
                ?>
                <?php if ($iframeIsLocal): ?>
                <p class="mb-2 rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-1.5 text-[11px] text-amber-200">Browsers block a live/public website from loading localhost in an iframe. Test below on this admin, or set your public site URL in Settings → General, then copy iframe again.</p>
                <?php endif; ?>
                <textarea readonly id="embed-iframe-code" rows="4" class="builder-embed-code"><?= e($embed['iframe']) ?></textarea>
                <button type="button" @click="copyEmbedById('embed-iframe-code')" class="builder-add-field-zone mt-1 w-full !py-2 text-xs">Copy iframe</button>
                <p class="mt-2 text-[11px] text-white/40">Same-origin preview (works here):</p>
                <iframe src="/preview/<?= e($formSlug) ?>" title="Form preview" class="mt-1 w-full rounded-md bg-white" style="height:280px;border:0"></iframe>
              </div>

              <div x-show="embedKind==='html'" x-cloak>
                <p class="mb-1 text-[11px] text-white/40">Inline HTML form. Posts to your FormFlow endpoint.</p>
                <textarea readonly id="embed-html-code" rows="6" class="builder-embed-code"><?= e($embed['html']) ?></textarea>
                <button type="button" @click="copyEmbedById('embed-html-code')" class="builder-add-field-zone mt-1 w-full !py-2 text-xs">Copy HTML</button>
              </div>

              <div x-show="embedKind==='popup'" x-cloak>
                <p class="mb-1 text-[11px] text-white/40">Button that opens the form in a modal overlay.</p>
                <textarea readonly id="embed-popup-code" rows="6" class="builder-embed-code"><?= e($embed['popup']) ?></textarea>
                <button type="button" @click="copyEmbedById('embed-popup-code')" class="builder-add-field-zone mt-1 w-full !py-2 text-xs">Copy popup</button>
              </div>

              <div x-show="embedKind==='script'" x-cloak>
                <p class="mb-1 text-[11px] text-white/40">Drop a placeholder + script; iframe is injected automatically.</p>
                <textarea readonly id="embed-script-code" rows="6" class="builder-embed-code"><?= e($embed['script']) ?></textarea>
                <button type="button" @click="copyEmbedById('embed-script-code')" class="builder-add-field-zone mt-1 w-full !py-2 text-xs">Copy script</button>
              </div>

              <div x-show="embedKind==='api'" x-cloak class="space-y-3">
                <div>
                  <label class="mb-1 block text-xs text-white/40">POST endpoint</label>
                  <div class="flex gap-1.5">
                    <input type="text" readonly id="embed-endpoint" value="<?= e($embed['endpoint']) ?>" class="builder-input min-w-0 flex-1 font-mono text-xs">
                    <button type="button" @click="copyEmbedById('embed-endpoint')" class="builder-btn-publish shrink-0 text-xs px-2">Copy</button>
                  </div>
                </div>
                <div>
                  <label class="mb-1 block text-xs text-white/40">JavaScript fetch</label>
                  <textarea readonly id="embed-fetch-code" rows="8" class="builder-embed-code"><?= e($embed['fetch']) ?></textarea>
                  <button type="button" @click="copyEmbedById('embed-fetch-code')" class="builder-add-field-zone mt-1 w-full !py-2 text-xs">Copy fetch</button>
                </div>
                <div>
                  <label class="mb-1 block text-xs text-white/40">cURL</label>
                  <textarea readonly id="embed-curl-code" rows="5" class="builder-embed-code"><?= e($embed['curl']) ?></textarea>
                  <button type="button" @click="copyEmbedById('embed-curl-code')" class="builder-add-field-zone mt-1 w-full !py-2 text-xs">Copy cURL</button>
                </div>
              </div>
              <?php endif; ?>
              <button type="button" @click="showEmbedPanel=false"
                      class="text-xs text-violet-400 hover:underline">← Back to settings</button>
              <hr class="border-white/8">
            </div>

            <!-- URL slug -->
            <?php if (!$isNew): ?>
            <div>
              <div class="builder-prop-section">URL</div>
              <div>
                <label class="mb-1 block text-xs text-white/40">Slug</label>
                <input type="text" x-model="formSlug" @input="slugManual = true" class="builder-input font-mono text-xs">
              </div>
            </div>
            <?php endif; ?>

            <!-- Success -->
            <div>
              <div class="builder-prop-section">Success</div>
              <div class="space-y-2">
                <div class="flex gap-3 text-xs">
                  <label class="flex items-center gap-1.5 text-white/60 cursor-pointer">
                    <input type="radio" name="success_type" value="message"
                           <?= ($success['type'] ?? 'message') !== 'redirect' ? 'checked' : '' ?>
                           class="accent-violet-500"> Message
                  </label>
                  <label class="flex items-center gap-1.5 text-white/60 cursor-pointer">
                    <input type="radio" name="success_type" value="redirect"
                           <?= ($success['type'] ?? '') === 'redirect' ? 'checked' : '' ?>
                           class="accent-violet-500"> Redirect
                  </label>
                </div>
                <input type="text" name="success_message"
                       value="<?= e((string) ($success['message'] ?? '')) ?>"
                       placeholder="Thank you message" class="builder-input">
                <input type="url" name="success_redirect_url"
                       value="<?= e((string) ($success['redirect_url'] ?? '')) ?>"
                       placeholder="https://example.com/thanks" class="builder-input">
              </div>
            </div>

            <!-- Notifications -->
            <div>
              <div class="builder-prop-section">Notifications</div>
              <div class="space-y-2">
                <input type="text" name="notification_recipients"
                       value="<?= e(implode(', ', (array) ($notifications['recipients'] ?? []))) ?>"
                       placeholder="email@example.com" class="builder-input">
                <input type="text" name="notification_subject"
                       value="<?= e((string) ($notifications['subject'] ?? 'New form submission')) ?>"
                       class="builder-input">
              </div>
            </div>

            <!-- Spam protection -->
            <div>
              <div class="builder-prop-section">Spam Protection</div>
              <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-white/60 cursor-pointer">
                  <input type="checkbox" name="honeypot" value="1"
                         <?= ($spam['honeypot'] ?? true) ? 'checked' : '' ?>
                         class="accent-violet-500 rounded">
                  Honeypot
                </label>
                <label class="flex items-center gap-2 text-sm text-white/60 cursor-pointer">
                  <input type="checkbox" name="recaptcha" value="1"
                         <?= !empty($spam['recaptcha']) ? 'checked' : '' ?>
                         class="accent-violet-500 rounded">
                  reCAPTCHA
                </label>
                <input type="text" name="recaptcha_site_key"
                       value="<?= e((string) ($spam['recaptcha_site_key'] ?? '')) ?>"
                       placeholder="Site key" class="builder-input font-mono text-xs">
                <input type="text" name="recaptcha_secret_key"
                       value="<?= e((string) ($spam['recaptcha_secret_key'] ?? '')) ?>"
                       placeholder="Secret key" class="builder-input font-mono text-xs">
              </div>
            </div>

            <!-- Integrations -->
            <div>
              <div class="builder-prop-section">Integrations</div>
              <div class="space-y-2">
                <input type="text" name="allowed_domains"
                       value="<?= e(implode(', ', (array) ($settings['allowed_domains'] ?? []))) ?>"
                       placeholder="CORS domains" class="builder-input">
                <input type="url" name="webhook_url"
                       value="<?= e((string) ($settings['webhook_url'] ?? '')) ?>"
                       placeholder="Webhook URL" class="builder-input">
              </div>
            </div>
          </div><!-- /settings tab -->

          <!-- ── A/B TEST ── -->
          <div x-show="rightTab==='abtest' && !showEmbedPanel" x-cloak class="space-y-5">
            <div>
              <div class="builder-prop-section">A/B Testing</div>
              <div class="mb-4">
                <label class="flex items-center justify-between text-sm text-white/70">
                  <span>Enable A/B Test</span>
                  <div class="builder-toggle" :class="abTestEnabled ? 'on' : ''"
                       @click="abTestEnabled = !abTestEnabled; document.getElementById('ab-test-input').value = abTestEnabled ? '1' : ''"></div>
                </label>
                <p class="mt-1 text-[10px] text-white/40 leading-relaxed">
                  Add a second variant, edit its fields, then Publish. Live visitors on the hosted form / iframe are split by traffic %. Same browser keeps the same variant (cookie). Use incognito or “Preview variant” to see the other one.
                </p>
              </div>

              <div x-show="abTestEnabled" class="space-y-4">
                <div class="flex items-center justify-between mb-2">
                  <h4 class="text-xs font-semibold text-white/80">Variants</h4>
                  <button type="button" @click="addAbVariant()"
                          class="text-xs font-medium text-violet-400 hover:text-violet-300">+ Add variant</button>
                </div>
                
                <template x-for="(variant, idx) in abVariants" :key="idx">
                  <div class="rounded-lg border border-white/10 bg-white/5 p-3 space-y-3 relative group">
                    <button type="button" x-show="Number(variant.is_control) !== 1" @click="abVariants.splice(idx, 1)"
                            class="absolute top-2 right-2 text-white/20 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
                    
                    <div>
                      <label class="mb-1 block text-[10px] text-white/40 uppercase tracking-wider" x-text="Number(variant.is_control) === 1 ? 'Control Variant' : 'Test Variant'"></label>
                      <input type="text" x-model="variant.name" class="builder-input text-sm font-medium" :readonly="Number(variant.is_control) === 1">
                    </div>
                    
                    <div x-show="Number(variant.is_control) !== 1">
                      <div class="flex justify-between text-[10px] text-white/40 mb-1">
                        <span>Traffic Allocation</span>
                        <span x-text="variant.traffic_pct + '%'"></span>
                      </div>
                      <input type="range" min="1" max="99" x-model.number="variant.traffic_pct" class="w-full accent-violet-500">
                    </div>
                    
                    <div class="pt-2 border-t border-white/10 flex justify-between items-center" x-show="Number(variant.is_control) !== 1">
                      <span class="text-xs text-white/50">Custom Design</span>
                      <button type="button" @click="editAbVariant(idx)" class="text-xs text-violet-400 px-2 py-1 rounded bg-violet-500/10 hover:bg-violet-500/20 transition-colors">Edit Fields</button>
                    </div>
                    <a x-show="formSlug && variant.id" :href="'/preview/' + formSlug + '?force_variant=' + variant.id" target="_blank" class="block text-[11px] text-emerald-400 hover:underline">Preview this variant</a>
                  </div>
                </template>
                
                <button type="button" @click="saveAbVariants()" class="builder-btn-save w-full text-xs py-2 mt-2">
                  Save Variants Config
                </button>
                
                <div class="mt-4 pt-4 border-t border-white/10 text-center">
                  <a :href="'/admin/forms/' + formId + '/ab-results'" class="text-xs font-medium text-emerald-400 hover:text-emerald-300 block py-1.5 rounded-lg border border-emerald-500/20 bg-emerald-500/10 hover:bg-emerald-500/20 transition-all">
                    📊 View Test Results
                  </a>
                </div>
              </div>
            </div>
          </div><!-- /abtest tab -->

        </div><!-- /scroll area -->
      </aside><!-- /right sidebar -->

    </div><!-- /main body -->
  </form>

  <!-- ═══════════════════════ CONTEXT MENU ═══════════════════════ -->
  <div x-show="contextMenu.show" x-cloak
       class="builder-context-menu"
       :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
       @click.stop>
    <button type="button" @click="ctxEdit()"       class="builder-context-item">
      <svg class="builder-context-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
      Edit properties
    </button>
    <button type="button" @click="ctxMoveUp()"     class="builder-context-item">
      <svg class="builder-context-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
      Move up
    </button>
    <button type="button" @click="ctxMoveDown()"   class="builder-context-item">
      <svg class="builder-context-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
      Move down
    </button>
    <button type="button" @click="ctxDuplicate()"  class="builder-context-item">
      <svg class="builder-context-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
      Duplicate <kbd class="ml-auto builder-kbd">Ctrl+D</kbd>
    </button>
    <button type="button" @click="ctxCopyId()"     class="builder-context-item">
      <svg class="builder-context-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Copy field ID
    </button>
    <div class="builder-context-separator"></div>
    <button type="button" @click="ctxDelete()"     class="builder-context-item danger">
      <svg class="builder-context-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      Delete
    </button>
  </div>

  <!-- ═══════════════════════ KEYBOARD SHORTCUTS MODAL ═══════════════════════ -->
  <div x-show="showShortcuts" x-cloak
       class="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
       @click.self="showShortcuts=false">
    <div class="builder-shortcuts-modal">
      <div class="mb-5 flex items-center justify-between">
        <h3 class="text-base font-bold text-white">⌨️ Keyboard Shortcuts</h3>
        <button type="button" @click="showShortcuts=false"
                class="text-white/30 hover:text-white text-lg leading-none">✕</button>
      </div>
      <div class="space-y-0">
        <div class="builder-shortcut-row"><span class="text-white/60">Undo</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">Ctrl</kbd><kbd class="builder-kbd">Z</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Redo</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">Ctrl</kbd><kbd class="builder-kbd">Y</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Duplicate field</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">Ctrl</kbd><kbd class="builder-kbd">D</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Delete field</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">Delete</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Move up</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">↑</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Move down</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">↓</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Select all</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">Ctrl</kbd><kbd class="builder-kbd">A</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Close / Escape</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">Esc</kbd></div></div>
        <div class="builder-shortcut-row"><span class="text-white/60">Show shortcuts</span><div class="builder-shortcut-keys"><kbd class="builder-kbd">?</kbd></div></div>
      </div>
      <p class="mt-4 text-[10px] text-white/20 text-center">Right-click any field for more options</p>
    </div>
  </div>

  <!-- ═══════════════════════ PREVIEW MODAL ═══════════════════════ -->
  <div x-show="showPreview" x-cloak
       class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4 backdrop-blur-md"
       @click.self="showPreview=false">
    <div class="builder-preview-modal max-h-[90vh] w-full max-w-xl overflow-y-auto p-6 md:p-8">

      <div class="mb-5 flex items-center justify-between">
        <h3 class="font-bold text-white">👁 Form Preview</h3>
        <button type="button" @click="showPreview=false" class="text-white/30 hover:text-white text-lg leading-none">✕</button>
      </div>

      <!-- Success screen -->
      <div x-show="previewDone" class="flex flex-col items-center justify-center py-12 text-center">
        <div class="builder-success-icon text-5xl mb-4">🎉</div>
        <h4 class="text-lg font-bold text-white mb-2">Form submitted!</h4>
        <p class="text-sm text-white/40" x-text="theme.button_text ? 'Thank you for submitting!' : 'Thank you for submitting!'"></p>
        <button type="button" @click="openPreview()" class="mt-6 text-sm text-violet-400 hover:underline">← Preview again</button>
      </div>

      <!-- Preview form -->
      <div x-show="!previewDone">
        <p class="mb-5 text-center text-lg font-bold text-white" x-text="formName"></p>

        <!-- Multi-step tabs in preview -->
        <div x-show="totalSteps > 1" class="mb-5 flex justify-center gap-2">
          <template x-for="s in stepNumbers" :key="'pv-'+s">
            <button type="button" @click="previewStep = s"
                    :class="previewStep === s ? 'active' : ''"
                    class="builder-step-tab">Step <span x-text="s"></span></button>
          </template>
        </div>

        <!-- Progress in preview -->
        <div x-show="totalSteps > 1" class="builder-progress-bar mb-5">
          <div class="builder-progress-fill" :style="{ width: ((previewStep / totalSteps) * 100) + '%' }"></div>
        </div>

        <!-- Fields -->
        <template x-for="field in previewFields" :key="'prev-'+field.id">
          <div class="mb-4"
               x-show="!['hidden','divider','image'].includes(field.type) && isFieldVisible(field, previewValues)"
               :class="field.style?.css_class || ''">

            <!-- Heading/Paragraph -->
            <div x-show="field.type === 'heading'" class="mb-2">
              <h4 class="font-bold text-white" x-text="field.label"></h4>
            </div>
            <div x-show="field.type === 'paragraph'" class="mb-2">
              <p class="text-sm text-white/50" x-text="field.label"></p>
            </div>
            <div x-show="field.type === 'html'" class="mb-2" x-html="field.html_content"></div>
            <div x-show="field.type === 'video'" class="mb-2">
              <template x-if="field.video_url">
                <iframe class="w-full aspect-video rounded-lg" :src="field.video_url" frameborder="0" allowfullscreen></iframe>
              </template>
              <template x-if="!field.video_url">
                <div class="builder-video-preview">No video URL set</div>
              </template>
            </div>

            <!-- Regular field -->
            <div x-show="!['heading','paragraph','html','video'].includes(field.type)">
              <label class="mb-1.5 block text-sm font-medium text-white/80">
                <span x-text="field.label"></span>
                <span x-show="field.required" class="text-red-400 ml-0.5">*</span>
              </label>
              <p x-show="field.help_text" class="mb-1.5 text-xs text-white/35" x-text="field.help_text"></p>

              <!-- Textarea -->
              <template x-if="field.type === 'textarea'">
                <textarea x-model="previewValues[field.id]" :placeholder="field.placeholder"
                          class="builder-preview-textarea"></textarea>
              </template>

              <!-- Select -->
              <template x-if="field.type === 'select'">
                <select x-model="previewValues[field.id]" class="builder-preview-select">
                  <option value="">Select…</option>
                  <template x-for="opt in (field.options||[])" :key="opt.value">
                    <option :value="opt.value" x-text="opt.label"></option>
                  </template>
                </select>
              </template>

              <!-- Radio -->
              <template x-if="field.type === 'radio'">
                <div class="space-y-2">
                  <template x-for="opt in (field.options||[])" :key="opt.value">
                    <label class="flex items-center gap-2.5 text-sm text-white/70 cursor-pointer">
                      <input type="radio" :name="'pv_'+field.id" :value="opt.value"
                             x-model="previewValues[field.id]" class="accent-violet-500">
                      <span x-text="opt.label"></span>
                    </label>
                  </template>
                </div>
              </template>

              <!-- Checkbox group -->
              <template x-if="field.type === 'checkbox'">
                <div class="space-y-2">
                  <template x-for="opt in (field.options||[])" :key="opt.value">
                    <label class="flex items-center gap-2.5 text-sm text-white/70 cursor-pointer">
                      <input type="checkbox" :value="opt.value"
                             @change="previewValues[field.id] = previewValues[field.id] || []"
                             class="accent-violet-500 rounded">
                      <span x-text="opt.label"></span>
                    </label>
                  </template>
                </div>
              </template>

              <!-- Single checkbox -->
              <template x-if="field.type === 'single-checkbox'">
                <label class="flex items-center gap-2.5 text-sm text-white/70 cursor-pointer">
                  <input type="checkbox" x-model="previewValues[field.id]" value="1" class="accent-violet-500 rounded">
                  <span x-text="field.label"></span>
                </label>
              </template>

              <!-- File -->
              <template x-if="field.type === 'file'">
                <div class="builder-preview-input justify-center gap-2 border-dashed cursor-pointer">
                  <span>📎</span><span>Choose file…</span>
                </div>
              </template>

              <!-- Rating -->
              <template x-if="field.type === 'rating'">
                <div class="builder-preview-stars">
                  <template x-for="i in (field.max_rating || 5)" :key="i">
                    <span class="builder-preview-star"
                          :class="(previewRatings[field.id] || 0) >= i ? 'filled' : ''"
                          @click="previewSetRating(field.id, i)"
                          @mouseover="previewRatings[field.id + '_hover'] = i"
                          @mouseleave="delete previewRatings[field.id + '_hover']">⭐</span>
                  </template>
                  <span x-show="previewValues[field.id]" class="ml-2 text-sm text-white/40 self-center"
                        x-text="previewValues[field.id] + ' / ' + (field.max_rating || 5)"></span>
                </div>
              </template>

              <!-- Range slider -->
              <template x-if="field.type === 'range'">
                <div>
                  <input type="range"
                         :min="field.range_min ?? 0"
                         :max="field.range_max ?? 100"
                         :step="field.range_step ?? 1"
                         x-model="previewValues[field.id]"
                         class="w-full accent-violet-500 cursor-pointer">
                  <div class="mt-1 flex justify-between text-xs text-white/30">
                    <span x-text="field.range_min ?? 0"></span>
                    <span class="font-semibold text-violet-400" x-text="previewValues[field.id] || (field.range_min ?? 0)"></span>
                    <span x-text="field.range_max ?? 100"></span>
                  </div>
                </div>
              </template>

              <!-- Signature -->
              <template x-if="field.type === 'signature'">
                <div>
                  <canvas class="builder-sig-canvas" height="100"
                          id="sig-canvas"></canvas>
                  <div class="mt-1 text-right">
                    <button type="button" class="text-xs text-white/30 hover:text-white/60">Clear</button>
                  </div>
                </div>
              </template>

              <!-- Matrix -->
              <template x-if="field.type === 'matrix'">
                <div class="overflow-x-auto rounded-lg border border-white/10">
                  <table class="builder-matrix-preview w-full">
                    <thead>
                      <tr>
                        <th class="text-left">—</th>
                        <template x-for="col in (field.matrix_cols || [])" :key="col">
                          <th x-text="col"></th>
                        </template>
                      </tr>
                    </thead>
                    <tbody>
                      <template x-for="row in (field.matrix_rows || [])" :key="row">
                        <tr>
                          <td x-text="row" class="text-left text-white/60"></td>
                          <template x-for="col in (field.matrix_cols || [])" :key="col">
                            <td>
                              <input type="radio" :name="'mat_'+field.id+'_'+row" class="accent-violet-500">
                            </td>
                          </template>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
              </template>

              <!-- Address -->
              <template x-if="field.type === 'address'">
                <div class="builder-address-grid">
                  <input type="text" x-model="previewValues[field.id + '_street']" placeholder="Street Address" class="builder-address-full builder-preview-input">
                  <input type="text" x-model="previewValues[field.id + '_street2']" placeholder="Address Line 2" class="builder-address-full builder-preview-input">
                  <input type="text" x-model="previewValues[field.id + '_city']" placeholder="City" class="builder-preview-input">
                  <input type="text" x-model="previewValues[field.id + '_state']" placeholder="State / Province" class="builder-preview-input">
                  <input type="text" x-model="previewValues[field.id + '_zip']" placeholder="Zip / Postal Code" class="builder-preview-input">
                  <input type="text" x-model="previewValues[field.id + '_country']" placeholder="Country" class="builder-preview-input">
                </div>
              </template>

              <!-- NPS -->
              <template x-if="field.type === 'nps'">
                <div>
                  <div class="builder-nps-scale">
                    <template x-for="i in 11" :key="i">
                      <label class="builder-nps-btn text-center cursor-pointer" :class="previewValues[field.id] == (i-1) ? 'bg-violet-500 border-violet-500 text-white' : ''">
                        <input type="radio" class="hidden" :name="'nps_'+field.id" :value="i-1" x-model="previewValues[field.id]">
                        <span x-text="i - 1"></span>
                      </label>
                    </template>
                  </div>
                  <div class="builder-nps-labels">
                    <span>Not likely at all</span>
                    <span>Extremely likely</span>
                  </div>
                </div>
              </template>

              <!-- Toggle -->
              <template x-if="field.type === 'toggle'">
                <label class="flex items-center gap-3 text-sm text-white/70 cursor-pointer">
                  <input type="checkbox" x-model="previewValues[field.id]" class="hidden">
                  <div class="builder-switch" :class="previewValues[field.id] ? 'on' : ''"></div>
                  <span x-text="field.label"></span>
                </label>
              </template>

              <!-- Color -->
              <template x-if="field.type === 'color'">
                <div class="flex items-center gap-2">
                  <input type="color" x-model="previewValues[field.id]" class="builder-input h-10 p-1 w-16">
                  <input type="text" x-model="previewValues[field.id]" placeholder="#000000" class="builder-preview-input font-mono flex-1">
                </div>
              </template>

              <!-- Default input -->
              <template x-if="!['textarea','select','radio','checkbox','single-checkbox','file','rating','range','signature','matrix','address','nps','color','toggle'].includes(field.type)">
                <div :class="(field.advanced?.prefix || field.advanced?.suffix) ? 'builder-affix-wrapper' : ''">
                  <span x-show="field.advanced?.prefix" class="builder-affix" x-text="field.advanced.prefix"></span>
                  <input :type="field.type === 'phone' ? 'tel' : field.type"
                         x-model="previewValues[field.id]"
                         :placeholder="field.placeholder"
                         :readonly="field.advanced?.readonly"
                         :autocomplete="field.advanced?.autocomplete || 'on'"
                         :class="(field.advanced?.prefix || field.advanced?.suffix) ? 'builder-affix-input' : 'builder-preview-input'">
                  <span x-show="field.advanced?.suffix" class="builder-affix builder-affix-suffix" x-text="field.advanced.suffix"></span>
                </div>
              </template>
            </div>
          </div>
        </template>

        <!-- Multi-step nav -->
        <div x-show="totalSteps > 1" class="flex justify-between mt-4">
          <button type="button" x-show="previewStep > 1"
                  @click="previewStep--"
                  class="builder-btn-publish">← Previous</button>
          <div class="flex-1"></div>
          <button type="button" x-show="previewStep < totalSteps"
                  @click="previewStep++"
                  class="builder-btn-save">Next Step →</button>
        </div>

        <!-- Submit -->
        <button type="button" x-show="previewStep === totalSteps"
                @click="previewSubmit()"
                class="builder-preview-submit"
                :style="buttonStyle()"
                x-text="theme.button_text || 'Submit Form'">
        </button>
      </div>
    </div>
  </div><!-- /preview modal -->

</div><!-- /root -->
