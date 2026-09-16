export function menuWorkspace() {
    let ownerRoot;
    return {
        destroyed: false,
        nativeHistoryIndex: null,
        dirtyForms: new Map(),
        dirtySections: new Set(),
        pendingNavigation: null,
        revision: 0,
        navigating: false,
        bypassNavigation: false,
        abortController: null,
        unsubscribe: null,
        historyId: null,
        historyIndex: 0,
        historyUrl: null,
        returningToIndex: null,
        init() {
            ownerRoot = this.$el;
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            this.historyId = `${ownerRoot.getAttribute('wire:id')}:${Date.now()}`;
            this.historyUrl = window.location.href;
            this.stampHistory();
            const markDirty = (event) => {
                if (event.target.closest('[data-menu-item-images], [data-section="catalog-transfer"]')) return;
                const form = event.target.closest('form[wire\\:submit]');
                if (!form || !ownerRoot.querySelector('[data-menu-workspace-content]')?.contains(form)) return;
                this.dirtyForms.set(form, ++this.revision);
            };
            ownerRoot.addEventListener('input', markDirty, options);
            ownerRoot.addEventListener('change', markDirty, options);
            ownerRoot.addEventListener('menu-workspace-dirty', (event) => {
                if (typeof event.detail?.key !== 'string') return;
                if (event.detail.dirty) this.dirtySections.add(event.detail.key);
                else this.dirtySections.delete(event.detail.key);
            }, options);
            window.addEventListener('beforeunload', (event) => {
                if (!this.hasUnsavedChanges()) return;
                event.preventDefault();
                event.returnValue = '';
            }, options);
            document.addEventListener('livewire:navigate', (event) => {
                if (this.bypassNavigation || !this.hasUnsavedChanges()) return;
                event.preventDefault();
                const url = event.detail.url.toString();
                this.requestNavigation(() => {
                    this.bypassNavigation = true;
                    window.Livewire.navigate(url);
                });
            }, options);
            window.addEventListener('popstate', (event) => this.guardHistory(event), { ...options, capture: true });
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (!ownerRoot.contains(message.component.el)) return;
                const actions = Array.from(message.actions).map((action) => action.name);
                const submitted = Array.from(this.dirtyForms.entries()).filter(([form]) =>
                    form.closest('[wire\\:id]')?.getAttribute('wire:id') === message.component.id
                    && actions.includes(form.getAttribute('wire:submit')?.split('(')[0].trim())
                );
                onSuccess(({ payload, onRender }) => {
                    onRender(() => {
                        if (this.destroyed || !ownerRoot.isConnected) return;
                        const snapshot = typeof payload.snapshot === 'string' ? JSON.parse(payload.snapshot) : payload.snapshot;
                        if (Object.keys(snapshot?.memo?.errors ?? {}).length === 0) {
                            submitted.forEach(([form, revision]) => {
                                if (this.dirtyForms.get(form) === revision) this.dirtyForms.delete(form);
                            });
                        }
                        queueMicrotask(() => this.stampHistory());
                    });
                });
            });
        },
        destroy() {
            this.destroyed = true;
            this.abortController?.abort();
            this.unsubscribe?.();
            this.unsubscribe = null;
            this.pendingNavigation = null;
            this.dirtyForms.clear();
            this.dirtySections.clear();
        },
        hasUnsavedChanges() {
            for (const form of this.dirtyForms.keys()) {
                if (!form.isConnected) this.dirtyForms.delete(form);
            }
            return this.dirtyForms.size > 0 || this.dirtySections.size > 0;
        },
        navigateSection(event, section) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            event.preventDefault();
            if (this.navigating || !navigator.onLine || section === this.$wire.section) return;
            return this.requestNavigation(async () => {
                this.navigating = true;
                this.setNavigationBusy(true);
                try {
                    await this.$wire.selectSection(section);
                    if (this.destroyed) return;
                    ownerRoot.querySelector(`[data-menu-section="${section}"]`)?.focus();
                } catch {
                    if (this.destroyed) return;
                    ownerRoot.querySelector('[data-menu-workspace-content] [role="alert"]')?.focus();
                } finally {
                    this.navigating = false;
                    if (!this.destroyed) this.setNavigationBusy(false);
                }
            });
        },
        setNavigationBusy(busy) {
            ownerRoot.querySelectorAll('[data-menu-section]').forEach((link) => {
                if (busy) link.setAttribute('aria-disabled', 'true');
                else link.removeAttribute('aria-disabled');
            });
        },
        requestNavigation(proceed) {
            if (this.destroyed) return;
            if (!this.hasUnsavedChanges()) return proceed();
            this.pendingNavigation = proceed;
            this.$flux.modal('menu-workspace-unsaved').show();
        },
        cancelNavigation() {
            this.pendingNavigation = null;
            this.$flux.modal('menu-workspace-unsaved').close();
        },
        discardAndNavigate() {
            const proceed = this.pendingNavigation;
            this.pendingNavigation = null;
            this.dirtyForms.clear();
            this.dirtySections.clear();
            this.$flux.modal('menu-workspace-unsaved').close();
            return proceed?.();
        },
        stampHistory() {
            if (this.destroyed || !ownerRoot.isConnected || this.returningToIndex !== null) return;
            this.nativeHistoryIndex = window.navigation?.currentEntry?.index ?? null;
            if (this.historyUrl !== window.location.href) this.historyIndex++;
            this.historyUrl = window.location.href;
            window.history.replaceState({
                ...window.history.state,
                menuWorkspace: { id: this.historyId, index: this.historyIndex },
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
                if (delta !== 0 && this.hasUnsavedChanges()) {
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
            const entry = event.state?.menuWorkspace;
            if (entry?.id !== this.historyId) return;
            if (this.returningToIndex !== null && entry.index === this.returningToIndex) {
                event.stopImmediatePropagation();
                this.returningToIndex = null;
                return;
            }
            const delta = entry.index - this.historyIndex;
            if (delta !== 0 && this.hasUnsavedChanges()) {
                event.stopImmediatePropagation();
                this.returningToIndex = this.historyIndex;
                window.history.go(-delta);
                this.requestNavigation(() => window.history.go(delta));
                return;
            }
            this.historyIndex = entry.index;
            this.historyUrl = window.location.href;
        },
    };
}
