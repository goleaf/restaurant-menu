export function branchPickerDisabled() {
    return {
        observer: null,
        init() {
            const syncDisabled = () => this.$el.setAttribute('aria-disabled', this.$el.hasAttribute('disabled') ? 'true' : 'false');
            this.observer = new MutationObserver(syncDisabled);
            this.observer.observe(this.$el, { attributeFilter: ['disabled'] });
            syncDisabled();
        },
        destroy() {
            this.observer?.disconnect();
            this.observer = null;
        },
    };
}
