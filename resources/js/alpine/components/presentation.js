import { menuWorkspace } from './menu-workspace.js';

export function restaurantDashboard() {
    const guard = menuWorkspace({ contentSelector: '[data-dashboard-ordering]', modal: 'dashboard-unsaved', cleanActions: ['discardOrdering'] });
    return {
        ...guard,
        focusFrame: null,
        destroyed: false,
        openBranchPicker() {
            this.$flux.modal('workspace-restaurant').show();
        },
        focusValidationError() {
            if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
            this.$nextTick(() => {
                if (this.destroyed || !this.$el.isConnected) return;
                this.focusFrame = requestAnimationFrame(() => {
                    this.focusFrame = null;
                    if (this.destroyed || !this.$el.isConnected) return;
                    const field = this.$el.querySelector('[aria-invalid="true"]') ?? this.$el.querySelector('[data-dashboard-error]');
                    if (!field) return;
                    for (let parent = field.parentElement; parent && parent !== this.$el; parent = parent.parentElement) {
                        if (parent.tagName === 'DETAILS') parent.open = true;
                    }
                    field.focus();
                });
            });
        },
        destroy() {
            guard.destroy.call(this);
            this.destroyed = true;
            if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
            this.focusFrame = null;
        },
    };
}

export function catalogTransfer() {
    return {
        focusFeedback() {
            this.$nextTick(() => { if (this.$el.isConnected) this.$refs.feedback?.focus(); });
        },
    };
}

export function catalogUpload(configuration = {}) {
    return {
        uploading: false,
        progress: 0,
        selectionChanged(event) {
            this.$dispatch('menu-workspace-dirty', { key: configuration.key ?? 'catalog-transfer', dirty: (event.target.files?.length ?? 0) > 0 });
        },
        clearSelection() { this.$dispatch('menu-workspace-dirty', { key: configuration.key ?? 'catalog-transfer', dirty: false }); },
        startUpload() { this.uploading = true; this.progress = 0; },
        updateProgress(event) { this.progress = event.detail.progress; },
        finishUpload() { this.uploading = false; this.progress = 100; },
        failUpload() { this.uploading = false; },
        cancelUpload() { this.uploading = false; this.progress = 0; },
        destroy() {
            if (this.uploading) this.$wire.$cancelUpload(configuration.field ?? 'form.file');
            this.uploading = false;
            this.clearSelection();
        },
    };
}

export function recoveryCodes() {
    return {
        showRecoveryCodes: false,
        show() { this.showRecoveryCodes = true; },
        hide() { this.showRecoveryCodes = false; },
    };
}
