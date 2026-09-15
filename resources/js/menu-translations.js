function registerTranslationEditor() {
    window.Alpine.data('menuTranslations', (config) => ({
        active: 'en',
        copiedFields: {},
        invalidLocales: [],
        errorSignature: '',
        observer: null,
        submitHandler: null,
        unsubscribe: null,
        form: null,
        editor: null,
        init() {
            this.editor = this.$el;
            this.form = this.editor.closest('form');
            if (this.form) {
                this.submitHandler = () => {
                    this.errorSignature = '';
                    this.syncPrimary();
                };
                this.form.addEventListener('submit', this.submitHandler, true);
                const action = this.form.getAttribute('wire:submit')?.split('(')[0].trim();
                if (action) {
                    const componentId = this.editor.closest('[wire\\:id]')?.getAttribute('wire:id');
                    this.unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                        if (message.component.id !== componentId || !Array.from(message.actions).some((entry) => entry.name === action)) return;
                        onSuccess(({ onRender }) => {
                            onRender(() => {
                                if (!this.editor.isConnected) return;
                                this.errorSignature = '';
                                this.revealError();
                            });
                        });
                    });
                }
            }
            this.$watch(() => this.name('en'), () => this.syncPrimary());
            this.$watch(() => this.description('en'), () => this.syncPrimary());
            this.observer = new MutationObserver(() => this.revealError());
            this.observer.observe(this.editor, { attributes: true, subtree: true, attributeFilter: ['data-invalid'] });
            this.$nextTick(() => this.revealError());
        },
        destroy() {
            this.unsubscribe?.();
            this.observer?.disconnect();
            if (this.form && this.submitHandler) this.form.removeEventListener('submit', this.submitHandler, true);
        },
        name(locale) {
            const value = this.$wire.$get(`${config.model}.${locale}${config.nameOnly ? '' : '.name'}`);
            return typeof value === 'string' ? value : '';
        },
        description(locale) {
            if (config.nameOnly) return '';
            const value = this.$wire.$get(`${config.model}.${locale}.description`);
            return typeof value === 'string' ? value : '';
        },
        filled(locale) { return this.name(locale).trim() !== ''; },
        hasError(locale) { return this.invalidLocales.includes(locale); },
        copyableFields(locale) {
            if (!['lt', 'ru'].includes(locale)) return [];
            const fields = config.nameOnly ? ['name'] : ['name', 'description'];
            return fields.filter((field) => this[field](locale).trim() === '' && this[field]('en').trim() !== '');
        },
        canCopyOriginal(locale) { return this.copyableFields(locale).length > 0; },
        copyOriginal(locale) {
            const fields = this.copyableFields(locale);
            for (const field of fields) {
                const value = this[field]('en');
                const path = `${config.model}.${locale}${config.nameOnly ? '' : `.${field}`}`;
                this.$wire.$set(path, value, false);
                this.copiedFields[locale] ??= {};
                this.copiedFields[locale][field] = value;
            }
            if (fields.length > 0) this.$dispatch('input');
        },
        hasCopiedText(locale) {
            return Object.entries(this.copiedFields[locale] ?? {}).some(([field, value]) => this[field](locale) === value);
        },
        length(value) { return Array.from(value).length; },
        syncPrimary() {
            if (config.baseNameModel) this.$wire.$set(config.baseNameModel, this.name('en'), false);
            if (config.baseDescriptionModel) this.$wire.$set(config.baseDescriptionModel, this.description('en'), false);
        },
        activate(locale, focus = false) {
            this.active = locale;
            if (focus) this.$nextTick(() => this.editor.querySelector(`[data-locale-tab="${locale}"]`)?.focus());
        },
        navigate(event) {
            const tabs = Array.from(this.editor.querySelectorAll('[data-locale-tab]'));
            if (tabs.length === 0) return;
            const index = tabs.findIndex((tab) => tab.dataset.localeTab === this.active);
            const next = { ArrowRight: (index + 1) % tabs.length, ArrowLeft: (index - 1 + tabs.length) % tabs.length, Home: 0, End: tabs.length - 1 }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            this.activate(tabs[next].dataset.localeTab, true);
        },
        revealError() {
            const invalid = Array.from(this.editor.querySelectorAll('[data-locale-panel][data-invalid="true"]'));
            this.invalidLocales = invalid.map((panel) => panel.dataset.localePanel);
            const signature = invalid.map((panel) => panel.dataset.localePanel).join(',');
            if (!signature || signature === this.errorSignature) return;
            this.errorSignature = signature;
            this.activate(invalid[0].dataset.localePanel);
            this.$nextTick(() => invalid[0].querySelector('[aria-invalid="true"], input, textarea')?.focus());
        },
    }));
}

if (window.Alpine) registerTranslationEditor();
else document.addEventListener('alpine:init', registerTranslationEditor, { once: true });
