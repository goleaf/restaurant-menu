function registerMenuWorkspace() {
    window.Alpine.data('menuWorkspace', () => ({
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
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            this.historyId = `${this.$el.getAttribute('wire:id')}:${Date.now()}`;
            this.historyUrl = window.location.href;
            this.stampHistory();
            const markDirty = (event) => {
                if (event.target.closest('[data-menu-item-images], [data-section="catalog-transfer"]')) return;
                const form = event.target.closest('form[wire\\:submit]');
                if (!form || !this.$el.querySelector('[data-menu-workspace-content]')?.contains(form)) return;
                this.dirtyForms.set(form, ++this.revision);
            };
            this.$el.addEventListener('input', markDirty, options);
            this.$el.addEventListener('change', markDirty, options);
            this.$el.addEventListener('menu-workspace-dirty', (event) => {
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
            if (window.navigation) {
                window.navigation.addEventListener('navigate', (event) => {
                    if (event.navigationType !== 'traverse' || !event.cancelable || !this.hasUnsavedChanges()) return;
                    const destination = new URL(event.destination.url);
                    if (destination.pathname === window.location.pathname && destination.searchParams.get('section') === new URL(window.location.href).searchParams.get('section')) return;
                    event.preventDefault();
                    this.requestNavigation(() => window.navigation.traverseTo(event.destination.key));
                }, options);
            }
            window.addEventListener('popstate', (event) => this.guardHistory(event), { ...options, capture: true });
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (!this.$el.contains(message.component.el)) return;
                const actions = Array.from(message.actions).map((action) => action.name);
                const submitted = Array.from(this.dirtyForms.entries()).filter(([form]) =>
                    form.closest('[wire\\:id]')?.getAttribute('wire:id') === message.component.id
                    && actions.includes(form.getAttribute('wire:submit')?.split('(')[0].trim())
                );
                onSuccess(({ payload, onRender }) => {
                    onRender(() => {
                        if (!this.$el.isConnected) return;
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
            this.abortController?.abort();
            this.unsubscribe?.();
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
            this.requestNavigation(async () => {
                this.navigating = true;
                this.setNavigationBusy(true);
                try {
                    await this.$wire.selectSection(section);
                    this.$el.querySelector(`[data-menu-section="${section}"]`)?.focus();
                } catch {
                    this.$el.querySelector('[data-menu-workspace-content] [role="alert"]')?.focus();
                } finally {
                    this.navigating = false;
                    this.setNavigationBusy(false);
                }
            });
        },
        setNavigationBusy(busy) {
            this.$el.querySelectorAll('[data-menu-section]').forEach((link) => {
                if (busy) link.setAttribute('aria-disabled', 'true');
                else link.removeAttribute('aria-disabled');
            });
        },
        requestNavigation(proceed) {
            if (!this.hasUnsavedChanges()) {
                proceed();
                return;
            }
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
            proceed?.();
        },
        stampHistory() {
            if (!this.$el.isConnected || this.returningToIndex !== null) return;
            if (this.historyUrl !== window.location.href) this.historyIndex++;
            this.historyUrl = window.location.href;
            window.history.replaceState({
                ...window.history.state,
                menuWorkspace: { id: this.historyId, index: this.historyIndex },
            }, '', window.location.href);
        },
        guardHistory(event) {
            const entry = event.state?.menuWorkspace;
            if (entry?.id !== this.historyId) return;
            if (this.returningToIndex !== null && entry.index === this.returningToIndex) {
                event.stopImmediatePropagation();
                this.returningToIndex = null;
                return;
            }
            const delta = entry.index - this.historyIndex;
            if (delta !== 0 && this.hasUnsavedChanges() && !window.navigation) {
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

if (window.Alpine) registerMenuWorkspace();
else document.addEventListener('alpine:init', registerMenuWorkspace, { once: true });
