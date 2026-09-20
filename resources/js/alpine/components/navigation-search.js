function acceptsNavigationKey(event) {
    return !event.defaultPrevented && !event.isComposing && !event.ctrlKey
        && !event.metaKey && !event.altKey && !event.shiftKey;
}

function isDishNavigationSync(message, actions) {
    if (message.component.name !== 'organizations.brands.branches.menu.dish' || !actions.every(name => name === '$set')) return false;
    const fields = Object.keys(message.updates ?? {});
    // These Dish hooks only normalize URL state and the accompanying deferred form draft.
    return fields.some(field => field === 'section' || field === 'contentLanguage')
        && fields.every(field => ['section', 'contentLanguage', 'returnFilters', 'editingItemForm'].includes(field.split('.')[0]));
}

let historyTracking;
let historySegment = 0;

function newHistorySegment() {
    return `${Date.now()}:${++historySegment}`;
}

function historyPosition(state) {
    const position = state?.alpine?.workspaceNavigation?.position;
    return Number.isSafeInteger(position) ? position : null;
}

function acquireHistoryTracking() {
    const history = window.history;
    if (!history || !window.location) return () => {};
    if (!historyTracking || historyTracking.history !== history) {
        const tracking = { history, owners: 0, active: true, push: history.pushState, replace: history.replaceState };
        for (const [method, original] of [['pushState', tracking.push], ['replaceState', tracking.replace]]) {
            tracking[method] = function (...args) {
                const state = args[0], previous = history.state?.alpine?.workspaceNavigation;
                if (tracking.active && this === history && args.length >= 2
                    && (state === null || (typeof state === 'object' && Object.getPrototypeOf(state) === Object.prototype))) {
                    const position = historyPosition(history.state);
                    const marker = position === null
                        ? { position: 0, segment: newHistorySegment() }
                        : { position: position + (method === 'pushState' ? 1 : 0), segment: previous.segment };
                    args[0] = { ...state, alpine: { ...state?.alpine, workspaceNavigation: marker } };
                }
                return Reflect.apply(original, this, args);
            };
            history[method] = tracking[method];
        }
        if (historyPosition(history.state) === null) history.replaceState(history.state, '');
        historyTracking = tracking;
    }
    const tracking = historyTracking;
    tracking.owners++;
    let released = false;
    return () => {
        if (released) return;
        released = true;
        tracking.owners--;
        // Livewire writes the destination synchronously after destroying the old body.
        queueMicrotask(() => {
            if (tracking.owners !== 0) return;
            tracking.active = false;
            if (history.pushState === tracking.pushState) history.pushState = tracking.push;
            if (history.replaceState === tracking.replaceState) history.replaceState = tracking.replace;
            if (historyTracking === tracking) historyTracking = null;
        });
    };
}

export function workspaceNavigation() {
    return {
        query: '',
        root: null,
        pending: 0,
        pendingDishSync: 0,
        blocked: false,
        destroyed: false,
        historyPosition: null,
        historyUrl: null,
        historyLength: null,
        historyState: null,
        returningToPosition: null,
        releaseHistory: null,
        abortController: null,
        unsubscribe: null,
        init() {
            this.root = this.$el;
            this.abortController = new AbortController();
            this.releaseHistory = acquireHistoryTracking();
            this.stampHistory();
            window.addEventListener('popstate', event => this.guardHistory(event), { capture: true, signal: this.abortController.signal });
            window.addEventListener('hashchange', event => this.trackHash(event), { signal: this.abortController.signal });
            window.addEventListener('beforeunload', event => {
                if (this.pending === 0) return;
                event.preventDefault();
                event.returnValue = '';
            }, { signal: this.abortController.signal });
            document.addEventListener('livewire:navigate', (event) => {
                if (!event.detail?.history) this.stampHistory();
                if (this.pending === 0 || (event.detail?.history && this.allowsDishHistory(event.detail.url.toString()))) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                this.blocked = true;
            }, { capture: true, signal: this.abortController.signal });
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSend, onSuccess, onFinish }) => {
                const actions = Array.from(message.actions).map((action) => action.name);
                const tracksOperation = !message.component.el.closest('[data-workspace-restaurant], [data-component="notifications-unread-count"]')
                    && !actions.every((name) => name.startsWith('refresh') || name === '$refresh');
                let sent = false, dishSync = false;
                onSend(() => {
                    if (!tracksOperation) return;
                    this.stampHistory();
                    sent = true;
                    dishSync = isDishNavigationSync(message, actions);
                    this.pending++;
                    if (dishSync) this.pendingDishSync++;
                });
                const release = () => {
                    if (sent) {
                        this.pending--;
                        if (dishSync) this.pendingDishSync--;
                        sent = false;
                    }
                    if (this.pending === 0) this.blocked = false;
                };
                onSuccess(({ onSync, onRender }) => {
                    onSync(release);
                    onRender(() => queueMicrotask(() => this.stampHistory()));
                });
                onFinish(release);
            });
        },
        destroy() {
            this.destroyed = true;
            this.abortController?.abort();
            this.unsubscribe?.();
            this.unsubscribe = null;
            this.releaseHistory?.();
            this.releaseHistory = null;
        },
        stampHistory() {
            if (this.destroyed || this.returningToPosition !== null || !window.history || !window.location) return;
            const url = window.location.href;
            if (historyPosition(window.history.state) === null && this.historyUrl !== url && this.sameResource(this.historyUrl, url)) {
                const previous = this.historyState?.alpine?.workspaceNavigation;
                const marker = previous && window.history.length === this.historyLength + 1
                    ? { position: previous.position + 1, segment: previous.segment }
                    : { position: 0, segment: newHistorySegment() };
                // A native hash entry exists before hashchange arrives. Preserve its snapshot
                // before a synchronous Livewire navigation or request can replace that entry.
                const state = { ...this.historyState, alpine: { ...this.historyState?.alpine, url, workspaceNavigation: marker } };
                historyTracking?.replace.call(window.history, state, '', url);
            }
            this.historyPosition = window.navigation?.currentEntry?.index ?? historyPosition(window.history.state);
            this.historyUrl = url;
            this.historyLength = window.history.length;
            this.historyState = window.history.state;
        },
        trackHash(event) {
            if (event.newURL === window.location.href && event.oldURL === this.historyUrl) this.stampHistory();
        },
        sameResource(left, right) {
            if (!left || !right) return false;
            const previous = new URL(left), next = new URL(right);
            previous.hash = '';
            next.hash = '';
            return previous.href === next.href;
        },
        allowsDishHistory(address) {
            if (this.pending === 0 || this.pending !== this.pendingDishSync || !this.historyUrl) return false;
            const previous = new URL(this.historyUrl), next = new URL(address);
            for (const key of ['section', 'language']) {
                previous.searchParams.delete(key);
                next.searchParams.delete(key);
            }
            return previous.href === next.href;
        },
        guardHistory(event) {
            const nativePosition = window.navigation?.currentEntry?.index;
            const position = nativePosition ?? historyPosition(event.state);
            const untracked = !Number.isInteger(position) || this.historyPosition === null
                || (!Number.isInteger(nativePosition) && event.state?.alpine?.workspaceNavigation?.segment !== (this.historyState?.alpine?.workspaceNavigation?.segment ?? null));
            if (this.returningToPosition !== null) {
                event.stopImmediatePropagation();
                if (position === this.returningToPosition) this.returningToPosition = null;
                return;
            }
            if (untracked) {
                if (this.sameResource(this.historyUrl, window.location.href)) return;
                if (this.pending > 0) {
                    event.stopImmediatePropagation();
                    this.blocked = true;
                    window.location.assign(window.location.href);
                }
                return;
            }
            if (this.pending === 0 || this.allowsDishHistory(window.location.href)) {
                this.stampHistory();
                return;
            }
            const delta = position - this.historyPosition;
            if (delta === 0) return;
            event.stopImmediatePropagation();
            this.returningToPosition = this.historyPosition;
            this.blocked = true;
            window.history.go(-delta);
        },
        get resultItems() {
            return [...this.root.querySelectorAll('[data-search-label]')];
        },
        matches(label) {
            const normalize = (value) => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase();
            return normalize(label).includes(normalize(this.query.trim()));
        },
        get hasMatches() {
            return this.resultItems.some((item) => this.matches(item.dataset.searchLabel));
        },
        get resultLinks() {
            return this.resultItems.filter((item) => this.matches(item.dataset.searchLabel))
                .map((item) => item.querySelector('a')).filter(Boolean);
        },
        handleShortcut(event) {
            if (event.defaultPrevented || event.key.toLowerCase() !== 'k' || !(event.ctrlKey || event.metaKey)
                || event.altKey || event.shiftKey || event.repeat || event.isComposing) return;
            const target = event.target;
            if (target.isContentEditable || target.closest('input, textarea, select, [role="textbox"]')
                || document.querySelector('dialog[open]')) return;
            event.preventDefault();
            this.openSearch();
        },
        openSearch() {
            this.query = '';
            this.root.querySelector('[data-navigation-search-trigger]')?.focus();
            this.$flux.modal('workspace-navigation').show();
            this.$nextTick(() => this.root.querySelector('[data-workspace-search-input]')?.focus());
        },
        firstResult() {
            return this.resultLinks[0];
        },
        focusFirstResult() {
            this.firstResult()?.focus();
        },
        visitFirstResult() {
            this.firstResult()?.click();
        },
        handleSearchKeydown(event) {
            if (!acceptsNavigationKey(event) || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
            event.preventDefault();
            if (event.key === 'Enter') this.visitFirstResult();
            else if (event.key === 'ArrowDown') this.focusFirstResult();
            else this.resultLinks.at(-1)?.focus();
        },
        handleResultsKeydown(event) {
            if (!acceptsNavigationKey(event) || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            const links = this.resultLinks;
            const index = links.indexOf(event.target.closest('[data-navigation-search-key]'));
            if (index === -1) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1
                : (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
            links[next].focus();
        },
    };
}
