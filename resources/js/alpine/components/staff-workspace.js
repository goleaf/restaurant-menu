import { stampWorkspaceHistory, guardWorkspaceHistory } from './workspace-history.js';

export function staffEditor() {
    return {
        destroyed: false,
        viewport: null,
        onViewportChange: null,
        init() {
            this.viewport = window.matchMedia('(min-width: 64rem)');
            this.onViewportChange = () => {
                if (this.$el.open) this.present();
            };
            this.viewport.addEventListener('change', this.onViewportChange);
            this.$nextTick(() => this.present());
        },
        present() {
            if (this.destroyed || !this.$el.isConnected) return;
            const active = document.activeElement;
            const focusedInside = this.$el.contains(active);
            this.$el.close();
            if (this.viewport.matches) this.$el.show();
            else this.$el.showModal();
            if (focusedInside) active.focus();
        },
        destroy() {
            this.destroyed = true;
            this.viewport?.removeEventListener('change', this.onViewportChange);
            if (this.$el.open) this.$el.close();
        },
    };
}

export function invitationClipboard() {
    return {
        busy: false,
        epoch: 0,
        copied: false,
        failed: false,
        destroy() { this.epoch++; this.busy = false; },
        async copy() {
            if (this.busy) return;
            const epoch = ++this.epoch;
            this.busy = true;
            this.copied = false;
            this.failed = false;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
                await navigator.clipboard.writeText(this.$refs.link.value);
                if (epoch === this.epoch) this.copied = true;
            } catch {
                if (epoch !== this.epoch) return;
                this.failed = true;
                this.$refs.link.focus();
                this.$refs.link.select();
            } finally {
                if (epoch === this.epoch) this.busy = false;
            }
        },
    };
}

export function staffWorkspace() {
    let ownerRoot;
    return {
        destroyed: false,
        discarding: false,
        online: true,
        dirty: false,
        editorDismissed: false,
        pendingNavigation: null,
        discardEditorBeforeNavigation: true,
        bypassNavigation: false,
        navigating: false,
        pendingRequests: 0,
        trigger: null,
        abortController: null,
        historyId: null,
        historyIndex: 0,
        nativeHistoryIndex: null,
        historyUrl: null,
        returningToIndex: null,
        unsubscribe: null,
        init() {
            ownerRoot = this.$el;
            this.online = navigator.onLine;
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            this.historyId = `${ownerRoot.getAttribute('wire:id')}:${Date.now()}`;
            this.historyUrl = window.location.href;
            this.stampHistory();
            const markDirty = (event) => {
                if (event.target.closest('[data-staff-editor]')) this.dirty = true;
            };
            ownerRoot.addEventListener('input', markDirty, options);
            ownerRoot.addEventListener('change', markDirty, options);
            ownerRoot.addEventListener('click', (event) => {
                const button = event.target.closest('[wire\\:click]');
                if (button && /^open(Invitation|Member|Assignments|AssignmentRemoval|ExistingAssignment|Permissions)\b/.test(button.getAttribute('wire:click'))) this.trigger = button;
            }, options);
            ownerRoot.addEventListener('click', async (event) => {
                const button = event.target.closest('[wire\\:click]');
                const action = button?.getAttribute('wire:click') ?? '';
                if (!this.editorDismissed || !this.online || !/^open(Invitation|Member|Assignments|AssignmentRemoval|ExistingAssignment|Permissions)\b/.test(action)) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                try {
                    await this.$wire.discardEditor();
                    if (this.destroyed) return;
                    this.editorDismissed = false;
                    this.$nextTick(() => {
                        if (!this.destroyed) [...ownerRoot.querySelectorAll('[wire\\:click]')].find((element) => element.getAttribute('wire:click') === action)?.click();
                    });
                } catch {
                    this.focusError();
                }
            }, { ...options, capture: true });
            ownerRoot.addEventListener('submit', (event) => {
                if (this.online) return;
                event.preventDefault();
                event.stopImmediatePropagation();
            }, { ...options, capture: true });
            window.addEventListener('offline', () => { this.online = false; }, options);
            window.addEventListener('online', () => { this.online = true; }, options);
            window.addEventListener('staff-editor-clean', () => { this.dirty = false; }, options);
            window.addEventListener('staff-validation-failed', () => {
                this.$nextTick(() => this.focusError());
            }, options);
            window.addEventListener('staff-editor-opened', () => {
                this.dirty = false;
                this.editorDismissed = false;
                this.$nextTick(() => {
                    if (this.destroyed) return;
                    const editor = ownerRoot.querySelector('[data-staff-editor]');
                    if (editor) {
                        editor.hidden = false;
                        editor.removeAttribute('inert');
                        const state = window.Alpine.$data(editor);
                        if (typeof state.present === 'function') state.present();
                    }
                    editor?.querySelector('input:not([type=hidden]), select')?.focus();
                });
            }, options);
            window.addEventListener('staff-editor-closed', () => {
                this.dirty = false;
                this.$nextTick(() => this.restoreFocus());
            }, options);
            window.addEventListener('beforeunload', (event) => {
                if (!this.dirty && this.pendingRequests === 0) return;
                event.preventDefault();
                event.returnValue = '';
            }, options);
            document.addEventListener('livewire:navigate', (event) => {
                if (this.pendingRequests > 0) {
                    event.preventDefault();
                    return;
                }
                if (this.bypassNavigation || !this.dirty) return;
                event.preventDefault();
                const url = event.detail.url.toString();
                this.requestNavigation(() => {
                    this.bypassNavigation = true;
                    window.Livewire.navigate(url);
                });
            }, options);
            window.addEventListener('popstate', (event) => this.guardHistory(event), { ...options, capture: true });
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSend, onFinish, onSuccess }) => {
                if (message.component.el !== ownerRoot) return;
                onSend(() => { this.pendingRequests++; });
                onFinish(() => { this.pendingRequests = Math.max(0, this.pendingRequests - 1); });
                onSuccess(({ onRender }) => onRender(() => queueMicrotask(() => {
                    if (this.destroyed) return;
                    this.stampHistory();
                    this.focusError();
                })));
            });
        },
        destroy() {
            this.destroyed = true;
            this.abortController?.abort();
            this.unsubscribe?.();
            this.unsubscribe = null;
            this.pendingNavigation = null;
        },
        focusError() {
            if (this.destroyed) return;
            const error = [...ownerRoot.querySelectorAll('[role=alert]')].find((element) => element.getClientRects().length);
            if (!error) return;
            error.setAttribute('tabindex', '-1');
            error.focus();
        },
        restoreFocus() {
            if (this.destroyed) return;
            const target = this.trigger?.isConnected && this.trigger.getClientRects().length && !this.trigger.disabled ? this.trigger : ownerRoot.closest('[data-staff-workspace]')?.querySelector('[data-staff-heading]');
            target?.focus();
        },
        async dismissEditor() {
            if (this.destroyed || this.pendingRequests > 0) return;
            const editor = ownerRoot.querySelector('[data-staff-editor]');
            if (typeof editor?.close === 'function') editor.close();
            else if (editor) {
                editor.hidden = true;
                editor.setAttribute('inert', '');
            }
            this.editorDismissed = true;
            this.dirty = false;
            this.restoreFocus();
            if (!this.online) return;
            try {
                await this.$wire.discardEditor();
            } catch {
                this.focusError();
            }
        },
        navigateSection(event, section) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            event.preventDefault();
            if (!this.online || this.navigating || section === this.$wire.section) return;
            return this.requestNavigation(async () => {
                this.navigating = true;
                try {
                    if (this.editorDismissed) {
                        await this.$wire.discardEditor();
                        if (this.destroyed) return;
                        this.editorDismissed = false;
                    }
                    await this.$wire.selectSection(section);
                } catch {
                    this.focusError();
                } finally {
                    this.navigating = false;
                }
            });
        },
        requestNavigation(proceed, discardEditor = true) {
            if (this.destroyed || this.pendingRequests > 0) return;
            if (!this.dirty) return proceed();
            this.pendingNavigation = proceed;
            this.discardEditorBeforeNavigation = discardEditor;
            this.$flux.modal('staff-workspace-unsaved').show();
        },
        cancelNavigation() {
            this.pendingNavigation = null;
            this.$flux.modal('staff-workspace-unsaved').close();
        },
        async discardAndNavigate() {
            if (this.discarding || this.destroyed || this.pendingRequests > 0) return;
            this.discarding = true;
            const proceed = this.pendingNavigation;
            if (this.online && this.discardEditorBeforeNavigation) {
                try {
                    await this.$wire.discardEditor();
                } catch {
                    this.discarding = false;
                    this.focusError();
                    return;
                }
            }
            this.discarding = false;
            if (this.destroyed) return;
            this.pendingNavigation = null;
            this.dirty = false;
            this.$flux.modal('staff-workspace-unsaved').close();
            this.$nextTick(() => this.restoreFocus());
            try {
                await proceed?.();
            } catch {
                this.focusError();
            }
        },
        stampHistory() {
            stampWorkspaceHistory.call(this, ownerRoot, 'staffWorkspace');
        },
        guardHistory(event) {
            guardWorkspaceHistory.call(this, event, 'staffWorkspace', this.dirty || this.pendingRequests > 0);
        },
    };
}
