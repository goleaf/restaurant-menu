function registerStaffWorkspace() {
    window.Alpine.data('staffEditor', () => ({
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
            if (!this.$el.isConnected) return;
            const active = document.activeElement;
            const focusedInside = this.$el.contains(active);
            this.$el.close();
            if (this.viewport.matches) this.$el.show();
            else this.$el.showModal();
            if (focusedInside) active.focus();
        },
        destroy() {
            this.viewport?.removeEventListener('change', this.onViewportChange);
        },
    }));

    window.Alpine.data('invitationClipboard', () => ({
        busy: false,
        copied: false,
        failed: false,
        async copy() {
            if (this.busy) return;
            this.busy = true;
            this.copied = false;
            this.failed = false;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
                await navigator.clipboard.writeText(this.$refs.link.value);
                this.copied = true;
            } catch {
                this.failed = true;
                this.$refs.link.focus();
                this.$refs.link.select();
            } finally {
                this.busy = false;
            }
        },
    }));

    window.Alpine.data('staffWorkspace', () => ({
        online: navigator.onLine,
        dirty: false,
        editorDismissed: false,
        pendingNavigation: null,
        discardEditorBeforeNavigation: true,
        bypassNavigation: false,
        navigating: false,
        trigger: null,
        abortController: null,
        historyId: null,
        historyIndex: 0,
        nativeHistoryIndex: null,
        historyUrl: null,
        returningToIndex: null,
        unsubscribe: null,
        init() {
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            this.historyId = `${this.$el.getAttribute('wire:id')}:${Date.now()}`;
            this.historyUrl = window.location.href;
            this.stampHistory();
            const markDirty = (event) => {
                if (event.target.closest('[data-staff-editor]')) this.dirty = true;
            };
            this.$el.addEventListener('input', markDirty, options);
            this.$el.addEventListener('change', markDirty, options);
            this.$el.addEventListener('click', (event) => {
                const button = event.target.closest('[wire\\:click]');
                if (button && /^open(Invitation|Member|Assignments|ExistingAssignment)\b/.test(button.getAttribute('wire:click'))) this.trigger = button;
            }, options);
            this.$el.addEventListener('click', async (event) => {
                const button = event.target.closest('[wire\\:click]');
                const action = button?.getAttribute('wire:click') ?? '';
                if (!this.editorDismissed || !this.online || !/^open(Invitation|Member|Assignments|ExistingAssignment)\b/.test(action)) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                try {
                    await this.$wire.discardEditor();
                    this.editorDismissed = false;
                    this.$nextTick(() => [...this.$el.querySelectorAll('[wire\\:click]')].find((element) => element.getAttribute('wire:click') === action)?.click());
                } catch {
                    this.focusError();
                }
            }, { ...options, capture: true });
            this.$el.addEventListener('submit', (event) => {
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
                    const editor = this.$el.querySelector('[data-staff-editor]');
                    if (editor) window.Alpine.$data(editor).present();
                    editor?.querySelector('input:not([type=hidden]), select')?.focus();
                });
            }, options);
            window.addEventListener('staff-editor-closed', () => {
                this.dirty = false;
                this.$nextTick(() => this.restoreFocus());
            }, options);
            window.addEventListener('beforeunload', (event) => {
                if (!this.dirty) return;
                event.preventDefault();
                event.returnValue = '';
            }, options);
            document.addEventListener('livewire:navigate', (event) => {
                if (this.bypassNavigation || !this.dirty) return;
                event.preventDefault();
                const url = event.detail.url.toString();
                this.requestNavigation(() => {
                    this.bypassNavigation = true;
                    window.Livewire.navigate(url);
                });
            }, options);
            window.addEventListener('popstate', (event) => this.guardHistory(event), { ...options, capture: true });
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (message.component.el !== this.$el) return;
                onSuccess(({ onRender }) => onRender(() => queueMicrotask(() => {
                    this.stampHistory();
                    this.focusError();
                })));
            });
        },
        destroy() {
            this.abortController?.abort();
            this.unsubscribe?.();
        },
        focusError() {
            const error = [...this.$el.querySelectorAll('[role=alert]')].find((element) => element.getClientRects().length);
            if (!error) return;
            error.setAttribute('tabindex', '-1');
            error.focus();
        },
        restoreFocus() {
            const target = this.trigger?.isConnected && this.trigger.getClientRects().length && !this.trigger.disabled ? this.trigger : this.$el.closest('[data-staff-workspace]')?.querySelector('[data-staff-heading]');
            target?.focus();
        },
        async dismissEditor() {
            this.$el.closest('[data-staff-workspace]')?.querySelector('[data-staff-editor]')?.close();
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
            this.requestNavigation(async () => {
                this.navigating = true;
                try {
                    await this.$wire.selectSection(section);
                } finally {
                    this.navigating = false;
                }
            });
        },
        requestNavigation(proceed, discardEditor = true) {
            if (!this.dirty) {
                proceed();
                return;
            }
            this.pendingNavigation = proceed;
            this.discardEditorBeforeNavigation = discardEditor;
            this.$flux.modal('staff-workspace-unsaved').show();
        },
        cancelNavigation() {
            this.pendingNavigation = null;
            this.$flux.modal('staff-workspace-unsaved').close();
        },
        async discardAndNavigate() {
            const proceed = this.pendingNavigation;
            if (this.online && this.discardEditorBeforeNavigation) {
                try {
                    await this.$wire.discardEditor();
                } catch {
                    this.focusError();
                    return;
                }
            }
            this.pendingNavigation = null;
            this.dirty = false;
            this.$flux.modal('staff-workspace-unsaved').close();
            this.$nextTick(() => this.restoreFocus());
            proceed?.();
        },
        stampHistory() {
            if (!this.$el.isConnected || this.returningToIndex !== null) return;
            this.nativeHistoryIndex = window.navigation?.currentEntry?.index ?? null;
            if (this.historyUrl !== window.location.href) this.historyIndex++;
            this.historyUrl = window.location.href;
            window.history.replaceState({
                ...window.history.state,
                staffWorkspace: { id: this.historyId, index: this.historyIndex },
            }, '', window.location.href);
        },
        guardHistory(event) {
            // Restore committed history before Livewire swaps the page. Cancelling
            // Navigation API traversals can desynchronize repeated Back in WebKit.
            if (this.nativeHistoryIndex !== null && window.navigation?.currentEntry) {
                const index = window.navigation.currentEntry.index;
                if (this.returningToIndex !== null && index === this.returningToIndex) {
                    event.stopImmediatePropagation();
                    this.returningToIndex = null;
                    return;
                }
                const delta = index - this.nativeHistoryIndex;
                if (delta !== 0 && this.dirty) {
                    event.stopImmediatePropagation();
                    this.returningToIndex = this.nativeHistoryIndex;
                    window.history.go(-delta);
                    this.requestNavigation(() => window.history.go(delta));
                    return;
                }
                this.nativeHistoryIndex = index;
                this.historyUrl = window.location.href;
                return;
            }
            const entry = event.state?.staffWorkspace;
            if (entry?.id !== this.historyId) return;
            if (this.returningToIndex !== null && entry.index === this.returningToIndex) {
                event.stopImmediatePropagation();
                this.returningToIndex = null;
                return;
            }
            const delta = entry.index - this.historyIndex;
            if (delta !== 0 && this.dirty) {
                event.stopImmediatePropagation();
                this.returningToIndex = this.historyIndex;
                window.history.go(-delta);
                this.requestNavigation(() => window.history.go(delta));
                return;
            }
            this.historyIndex = entry.index;
            this.historyUrl = window.location.href;
        },
    }));
}

if (window.Alpine) registerStaffWorkspace();
else document.addEventListener('alpine:init', registerStaffWorkspace, { once: true });
