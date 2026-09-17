export function menuImagePresentationEditor(config) {
    let ownerRoot;
    return {
        focalX: config.focalX, focalY: config.focalY, locale: 'en',
        get position() { return `${this.focalX}% ${this.focalY}%`; },
        init() {
            ownerRoot = this.$el;
            this.$nextTick(() => this.$refs.heading.focus());
        },
        nextLocale(direction) {
            const locales = ['en', 'lt', 'ru'];
            this.locale = locales[(locales.indexOf(this.locale) + direction + locales.length) % locales.length];
            this.$nextTick(() => ownerRoot.querySelector('[role="tab"][aria-selected="true"]')?.focus());
        },
        showError(locale) {
            this.locale = ['en', 'lt', 'ru'].includes(locale) ? locale : 'en';
            this.$nextTick(() => {
                const panel = ownerRoot.querySelector(`[data-photo-locale="${this.locale}"]`);
                (panel?.querySelector('[aria-invalid="true"]') ?? ownerRoot.querySelector('[data-image-error]') ?? this.$refs.heading).focus();
            });
        },
    };
}

export function menuImagePicker(config) {
    return {
        previews: [], pendingPreviewKeys: [], nextPreviewKey: 0,
        uploading: false, removing: false, saving: false, offline: false,
        destroyed: false, workspace: null, failed: false, progress: 0, dragging: false, unsubscribe: null, settingsTrigger: null, metadataDirty: false,
        init() {
            this.workspace = this.$el.closest('[data-page="dish-card"]') ?? this.$el.closest('[data-page="branch-menu"]');
            this.offline = !navigator.onLine;
            this.$watch('previews', () => this.notifyDirty());
            const componentId = this.$wire.$id;
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSend, onFinish }) => {
                if (message.component.id !== componentId || !Array.from(message.actions).some((action) => action.name === 'saveItemImages' && action.params[0] === config.itemId)) return;
                onSend(() => { if (!this.destroyed) this.saving = true; });
                onFinish(() => { if (!this.destroyed) this.saving = false; });
            });
        },
        startUpload() { if (!this.destroyed) { this.uploading = true; this.failed = false; this.progress = 0; } },
        updateProgress(event) { if (!this.destroyed) this.progress = event.detail.progress; },
        goOffline() { this.offline = true; this.dragging = false; },
        goOnline() { this.offline = false; },
        markMetadataDirty(event) {
            if (!event.target.closest('[data-image-presentation-editor]')) return;
            this.metadataDirty = true;
            this.notifyDirty();
        },
        closePresentation(event) {
            if (event.detail.itemId !== config.itemId) return;
            this.metadataDirty = false;
            this.notifyDirty();
            if (this.settingsTrigger?.isConnected) this.settingsTrigger.focus();
        },
        imagesSaved(event) { if (event.detail.itemId === config.itemId) this.clear(); },
        notifyDirty() {
            const detail = { key: `images-${config.itemId}`, dirty: this.previews.length > 0 || this.metadataDirty };
            if (this.workspace?.isConnected) this.workspace.dispatchEvent(new CustomEvent('menu-workspace-dirty', { bubbles: true, detail }));
            else this.$dispatch('menu-workspace-dirty', detail);
        },
        get busy() { return this.destroyed || this.uploading || this.removing || this.saving || this.offline; },
        preview(event) {
            if (this.busy) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            const files = Array.from(event.target.files ?? []);
            if (files.length === 0) return;
            const previews = [];
            try {
                files.forEach((file) => previews.push({ key: ++this.nextPreviewKey, name: file.name, url: URL.createObjectURL(file) }));
            } catch {
                previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                event.preventDefault();
                event.stopImmediatePropagation();
                this.failed = true;
                return;
            }
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
            if (this.destroyed) return;
            this.uploading = false;
            this.progress = 100;
            this.pendingPreviewKeys = [];
        },
        failUpload() {
            if (this.destroyed) return;
            this.discardPendingPreviews();
            this.uploading = false;
            this.failed = true;
            this.progress = 0;
        },
        cancelUpload() {
            if (this.destroyed) return;
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
                if (this.destroyed) return;
                const currentIndex = this.previews.findIndex((preview) => preview.key === key);
                if (currentIndex === -1) return;
                URL.revokeObjectURL(this.previews[currentIndex].url);
                this.previews.splice(currentIndex, 1);
                this.failed = false;
            } catch {
                if (!this.destroyed) this.failed = true;
            } finally {
                if (!this.destroyed) this.removing = false;
            }
        },
        clearPreviews() {
            this.previews.forEach((preview) => URL.revokeObjectURL(preview.url));
            this.previews = [];
            this.pendingPreviewKeys = [];
        },
        clear() { this.clearPreviews(); if (this.$refs.files) this.$refs.files.value = ''; this.failed = false; },
        destroy() {
            this.destroyed = true;
            if (this.uploading) this.$wire.$cancelUpload(`itemImageUploads.${config.itemId}`);
            this.uploading = false;
            this.removing = false;
            this.saving = false;
            this.unsubscribe?.();
            this.unsubscribe = null;
            this.clearPreviews();
            this.metadataDirty = false;
            this.notifyDirty();
        },
    };
}
