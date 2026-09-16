function acceptsNavigationKey(event) {
    return !event.defaultPrevented && !event.isComposing && !event.ctrlKey
        && !event.metaKey && !event.altKey && !event.shiftKey;
}

export function workspaceNavigation() {
    return {
        query: '',
        root: null,
        init() {
            this.root = this.$el;
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
