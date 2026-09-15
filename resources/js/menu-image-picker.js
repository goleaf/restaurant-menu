function registerImagePicker() {
    window.Alpine.data('menuImagePresentationEditor', (config) => ({
        focalX: config.focalX, focalY: config.focalY, locale: 'en',
        get position() { return `${this.focalX}% ${this.focalY}%`; },
        init() { this.$nextTick(() => this.$refs.heading.focus()); },
        nextLocale(direction) {
            const locales = ['en', 'lt', 'ru'];
            this.locale = locales[(locales.indexOf(this.locale) + direction + locales.length) % locales.length];
            this.$nextTick(() => this.$el.querySelector('[role="tab"][aria-selected="true"]')?.focus());
        },
        showError(locale) {
            this.locale = ['en', 'lt', 'ru'].includes(locale) ? locale : 'en';
            this.$nextTick(() => {
                const panel = this.$el.querySelector(`[data-photo-locale="${this.locale}"]`);
                (panel?.querySelector('[aria-invalid="true"]') ?? this.$el.querySelector('[data-image-error]') ?? this.$refs.heading).focus();
            });
        },
    }));
    window.Alpine.data('menuImagePicker', (config) => ({
        previews: [], pendingPreviewKeys: [], nextPreviewKey: 0,
        uploading: false, removing: false, saving: false, offline: false,
        workspace: null, failed: false, progress: 0, dragging: false, unsubscribe: null, settingsTrigger: null, metadataDirty: false,
        init() {
            this.workspace = this.$el.closest('[data-page="branch-menu"]');
            this.offline = !navigator.onLine;
            this.$watch('previews', () => this.notifyDirty());
            const componentId = this.$wire.$id;
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSend, onFinish }) => {
                if (message.component.id !== componentId || !Array.from(message.actions).some((action) => action.name === 'saveItemImages' && action.params[0] === config.itemId)) return;
                onSend(() => { this.saving = true; });
                onFinish(() => { this.saving = false; });
            });
        },
        notifyDirty() {
            const detail = { key: `images-${config.itemId}`, dirty: this.previews.length > 0 || this.metadataDirty };
            if (this.workspace?.isConnected) this.workspace.dispatchEvent(new CustomEvent('menu-workspace-dirty', { bubbles: true, detail }));
            else this.$dispatch('menu-workspace-dirty', detail);
        },
        get busy() { return this.uploading || this.removing || this.saving || this.offline; },
        preview(event) {
            if (this.busy) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            const files = Array.from(event.target.files ?? []);
            if (files.length === 0) return;
            const previews = files.map((file) => ({
                key: ++this.nextPreviewKey, name: file.name, url: URL.createObjectURL(file),
            }));
            this.pendingPreviewKeys = previews.map((preview) => preview.key);
            this.previews.push(...previews);
            this.uploading = true;
            this.failed = false;
            this.progress = 0;
        },
        drop(event) {
            this.dragging = false;
            if (this.busy || !event.dataTransfer?.files.length) return;
            this.$refs.files.files = event.dataTransfer.files;
            this.$refs.files.dispatchEvent(new Event('change', { bubbles: true }));
        },
        finishUpload() {
            this.uploading = false;
            this.progress = 100;
            this.pendingPreviewKeys = [];
        },
        failUpload() {
            this.discardPendingPreviews();
            this.uploading = false;
            this.failed = true;
            this.progress = 0;
        },
        cancelUpload() {
            this.discardPendingPreviews();
            this.uploading = false;
            this.failed = false;
            this.progress = 0;
        },
        discardPendingPreviews() {
            this.previews = this.previews.filter((preview) => {
                if (!this.pendingPreviewKeys.includes(preview.key)) return true;
                URL.revokeObjectURL(preview.url);
                return false;
            });
            this.pendingPreviewKeys = [];
            if (this.$refs.files) this.$refs.files.value = '';
        },
        beginSave(event) {
            if (this.busy) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            this.saving = true;
        },
        async remove(key) {
            if (this.busy) return;
            const index = this.previews.findIndex((preview) => preview.key === key);
            if (index === -1) return;
            this.removing = true;
            try {
                await this.$wire.removePendingItemImage(config.itemId, index);
                const currentIndex = this.previews.findIndex((preview) => preview.key === key);
                if (currentIndex === -1) return;
                URL.revokeObjectURL(this.previews[currentIndex].url);
                this.previews.splice(currentIndex, 1);
                this.failed = false;
            } catch {
                this.failed = true;
            } finally {
                this.removing = false;
            }
        },
        clearPreviews() {
            this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
            this.previews = [];
            this.pendingPreviewKeys = [];
        },
        clear() { this.clearPreviews(); if (this.$refs.files) this.$refs.files.value = ''; this.failed = false; },
        destroy() {
            this.unsubscribe?.();
            this.unsubscribe = null;
            this.clearPreviews();
            this.metadataDirty = false;
            this.notifyDirty();
        },
    }));
}
if (window.Alpine) registerImagePicker();
else document.addEventListener('alpine:init', registerImagePicker, { once: true });
