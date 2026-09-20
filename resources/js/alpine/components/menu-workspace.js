import { stampWorkspaceHistory, guardWorkspaceHistory } from './workspace-history.js';

export function menuWorkspace(configuration = {}) {
    let ownerRoot, ownerAddress;
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
        focusFrame: null,
        init() {
            ownerRoot = this.$el;
            ownerAddress = window.location.href;
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            if (configuration.invalidEvent) {
                window.addEventListener(configuration.invalidEvent, event => {
                    this.$nextTick(() => {
                        if (this.destroyed) return;
                        if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
                        this.focusFrame = requestAnimationFrame(() => {
                            this.focusFrame = null;
                            const selector = event.detail?.target ? `#${CSS.escape(event.detail.target)}` : configuration.invalidSelector;
                            if (!this.destroyed && ownerRoot.isConnected) ownerRoot.querySelector(selector)?.focus();
                        });
                    });
                }, options);
            }
            this.historyId = `${ownerRoot.getAttribute('wire:id')}:${Date.now()}`;
            this.historyUrl = window.location.href;
            this.stampHistory();
            const markDirty = (event) => {
                if (event.target.closest('[data-menu-item-images], [data-section="catalog-transfer"]') || event.target.closest('[data-menu-preview]') || event.target.closest('[data-menu-search]')) return;
                const form = event.target.closest('form[wire\\:submit]');
                if (!form || !ownerRoot.querySelector(configuration.contentSelector ?? '[data-menu-workspace-content]')?.contains(form)) return;
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
                const url = event.detail.url.toString();
                if (configuration.retainSectionDrafts && event.detail.history && this.retainsDraftsAt(url)) {
                    event.preventDefault();
                    return;
                }
                if (this.bypassNavigation || !this.hasUnsavedChanges()) return;
                event.preventDefault();
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
                    && actions.some(action => (configuration.cleanFormActions?.[action] ?? action) === form.getAttribute('wire:submit')?.split('(')[0].trim())
                );
                onSuccess(({ payload, onSync, onRender }) => {
                    onSync(() => {
                        if (this.destroyed || !ownerRoot.isConnected) return;
                        const snapshot = typeof payload.snapshot === 'string' ? JSON.parse(payload.snapshot) : payload.snapshot;
                        if (Object.keys(snapshot?.memo?.errors ?? {}).length === 0) {
                            if (actions.some((action) => configuration.cleanActions?.includes(action))) this.dirtyForms.clear();
                            submitted.forEach(([form, revision]) => {
                                if (this.dirtyForms.get(form) === revision) this.dirtyForms.delete(form);
                            });
                        }
                    });
                    onRender(() => queueMicrotask(() => this.stampHistory()));
                });
            });
        },
        destroy() {
            this.destroyed = true;
            this.abortController?.abort();
            if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
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
            const proceed = async () => {
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
            };
            return configuration.retainSectionDrafts ? proceed() : this.requestNavigation(proceed);
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
            this.$flux.modal(configuration.modal ?? 'menu-workspace-unsaved').show();
        },
        cancelNavigation() {
            this.pendingNavigation = null;
            this.$flux.modal(configuration.modal ?? 'menu-workspace-unsaved').close();
        },
        discardAndNavigate() {
            const proceed = this.pendingNavigation;
            this.pendingNavigation = null;
            this.dirtyForms.clear();
            this.dirtySections.clear();
            this.$flux.modal(configuration.modal ?? 'menu-workspace-unsaved').close();
            return proceed?.();
        },
        discardFormLocally(event, model, baseline) {
            event.preventDefault();
            event.stopImmediatePropagation();
            const form = event.currentTarget.closest('form');
            this.$wire.$set(model, JSON.parse(JSON.stringify(this.$wire.$get(baseline))), false);
            this.dirtyForms.delete(form);
            form.querySelectorAll('[role="alert"]').forEach(error => { error.hidden = true; });
            form.querySelectorAll('[aria-invalid="true"]').forEach(field => field.setAttribute('aria-invalid', 'false'));
            form.querySelectorAll('[data-invalid="true"]').forEach(panel => panel.setAttribute('data-invalid', 'false'));
            form.dispatchEvent(new CustomEvent('menu-form-discarded'));
        },
        stampHistory() {
            stampWorkspaceHistory.call(this, ownerRoot, 'menuWorkspace');
        },
        guardHistory(event) {
            if (this.returningToIndex === null && configuration.retainSectionDrafts && this.retainsDraftsAt(window.location.href)) {
                this.historyIndex = event.state?.menuWorkspace?.index ?? this.historyIndex;
                this.nativeHistoryIndex = window.navigation?.currentEntry?.index ?? null;
                this.historyUrl = window.location.href;
                return;
            }
            guardWorkspaceHistory.call(this, event, 'menuWorkspace', this.hasUnsavedChanges());
        },
        retainsDraftsAt(address) {
            const previous = new URL(ownerAddress), next = new URL(address);
            for (const key of configuration.retainedParameters ?? ['section', 'language']) {
                previous.searchParams.delete(key);
                next.searchParams.delete(key);
            }
            return previous.href === next.href;
        },
    };
}
