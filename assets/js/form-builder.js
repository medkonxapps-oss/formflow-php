/**
 * FormFlow — Advanced Visual Form Builder
 * Alpine.js component with drag-and-drop, multi-select,
 * context menu, zoom, auto-save, new field types & more.
 */
function visualFormBuilder(initialFields, cfg) {
    const defaults = window.__FF_FIELD_DEFAULTS || {};

    const fields = (initialFields || []).map(f => normalizeField(f));
    const theme = { ...(defaults.theme || {}), ...(cfg.theme || {}) };

    /* ── Field block definitions ── */
    const blocks = [
        /* Basic */
        { key: 'text',            category: 'basic',    type: 'text',            label: 'Text',          icon: '✏️' },
        { key: 'textarea',        category: 'basic',    type: 'textarea',        label: 'Textarea',      icon: '📝' },
        { key: 'email',           category: 'basic',    type: 'email',           label: 'Email',         icon: '📧' },
        { key: 'phone',           category: 'basic',    type: 'phone',           label: 'Phone',         icon: '📱' },
        { key: 'select',          category: 'basic',    type: 'select',          label: 'Dropdown',      icon: '🔽' },
        { key: 'radio',           category: 'basic',    type: 'radio',           label: 'Radio',         icon: '🔘' },
        { key: 'checkbox',        category: 'basic',    type: 'checkbox',        label: 'Checkbox',      icon: '☑️' },
        { key: 'single-checkbox', category: 'basic',    type: 'single-checkbox', label: 'Agree',         icon: '✅' },
        { key: 'fullname',        category: 'basic',    type: 'text',            label: 'Full Name',     icon: '👤',
            preset: { label: 'Full Name', placeholder: 'John Doe', required: true } },
        /* Advanced */
        { key: 'number',    category: 'advanced', type: 'number',    label: 'Number',      icon: '🔢' },
        { key: 'date',      category: 'advanced', type: 'date',      label: 'Date',        icon: '📅' },
        { key: 'time',      category: 'advanced', type: 'time',      label: 'Time',        icon: '🕐' },
        { key: 'url',       category: 'advanced', type: 'url',       label: 'URL',         icon: '🔗' },
        { key: 'file',      category: 'advanced', type: 'file',      label: 'File Upload', icon: '📎' },
        { key: 'hidden',    category: 'advanced', type: 'hidden',    label: 'Hidden',      icon: '👁️' },
        { key: 'rating',    category: 'advanced', type: 'rating',    label: 'Rating',      icon: '⭐' },
        { key: 'range',     category: 'advanced', type: 'range',     label: 'Range',       icon: '🎚️' },
        { key: 'signature', category: 'advanced', type: 'signature', label: 'Signature',   icon: '✍️' },
        { key: 'matrix',    category: 'advanced', type: 'matrix',    label: 'Matrix',      icon: '📊' },
        { key: 'address',   category: 'advanced', type: 'address',   label: 'Address',     icon: '🏠' },
        { key: 'nps',       category: 'advanced', type: 'nps',       label: 'NPS Score',   icon: '📈' },
        { key: 'color',     category: 'advanced', type: 'color',     label: 'Color Picker',icon: '🎨' },
        { key: 'toggle',    category: 'basic',    type: 'toggle',    label: 'Toggle',      icon: '🎚️' },
        /* Layout */
        { key: 'heading',   category: 'layout', type: 'heading',   label: 'Heading',   icon: '🔤' },
        { key: 'paragraph', category: 'layout', type: 'paragraph', label: 'Paragraph', icon: '📄' },
        { key: 'divider',   category: 'layout', type: 'divider',   label: 'Divider',   icon: '➖' },
        { key: 'image',     category: 'layout', type: 'image',     label: 'Image',     icon: '🖼️' },
        { key: 'html',      category: 'layout', type: 'html',      label: 'Custom HTML',icon: '🖥️' },
        { key: 'video',     category: 'layout', type: 'video',     label: 'Video',     icon: '🎥' },
    ];

    const operators = [
        { value: 'equals',     label: 'equals' },
        { value: 'not_equals', label: 'does not equal' },
        { value: 'contains',   label: 'contains' },
        { value: 'not_empty',  label: 'is not empty' },
        { value: 'empty',      label: 'is empty' },
        { value: 'greater',    label: 'is greater than' },
        { value: 'less',       label: 'is less than' },
    ];

    /* ── normalizeField ── */
    function normalizeField(f) {
        if (!f.validation) f.validation = { min_length: 0, max_length: 0, min_value: null, max_value: null, regex: '', error_message: '', accept: '' };
        if (f.validation.min_value   === undefined) f.validation.min_value   = null;
        if (f.validation.max_value   === undefined) f.validation.max_value   = null;
        if (f.validation.accept      === undefined) f.validation.accept      = '';
        if (!f.width)          f.width       = 'full';
        if (!f.step)           f.step        = 1;
        if (f.help_text        === undefined) f.help_text    = '';
        if (!f.conditional)    f.conditional = { enabled: false, action: 'show', match: 'all', rules: [] };
        if (!f.style)          f.style       = { css_class: '', label_bold: false, text_color: '', bg_color: '', border_color: '' };
        if (!f.advanced)       f.advanced    = { readonly: false, autocomplete: 'on', copy_on_click: false, prefix: '', suffix: '' };
        if (f.type === 'rating'    && !f.max_rating)    f.max_rating    = 5;
        if (f.type === 'range'     && f.range_min === undefined) { f.range_min = 0; f.range_max = 100; f.range_step = 1; }
        if (f.type === 'matrix'    && !f.matrix_rows)   f.matrix_rows   = ['Row 1', 'Row 2'];
        if (f.type === 'matrix'    && !f.matrix_cols)   f.matrix_cols   = ['Col 1', 'Col 2'];
        if (f.type === 'image'     && !f.image_url)     f.image_url     = '';
        if (f.type === 'video'     && !f.video_url)     f.video_url     = '';
        if (f.type === 'html'      && f.html_content === undefined) f.html_content = '<p>Your custom HTML here...</p>';
        if (['select','radio','checkbox'].includes(f.type) && f.options) {
            f._optionsText = f.options.map(o => `${o.label}:${o.value}`).join('\n');
        }
        return f;
    }

    function slugify(text) {
        return text.toLowerCase().trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 80);
    }

    function cloneFields(arr) { return JSON.parse(JSON.stringify(arr)); }

    function uid() { return 'f_' + Math.random().toString(16).slice(2, 10); }

    /* ── Auto-save helpers ── */
    const DRAFT_KEY = `ff_draft_${cfg.formId || 'new'}`;

    function saveDraft(fields) {
        try { localStorage.setItem(DRAFT_KEY, JSON.stringify(fields)); } catch (_) { /* ignore */ }
    }

    function loadDraft() {
        try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (_) { return null; }
    }

    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (_) { /* ignore */ }
    }

    /* ── debounce ── */
    function debounce(fn, ms) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    }

    return {
        ...cfg,
        fields,
        blocks,
        operators,
        theme,

        /* State */
        selectedIndex:  fields.length > 0 ? 0 : -1,
        selectedIds:    [],            // multi-select
        leftTab:        'blocks',
        rightTab:       'field',
        propTab:        'content',     // content | logic | style | advanced
        viewMode:       'desktop',
        canvasStep:     1,
        previewStep:    1,
        blockSearch:    '',
        abTestEnabled:  cfg.abTestEnabled || false,
        abVariants:     (cfg.abVariants || []).map(function (v) {
            var parsed = null;
            if (v.fields_json) {
                try { parsed = JSON.parse(v.fields_json); } catch (e) { parsed = null; }
            }
            return {
                id: Number(v.id) || 0,
                name: v.name || 'Variant',
                is_control: Number(v.is_control) === 1 ? 1 : 0,
                traffic_pct: Number(v.traffic_pct) || 50,
                fields: Array.isArray(parsed) ? parsed : null,
            };
        }),
        editingVariantIndex: -1,
        controlFieldsBackup: null,
        showPreview:    false,
        showEmbedPanel: false,
        embedKind:      'link',
        showShortcuts:  false,
        previewValues:  {},
        previewRatings: {},
        previewDone:    false,
        dragIndex:      null,
        dropZoneActive: null,      // index of active drop zone
        isDraggingBlock: false,    // block drag from palette
        toast:          '',
        toastType:      'info',    // info | success | warning | error
        history:        [cloneFields(fields)],
        historyIndex:   0,
        slugManual:     !cfg.isNew && !!cfg.formSlug,
        zoom:           100,
        saving:         false,
        savedAt:        null,
        contextMenu:    { show: false, x: 0, y: 0, fieldIndex: -1 },
        layerSearch:    '',
        regexTestValue: '',

        /* Collapse states */
        collapseContent:  false,
        collapseStep:     false,
        collapseBehav:    false,
        collapseLayout:   false,
        collapseValid:    false,
        collapseCondition:false,
        collapseAdvanced: false,
        collapseTheme:    false,

        /* ── Computed ── */
        get selectedField() {
            return this.selectedIndex >= 0 ? this.fields[this.selectedIndex] : null;
        },
        get totalSteps() {
            return Math.max(1, ...this.fields.map(f => f.step || 1));
        },
        get canvasFields() {
            return this.fields.filter(f => (f.step || 1) === this.canvasStep);
        },
        get previewFields() {
            return this.fields.filter(f => (f.step || 1) === this.previewStep);
        },
        get stepNumbers() {
            const s = new Set(this.fields.map(f => f.step || 1));
            return Array.from(s).sort((a, b) => a - b);
        },
        get filteredLayers() {
            const q = this.layerSearch.toLowerCase().trim();
            if (!q) return this.fields.map((f, i) => ({ ...f, _idx: i }));
            return this.fields
                .map((f, i) => ({ ...f, _idx: i }))
                .filter(f => (f.label || f.type).toLowerCase().includes(q));
        },
        get historyBack() { return this.historyIndex; },
        get historyFwd()  { return this.history.length - 1 - this.historyIndex; },
        get regexValid() {
            if (!this.selectedField?.validation?.regex) return null;
            try {
                const re = new RegExp(this.selectedField.validation.regex);
                return re.test(this.regexTestValue) ? 'valid' : 'invalid';
            } catch (_) { return 'error'; }
        },

        /* ── init ── */
        init() {
            this.$watch('formName', (val) => {
                if (!this.slugManual && val) this.formSlug = slugify(val);
            });
            this.$watch('fields', debounce(() => {
                this.saving = true;
                saveDraft(this.fields);
                setTimeout(() => {
                    this.saving = false;
                    this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }, 400);
                this.$nextTick(() => this.initSortables());
            }, 800), { deep: true });
            this.$nextTick(() => this.initSortables());
            window.addEventListener('keydown', (e) => this.handleKeyboard(e));
            document.addEventListener('click', () => this.contextMenu.show = false);
            document.addEventListener('contextmenu', (e) => {
                // hide custom menu on right-click outside builder fields
                if (!e.target.closest('.builder-canvas-field')) {
                    this.contextMenu.show = false;
                }
            });
            // Load draft prompt (only for new forms)
            if (cfg.isNew) {
                const draft = loadDraft();
                if (draft && draft.length > 0 && confirm('📋 Auto-saved draft found. Restore it?')) {
                    this.fields = draft.map(f => normalizeField(f));
                    this.showToast('Draft restored!', 'success');
                }
            }
        },

        /* ── Sortable init ── */
        initSortables() {
            if (typeof Sortable === 'undefined') return;
            const canvas = document.getElementById('builder-canvas-fields');
            const layers = document.getElementById('builder-layers-list');
            const self   = this;

            if (canvas?._sortable) { canvas._sortable.destroy(); canvas._sortable = null; }
            if (layers?._sortable) { layers._sortable.destroy();  layers._sortable  = null; }

            if (canvas) {
                canvas._sortable = Sortable.create(canvas, {
                    animation:   200,
                    handle:      '.builder-drag-handle',
                    ghostClass:  'builder-sort-ghost',
                    chosenClass: 'builder-sort-chosen',
                    draggable:   '.builder-canvas-field',
                    onEnd(evt) {
                        self.reorderFromDom('canvas', evt.oldIndex, evt.newIndex);
                    },
                });
            }
            if (layers) {
                layers._sortable = Sortable.create(layers, {
                    animation:  150,
                    handle:     '.builder-layer-drag',
                    ghostClass: 'builder-sort-ghost',
                    draggable:  '.builder-layer-item',
                    onEnd(evt) {
                        self.reorderFromDom('layers', evt.oldIndex, evt.newIndex);
                    },
                });
            }
        },

        /* ── Reorder ── */
        reorderFromDom(source, oldIndex, newIndex) {
            if (oldIndex === newIndex || oldIndex == null || newIndex == null) return;
            if (source === 'layers') {
                const items = [...this.fields];
                const [moved] = items.splice(oldIndex, 1);
                items.splice(newIndex, 0, moved);
                this.fields = items;
                this.selectedIndex = newIndex;
            } else {
                const step     = this.canvasStep;
                const reordered = [...this.canvasFields];
                const [moved]   = reordered.splice(oldIndex, 1);
                reordered.splice(newIndex, 0, moved);
                const reorderedIds = new Set(reordered.map(f => f.id));
                const result = [];
                let ri = 0;
                for (const f of this.fields) {
                    if ((f.step || 1) === step && reorderedIds.has(f.id)) {
                        result.push(reordered[ri++]);
                    } else {
                        result.push(f);
                    }
                }
                this.fields = result;
                this.selectedIndex = this.fields.findIndex(f => f.id === moved.id);
            }
            this.pushHistory();
        },

        /* ── History ── */
        pushHistory() {
            const snap = cloneFields(this.fields);
            this.history = this.history.slice(0, this.historyIndex + 1);
            this.history.push(snap);
            if (this.history.length > 80) this.history.shift();
            else this.historyIndex++;
        },
        undo() {
            if (this.historyIndex <= 0) return;
            this.historyIndex--;
            this.fields = cloneFields(this.history[this.historyIndex]);
            this.showToast('↩ Undone', 'info');
        },
        redo() {
            if (this.historyIndex >= this.history.length - 1) return;
            this.historyIndex++;
            this.fields = cloneFields(this.history[this.historyIndex]);
            this.showToast('↪ Redone', 'info');
        },

        /* ── Keyboard ── */
        handleKeyboard(e) {
            // show shortcuts modal
            if (e.key === '?' && !e.target.matches('input,textarea,select')) {
                e.preventDefault();
                this.showShortcuts = !this.showShortcuts;
                return;
            }
            if (e.key === 'Escape') {
                this.contextMenu.show = false;
                this.showShortcuts    = false;
                this.showPreview      = false;
                return;
            }
            if (e.target.matches('input, textarea, select')) return;
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedIndex >= 0) {
                e.preventDefault();
                this.removeField(this.selectedIndex);
            }
            if (e.ctrlKey && e.key === 'd') { e.preventDefault(); if (this.selectedIndex >= 0) this.duplicateField(this.selectedIndex); }
            if (e.ctrlKey && e.key === 'z' && !e.shiftKey) { e.preventDefault(); this.undo(); }
            if (e.ctrlKey && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); this.redo(); }
            if (e.key === 'ArrowUp'   && this.selectedIndex > 0)                        { e.preventDefault(); this.moveField(this.selectedIndex, -1); }
            if (e.key === 'ArrowDown' && this.selectedIndex < this.fields.length - 1)  { e.preventDefault(); this.moveField(this.selectedIndex,  1); }
            if (e.ctrlKey && e.key === 'a') { e.preventDefault(); this.selectAll(); }
        },

        /* ── Toast ── */
        showToast(msg, type = 'info') {
            this.toast     = msg;
            this.toastType = type;
            setTimeout(() => { this.toast = ''; }, 2500);
        },
        toastClass() {
            const map = {
                info:    'bg-gray-700',
                success: 'bg-emerald-600',
                warning: 'bg-amber-600',
                error:   'bg-red-600',
            };
            return map[this.toastType] || map.info;
        },

        /* ── Block search & filter ── */
        filteredBlocks(category) {
            const q = this.blockSearch.toLowerCase().trim();
            return this.blocks.filter(b => {
                if (b.category !== category) return false;
                if (!q) return true;
                return b.label.toLowerCase().includes(q) || b.type.includes(q);
            });
        },

        /* ── Field type labels ── */
        fieldTypeLabel(type) {
            const map = {
                text: 'Text Field', textarea: 'Textarea', email: 'Email', phone: 'Phone',
                select: 'Dropdown', radio: 'Radio Group', checkbox: 'Checkbox Group',
                number: 'Number', date: 'Date', time: 'Time', url: 'URL', file: 'File Upload',
                hidden: 'Hidden Field', heading: 'Heading', paragraph: 'Paragraph',
                'single-checkbox': 'Agreement', rating: 'Rating', range: 'Range Slider',
                signature: 'Signature', matrix: 'Matrix', divider: 'Divider', image: 'Image',
            };
            return map[type] || type;
        },

        /* ── Conditional logic ── */
        logicSourceFields() {
            return this.fields.filter(f => !['heading','paragraph','hidden','divider','image'].includes(f.type) && f.id !== this.selectedField?.id);
        },
        addConditionalRule() {
            if (!this.selectedField) return;
            const sources = this.logicSourceFields();
            if (!this.selectedField.conditional.rules) this.selectedField.conditional.rules = [];
            this.selectedField.conditional.rules.push({ field_id: sources[0]?.id || '', operator: 'equals', value: '' });
        },
        removeConditionalRule(idx) {
            this.selectedField?.conditional?.rules?.splice(idx, 1);
        },
        evaluateConditional(field, values) {
            const c = field.conditional;
            if (!c?.enabled || !c.rules?.length) return true;
            const results = c.rules.map(rule => {
                const val = values[rule.field_id] ?? '';
                const str = Array.isArray(val) ? val.join(',') : String(val);
                const num = parseFloat(str);
                switch (rule.operator) {
                    case 'equals':     return str === rule.value;
                    case 'not_equals': return str !== rule.value;
                    case 'contains':   return str.includes(rule.value);
                    case 'not_empty':  return str.trim() !== '';
                    case 'empty':      return str.trim() === '';
                    case 'greater':    return !isNaN(num) && num > parseFloat(rule.value);
                    case 'less':       return !isNaN(num) && num < parseFloat(rule.value);
                    default:           return true;
                }
            });
            const pass = c.match === 'any' ? results.some(Boolean) : results.every(Boolean);
            return c.action === 'show' ? pass : !pass;
        },
        isFieldVisible(field, values) { return this.evaluateConditional(field, values); },

        /* ── Field selection ── */
        selectField(index) {
            this.selectedIndex = index;
            this.rightTab = 'field';
            this.showEmbedPanel = false;
            this.contextMenu.show = false;
        },
        selectFieldById(id) {
            const idx = this.fields.findIndex(f => f.id === id);
            if (idx >= 0) this.selectField(idx);
        },
        selectAll() {
            this.selectedIds = this.fields.map(f => f.id);
            this.showToast(`${this.selectedIds.length} fields selected`, 'info');
        },
        isMultiSelected(id) { return this.selectedIds.includes(id); },
        toggleMultiSelect(e, id) {
            if (!e.ctrlKey && !e.metaKey) { this.selectedIds = []; return; }
            const idx = this.selectedIds.indexOf(id);
            if (idx >= 0) this.selectedIds.splice(idx, 1);
            else this.selectedIds.push(id);
        },

        /* ── Bulk operations ── */
        bulkDelete() {
            if (!this.selectedIds.length) return;
            if (!confirm(`Delete ${this.selectedIds.length} fields?`)) return;
            this.fields = this.fields.filter(f => !this.selectedIds.includes(f.id));
            this.selectedIds = [];
            this.selectedIndex = -1;
            this.pushHistory();
            this.showToast('Fields deleted', 'warning');
        },
        clearMultiSelect() { this.selectedIds = []; },

        /* ── Drag from block palette ── */
        onBlockDragStart(e, block) {
            e.dataTransfer.setData('application/x-ff-block', JSON.stringify(block));
            e.dataTransfer.effectAllowed = 'copy';
            this.isDraggingBlock = true;
        },
        onBlockDragEnd() { this.isDraggingBlock = false; this.dropZoneActive = null; },
        onCanvasDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        },
        onCanvasDrop(e) {
            e.preventDefault();
            this.dropZoneActive  = null;
            this.isDraggingBlock = false;
            const raw = e.dataTransfer.getData('application/x-ff-block');
            if (!raw) return;
            try { this.addBlock(JSON.parse(raw), true); } catch (_) { /* ignore */ }
        },
        onDropZoneDrop(e, insertAfterIndex) {
            e.preventDefault();
            e.stopPropagation();
            this.dropZoneActive  = null;
            this.isDraggingBlock = false;
            const raw = e.dataTransfer.getData('application/x-ff-block');
            if (!raw) return;
            try { this.addBlockAt(JSON.parse(raw), insertAfterIndex + 1); } catch (_) { /* ignore */ }
        },
        onDropZoneOver(e, idx) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            this.dropZoneActive = idx;
        },
        onDropZoneLeave() { this.dropZoneActive = null; },

        /* ── Add block to end ── */
        addBlock(block, saveHistory = true) {
            const f     = JSON.parse(JSON.stringify(defaults));
            f.id        = uid();
            f.type      = block.type;
            f.label     = block.label;
            f.step      = this.canvasStep;
            f.required  = !['heading','paragraph','hidden','single-checkbox','divider','image'].includes(block.type);
            if (block.preset) Object.assign(f, block.preset);
            this._applyTypeDefaults(f, block.type);
            this.fields.push(f);
            this.selectedIndex = this.fields.length - 1;
            this.rightTab = 'field';
            if (saveHistory) this.pushHistory();
            this.$nextTick(() => this.scrollToSelected());
        },

        /* ── Add block at specific index ── */
        addBlockAt(block, insertIndex) {
            const f     = JSON.parse(JSON.stringify(defaults));
            f.id        = uid();
            f.type      = block.type;
            f.label     = block.label;
            f.step      = this.canvasStep;
            f.required  = !['heading','paragraph','hidden','single-checkbox','divider','image'].includes(block.type);
            if (block.preset) Object.assign(f, block.preset);
            this._applyTypeDefaults(f, block.type);
            const clampedIdx = Math.min(Math.max(insertIndex, 0), this.fields.length);
            this.fields.splice(clampedIdx, 0, f);
            this.selectedIndex = clampedIdx;
            this.rightTab = 'field';
            this.pushHistory();
            this.$nextTick(() => this.scrollToSelected());
        },

        _applyTypeDefaults(f, type) {
            if (['select','radio','checkbox'].includes(type)) {
                f.options      = [{ label: 'Option 1', value: 'option_1' }, { label: 'Option 2', value: 'option_2' }];
                f._optionsText = 'Option 1:option_1\nOption 2:option_2';
            }
            if (type === 'single-checkbox') { f.label = 'I agree to the terms and conditions'; f.required = true; }
            if (type === 'rating')    { f.max_rating = 5; }
            if (type === 'range')     { f.range_min = 0; f.range_max = 100; f.range_step = 1; }
            if (type === 'signature') { f.label = 'Signature'; }
            if (type === 'matrix')    { f.matrix_rows = ['Row 1','Row 2']; f.matrix_cols = ['Column 1','Column 2']; }
            if (type === 'divider')   { f.label = ''; f.required = false; }
            if (type === 'image')     { f.image_url = ''; f.label = 'Image'; f.required = false; }
            normalizeField(f);
        },

        /* ── Duplicate field ── */
        duplicateField(index) {
            const copy = JSON.parse(JSON.stringify(this.fields[index]));
            copy.id = uid();
            this.fields.splice(index + 1, 0, copy);
            this.selectedIndex = index + 1;
            this.pushHistory();
            this.showToast('Field duplicated', 'success');
        },

        /* ── Remove field ── */
        removeField(index, skipConfirm = false) {
            if (!skipConfirm && !confirm('Delete this field?')) return;
            const label = this.fields[index]?.label || 'Field';
            this.fields.splice(index, 1);
            if (this.selectedIndex >= this.fields.length) this.selectedIndex = this.fields.length - 1;
            this.pushHistory();
            this.showToast(`"${label}" deleted`, 'warning');
        },

        /* ── Move field ── */
        moveField(index, dir) {
            const n = index + dir;
            if (n < 0 || n >= this.fields.length) return;
            const t = this.fields[n];
            this.fields[n]     = this.fields[index];
            this.fields[index] = t;
            this.selectedIndex = n;
            this.pushHistory();
        },

        /* ── Scroll selected into view ── */
        scrollToSelected() {
            if (this.selectedIndex < 0) return;
            const el = document.querySelector('.builder-field-selected');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },

        /* ── Jump to field (layer click) ── */
        jumpToField(globalIdx) {
            this.selectField(globalIdx);
            const field = this.fields[globalIdx];
            if (field && (field.step || 1) !== this.canvasStep) {
                this.canvasStep = field.step || 1;
            }
            this.$nextTick(() => this.scrollToSelected());
        },

        /* ── Sync options text <-> options array ── */
        syncOptions(field) {
            field.options = (field._optionsText || '').split('\n').filter(Boolean).map(line => {
                const parts = line.split(':');
                const label = (parts[0] || '').trim();
                const value = (parts[1] || label || '').trim();
                return { label, value };
            });
        },

        /* ── Matrix helpers ── */
        matrixRowsText(field)  { return (field.matrix_rows  || []).join('\n'); },
        matrixColsText(field)  { return (field.matrix_cols  || []).join('\n'); },
        syncMatrixRows(field, text)  { field.matrix_rows = text.split('\n').filter(Boolean); },
        syncMatrixCols(field, text)  { field.matrix_cols = text.split('\n').filter(Boolean); },

        /* ── Zoom ── */
        zoomIn()    { this.zoom = Math.min(this.zoom + 10, 150); },
        zoomOut()   { this.zoom = Math.max(this.zoom - 10, 50);  },
        zoomReset() { this.zoom = 100; },
        zoomStyle() { return { transform: `scale(${this.zoom / 100})`, transformOrigin: 'top center' }; },

        /* ── Context menu ── */
        openContextMenu(e, fieldIndex) {
            e.preventDefault();
            e.stopPropagation();
            this.contextMenu = { show: true, x: e.clientX, y: e.clientY, fieldIndex };
            this.selectedIndex = fieldIndex;
        },
        ctxEdit()      { this.selectField(this.contextMenu.fieldIndex); this.contextMenu.show = false; },
        ctxDuplicate() { this.duplicateField(this.contextMenu.fieldIndex); this.contextMenu.show = false; },
        ctxDelete()    { this.removeField(this.contextMenu.fieldIndex); this.contextMenu.show = false; },
        ctxMoveUp()    { this.moveField(this.contextMenu.fieldIndex, -1); this.contextMenu.show = false; },
        ctxMoveDown()  { this.moveField(this.contextMenu.fieldIndex,  1); this.contextMenu.show = false; },
        ctxCopyId() {
            if (this.fields[this.contextMenu.fieldIndex]) {
                navigator.clipboard.writeText(this.fields[this.contextMenu.fieldIndex].id)
                    .then(() => this.showToast('Field ID copied!', 'success'));
            }
            this.contextMenu.show = false;
        },

        /* ── Form controls ── */
        togglePublish() {
            this.formStatus = this.formStatus === 'active' ? 'paused' : 'active';
        },
        copyEmbed() {
            this.copyEmbedById('embed-html-code');
        },
        copyEndpoint() {
            this.copyEmbedById('embed-endpoint');
        },
        copyEmbedById(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const value = el.value || el.textContent || '';
            navigator.clipboard.writeText(value).then(() => this.showToast('Copied!', 'success'));
        },

        /* ── Canvas style ── */
        canvasStyle() {
            return {
                backgroundColor: this.theme.background_color,
                borderRadius:    (this.theme.border_radius || 12) + 'px',
                maxWidth:        (this.theme.max_width || 700) + 'px',
                fontFamily:      this.theme.font_family || 'Inter, sans-serif',
            };
        },
        buttonStyle() {
            return {
                background:    this.theme.button_color ? undefined : 'linear-gradient(135deg,#7c5cff,#a78bfa)',
                backgroundColor: this.theme.button_color || undefined,
                borderRadius:  (this.theme.border_radius || 10) + 'px',
            };
        },
        labelStyle(field) {
            return {
                color:      this.theme.label_color || undefined,
                fontWeight: field?.style?.label_bold ? '600' : '400',
                color:      field?.style?.text_color || this.theme.label_color || undefined,
            };
        },

        /* ── Form submit preparation ── */
        prepareSubmit() {
            if (this.editingVariantIndex >= 0) {
                this.doneEditAbVariant();
            }
            this.fields.forEach(f => {
                if (f._optionsText !== undefined) this.syncOptions(f);
                delete f._optionsText;
            });
            const abInput = document.getElementById('ab-test-input');
            if (abInput) abInput.value = this.abTestEnabled ? '1' : '';
            const variantsInput = document.getElementById('variants-json-input');
            if (variantsInput) variantsInput.value = JSON.stringify(this.abPayload());
            clearDraft();
        },

        /* ── Preview ── */
        openPreview() {
            this.previewStep   = 1;
            this.previewValues = {};
            this.previewRatings = {};
            this.previewDone   = false;
            this.showPreview   = true;
        },
        previewSetRating(fieldId, val) {
            this.previewRatings[fieldId] = val;
            this.previewValues[fieldId]  = val;
        },
        previewSubmit() {
            this.previewDone = true;
        },

        /* ── Step management ── */
        addStep() {
            const newStep = this.totalSteps + 1;
            this.canvasStep = newStep;
            this.showToast(`Step ${newStep} added`, 'success');
        },
        removeStep() {
            if (this.totalSteps <= 1) return;
            const step = this.canvasStep;
            const hasFields = this.fields.some(f => (f.step || 1) === step);
            if (hasFields && !confirm(`Move all Step ${step} fields to Step ${step - 1}?`)) return;
            this.fields.forEach(f => {
                if ((f.step || 1) === step) f.step = step - 1;
                else if ((f.step || 1) > step) f.step = (f.step || 1) - 1;
            });
            this.canvasStep = Math.max(1, step - 1);
            this.pushHistory();
        },

        /* ── A/B Testing ── */
        addAbVariant() {
            if (this.formId <= 0) {
                this.showToast('Save the form first, then add variants.', 'warning');
                return;
            }
            if (this.abVariants.length === 0) {
                this.abVariants.push({ id: 0, name: 'Control', traffic_pct: 50, is_control: 1, fields: null });
            }
            const n = this.abVariants.filter(v => Number(v.is_control) !== 1).length + 1;
            this.abVariants.push({
                id: 0,
                name: 'Variant ' + String.fromCharCode(64 + n),
                traffic_pct: 50,
                is_control: 0,
                fields: JSON.parse(JSON.stringify(this.fields)),
            });
            this.abTestEnabled = true;
            const abInput = document.getElementById('ab-test-input');
            if (abInput) abInput.value = '1';
        },
        editAbVariant(idx) {
            const variant = this.abVariants[idx];
            if (!variant || Number(variant.is_control) === 1) return;
            if (this.editingVariantIndex >= 0) {
                this.doneEditAbVariant();
            }
            this.controlFieldsBackup = JSON.parse(JSON.stringify(this.fields));
            this.editingVariantIndex = idx;
            if (Array.isArray(variant.fields) && variant.fields.length > 0) {
                this.fields = JSON.parse(JSON.stringify(variant.fields));
            }
            this.selectedIndex = this.fields.length ? 0 : -1;
            this.rightTab = 'field';
            this.showToast('Editing ' + variant.name + '. Change fields, then click Done.', 'info');
        },
        doneEditAbVariant() {
            if (this.editingVariantIndex < 0) return;
            this.abVariants[this.editingVariantIndex].fields = JSON.parse(JSON.stringify(this.fields));
            if (this.controlFieldsBackup) {
                this.fields = this.controlFieldsBackup;
            }
            this.controlFieldsBackup = null;
            this.editingVariantIndex = -1;
            this.selectedIndex = this.fields.length ? 0 : -1;
            this.rightTab = 'abtest';
            this.showToast('Variant fields updated. Click Save Variants Config.', 'success');
        },
        abPayload() {
            return this.abVariants.map(v => ({
                name: v.name,
                is_control: Number(v.is_control) === 1 ? 1 : 0,
                traffic_pct: Number(v.traffic_pct) || 50,
                fields_json: Array.isArray(v.fields) ? JSON.stringify(v.fields) : null,
            }));
        },
        saveAbVariants() {
            if (this.formId <= 0) {
                this.showToast('Save the form first, then save variants.', 'warning');
                return;
            }
            if (this.editingVariantIndex >= 0) {
                this.doneEditAbVariant();
            }
            this.abTestEnabled = true;
            const abInput = document.getElementById('ab-test-input');
            if (abInput) abInput.value = '1';

            const data = new FormData();
            data.append('_csrf', document.querySelector('input[name="_csrf"]')?.value || '');
            data.append('form_id', this.formId);
            data.append('variants_json', JSON.stringify(this.abPayload()));

            fetch('/admin/forms/variants/save', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
            .then(res => res.text().then(text => {
                let json = {};
                try { json = JSON.parse(text); } catch (e) { json = { error: text.slice(0, 120) || res.statusText }; }
                return { ok: res.ok, json };
            }))
            .then(({ ok, json }) => {
                if (ok && json.success) {
                    this.showToast('Variants saved. Click Publish so the live form uses them.', 'success');
                } else {
                    this.showToast(json.error || 'Failed to save variants', 'error');
                }
            })
            .catch(() => {
                this.showToast('Could not save variants', 'error');
            });
        }
    };
}
