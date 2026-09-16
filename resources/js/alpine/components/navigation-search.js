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
        handleShortcut(event) {
            if (event.key.toLowerCase() !== 'k' || !(event.ctrlKey || event.metaKey)
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
            return this.resultItems
                .find((item) => this.matches(item.dataset.searchLabel))?.querySelector('a');
        },
        focusFirstResult() {
            this.firstResult()?.focus();
        },
        visitFirstResult() {
            this.firstResult()?.click();
        },
    };
}
