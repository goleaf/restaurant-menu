import { menuWorkspace } from './menu-workspace.js';

export function floorWorkspace() {
    const base = menuWorkspace({ contentSelector: '[data-floor-content]', modal: 'floor-unsaved' });
    let ownerRoot, unsubscribeFloor, returnFocus;
    return {
        ...base,
        locallyClosed: false,
        transitioning: false,
        pendingRequests: 0,
        bypassEditorGuard: false,
        init() {
            ownerRoot = this.$el;
            base.init.call(this);
            const options = { signal: this.abortController.signal };
            ownerRoot.addEventListener('click', event => this.handleTransition(event), { ...options, capture: true });
            ownerRoot.addEventListener('submit', event => {
                if (navigator.onLine) return;
                event.preventDefault();
                event.stopImmediatePropagation();
            }, { ...options, capture: true });
            ownerRoot.addEventListener('click', event => {
                if (navigator.onLine || !event.target.closest('[data-floor-cancel]')) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                this.hideEditorLocally();
            }, { ...options, capture: true });
            ownerRoot.addEventListener('floor-print-ready', () => {
                if (!this.destroyed && ownerRoot.isConnected) window.print();
            }, options);
            ownerRoot.addEventListener('floor-invalid', () => this.focusEditor(true), options);
            for (const name of ['floor-editor-saved', 'floor-editor-cancelled']) {
                ownerRoot.addEventListener(name, event => {
                    const component = event.target.closest('[wire\\:id]');
                    for (const form of this.dirtyForms.keys()) {
                        if (form.closest('[wire\\:id]') === component) this.dirtyForms.delete(form);
                    }
                    if (typeof event.detail?.draftKey === 'string') this.dirtySections.delete(event.detail.draftKey);
                }, options);
            }
            unsubscribeFloor = window.Livewire.interceptMessage(({ message, onSend, onFinish, onSuccess }) => {
                if (!ownerRoot.contains(message.component.el)) return;
                let pending = false;
                const release = () => {
                    if (!pending) return;
                    pending = false;
                    this.pendingRequests = Math.max(0, this.pendingRequests - 1);
                };
                onSend(() => { if (!pending) { pending = true; this.pendingRequests++; } });
                onFinish(release);
                onSuccess(({ onSync }) => onSync(release));
                if (message.component.el !== ownerRoot) return;
                if (!Array.from(message.actions).some(action => /^(?:openPoint|openArea|createPoint|createArea|openBulk|openSelection)$/.test(action.name))) return;
                onSuccess(({ payload, onRender }) => onRender(() => {
                    if (this.destroyed || !ownerRoot.isConnected) return;
                    const snapshot = typeof payload.snapshot === 'string' ? JSON.parse(payload.snapshot) : payload.snapshot;
                    if (Object.keys(snapshot?.memo?.errors ?? {}).length > 0) return;
                    this.locallyClosed = false;
                    const editor = ownerRoot.querySelector('[data-floor-editor-shell]');
                    if (editor) editor.hidden = false;
                    this.focusEditor();
                }));
            });
        },
        async handleTransition(event) {
            const trigger = event.target.closest('[data-floor-transition]');
            if (!trigger || !ownerRoot.contains(trigger)) return;
            if (this.bypassEditorGuard) { this.bypassEditorGuard = false; return; }
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button > 0) return;
            if (!navigator.onLine || this.transitioning || this.pendingRequests > 0) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            returnFocus = trigger;
            if (!this.hasUnsavedChanges() && !this.locallyClosed) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            const drafts = new Map(this.dirtyForms), sections = new Set(this.dirtySections);
            return this.requestNavigation(async () => {
                if (!navigator.onLine || this.transitioning || this.destroyed) return;
                this.transitioning = true;
                try {
                    if (this.locallyClosed) await this.$wire.clearEditor();
                    if (this.destroyed || !trigger.isConnected) return;
                    this.locallyClosed = false;
                    this.bypassEditorGuard = true;
                    trigger.click();
                } catch {
                    this.dirtyForms = drafts;
                    this.dirtySections = sections;
                    this.focusEditor(true);
                } finally {
                    this.transitioning = false;
                }
            });
        },
        closeEditor() {
            if (this.transitioning || this.pendingRequests > 0) return;
            const drafts = new Map(this.dirtyForms), sections = new Set(this.dirtySections);
            return this.requestNavigation(async () => {
                if (this.destroyed) return;
                if (!navigator.onLine) {
                    this.hideEditorLocally();
                    return;
                }
                this.transitioning = true;
                try {
                    await this.$wire.clearEditor();
                    this.locallyClosed = false;
                    this.restoreFocus();
                } catch {
                    this.dirtyForms = drafts;
                    this.dirtySections = sections;
                    this.focusEditor(true);
                } finally {
                    this.transitioning = false;
                }
            });
        },
        hideEditorLocally() {
            if (this.pendingRequests > 0) return;
            const editor = ownerRoot.querySelector('[data-floor-editor-shell]');
            if (editor) editor.hidden = true;
            this.locallyClosed = true;
            this.dirtyForms.clear();
            this.dirtySections.clear();
            this.restoreFocus();
        },
        discardFormLocally(event, model, baseline) {
            if (this.pendingRequests > 0) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            const component = event.currentTarget.closest('[wire\\:id]');
            const wire = component && window.Livewire.find(component.getAttribute('wire:id'));
            if (!wire) return;
            base.discardFormLocally.call({ $wire: wire, dirtyForms: this.dirtyForms }, event, model, baseline);
        },
        hasUnsavedChanges() {
            return this.pendingRequests > 0 || base.hasUnsavedChanges.call(this);
        },
        requestNavigation(proceed) {
            if (this.pendingRequests > 0) return;
            return base.requestNavigation.call(this, proceed);
        },
        discardAndNavigate() {
            if (this.pendingRequests > 0) return;
            return base.discardAndNavigate.call(this);
        },
        focusEditor(invalid = false) {
            this.$nextTick(() => {
                if (this.destroyed || !ownerRoot.isConnected) return;
                const editor = ownerRoot.querySelector('[data-floor-editor-shell]');
                const target = invalid ? ownerRoot.querySelector('[aria-invalid="true"]') ?? ownerRoot.querySelector('[role="alert"]') : editor;
                target?.focus();
            });
        },
        restoreFocus() {
            this.$nextTick(() => {
                if (this.destroyed || !ownerRoot.isConnected) return;
                if (returnFocus?.isConnected) returnFocus.focus();
                else ownerRoot.querySelector('[data-floor-results-heading]')?.focus();
            });
        },
        destroy() {
            base.destroy.call(this);
            unsubscribeFloor?.();
            unsubscribeFloor = null;
            returnFocus = null;
        },
    };
}
