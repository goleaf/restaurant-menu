import { menuWorkspace } from './menu-workspace.js';

export function availabilityWorkspace() {
    const base = menuWorkspace({ modal: 'availability-unsaved', cleanActions: ['discardDraft', 'applyRestriction', 'applyPause', 'applySchedule', 'applyExceptions'] });
    let ownerRoot;
    return {
        ...base,
        locallyDiscarded: false,
        discarding: false,
        bypassEditorGuard: false,
        init() {
            ownerRoot = this.$el;
            base.init.call(this);
            const options = { signal: this.abortController.signal };
            ownerRoot.addEventListener('submit', (event) => {
                if (navigator.onLine) return;
                event.preventDefault();
                event.stopImmediatePropagation();
            }, { ...options, capture: true });
            window.addEventListener('availability-validation-failed', () => {
                this.$nextTick(() => ownerRoot.querySelector('[role="alert"]')?.focus());
            }, options);
            window.addEventListener('availability-preview-ready', () => {
                if (this.destroyed) return;
                this.dirtySections.add('availability-draft');
                this.$nextTick(() => ownerRoot.querySelector('[data-availability-preview]')?.focus());
            }, options);
            window.addEventListener('availability-draft-dirty', () => this.dirtySections.add('availability-draft'), options);
            window.addEventListener('availability-draft-cleared', () => this.clearLocalDraft(), options);
            ownerRoot.addEventListener('click', (event) => {
                const trigger = event.target.closest('[wire\\:click]');
                if (!trigger || !/^open(?:Item|Bulk|Pause|Hours|MenuSchedule|Exceptions)\b/.test(trigger.getAttribute('wire:click') ?? '')) return;
                if (this.bypassEditorGuard) { this.bypassEditorGuard = false; return; }
                if (!this.hasUnsavedChanges() && !this.locallyDiscarded) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                this.requestNavigation(async () => {
                    if (!navigator.onLine) return;
                    if (this.locallyDiscarded || this.hasUnsavedChanges()) await this.cancelDraft();
                    if (this.destroyed) return;
                    this.bypassEditorGuard = true;
                    trigger.click();
                });
            }, { ...options, capture: true });
            window.addEventListener('availability-editor-opened', () => {
                this.locallyDiscarded = false;
                this.$nextTick(() => {
                    const editor = ownerRoot.querySelector('[data-availability-editor]');
                    if (editor) editor.hidden = false;
                    editor?.focus();
                });
            }, options);
        },
        clearLocalDraft() {
            this.dirtyForms.clear();
            this.dirtySections.clear();
        },
        async cancelDraft() {
            if (this.discarding) return;
            if (!navigator.onLine) {
                const editor = ownerRoot.querySelector('[data-availability-editor]');
                if (editor) editor.hidden = true;
                this.clearLocalDraft();
                this.locallyDiscarded = true;
                this.$wire.$set?.('restriction', { operation: 'stop', untilDate: '', untilTime: '', reason: '' }, false);
                this.$wire.$set?.('pause', { mode: 'indefinite', durationMinutes: 30, untilDate: '', untilTime: '', reason: '' }, false);
                this.$wire.$set?.('weekly', { mode: 'unrestricted', openingHoursConfigured: false, openingHours: [] }, false);
                this.$wire.$set?.('exceptions', { exceptions: [] }, false);
                return;
            }
            this.discarding = true;
            try {
                await this.$wire.discardDraft();
                this.clearLocalDraft();
                this.locallyDiscarded = false;
            } finally {
                this.discarding = false;
            }
        },
        async navigateSection(event, section) {
            if (this.locallyDiscarded && navigator.onLine && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey && event.button === 0) {
                event.preventDefault();
                await this.cancelDraft();
            }
            return base.navigateSection.call(this, event, section);
        },
        async discardAndNavigate() {
            if (!navigator.onLine || this.discarding) return;
            await this.cancelDraft();
            return base.discardAndNavigate.call(this);
        },
    };
}
