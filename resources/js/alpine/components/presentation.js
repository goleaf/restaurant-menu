export function restaurantDashboard() {
    return {
        focusFrame: null,
        destroyed: false,
        openBranchPicker() {
            if (this.$refs.branchPicker) this.$refs.branchPicker.open = true;
            this.$nextTick(() => { if (!this.destroyed) this.$refs.branchSearch?.focus(); });
        },
        closeBranchPicker() {
            if (this.$refs.branchPicker) this.$refs.branchPicker.open = false;
            this.$refs.branchPickerSummary?.focus();
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
            this.destroyed = true;
            if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
            this.focusFrame = null;
        },
    };
}

export function onboardingFocus() {
    return {
        focusStep() {
            this.$nextTick(() => { if (this.$el.isConnected) this.$el.querySelector('[data-onboarding-step-heading]')?.focus(); });
        },
        focusValidationError() {
            this.$nextTick(() => {
                if (!this.$el.isConnected) return;
                const field = this.$el.querySelector('[aria-invalid="true"]') ?? this.$el.querySelector('#onboarding-validation-summary');
                field?.focus();
            });
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

export function catalogUpload() {
    return {
        uploading: false,
        progress: 0,
        selectionChanged(event) {
            this.$dispatch('menu-workspace-dirty', { key: 'catalog-transfer', dirty: (event.target.files?.length ?? 0) > 0 });
        },
        startUpload() { this.uploading = true; this.progress = 0; },
        updateProgress(event) { this.progress = event.detail.progress; },
        finishUpload() { this.uploading = false; this.progress = 100; },
        failUpload() { this.uploading = false; },
        cancelUpload() { this.uploading = false; this.progress = 0; },
        destroy() {
            if (this.uploading) this.$wire.$cancelUpload('form.file');
            this.uploading = false;
            this.$dispatch('menu-workspace-dirty', { key: 'catalog-transfer', dirty: false });
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
