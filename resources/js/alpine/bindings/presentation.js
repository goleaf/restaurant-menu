export function dialogLabel() {
    return {
        'x-init'() { this.$el.closest('dialog')?.setAttribute('aria-labelledby', this.$el.id); },
    };
}

export function focusInput() {
    return {
        'x-init'() {
            const element = this.$el;
            this.$nextTick(() => {
                if (element.isConnected) (element.matches('input') ? element : element.querySelector('input'))?.focus();
            });
        },
    };
}

export function focusSelf() {
    return { '@click'() { this.$el.focus(); } };
}

export function printDocument() {
    return { '@click'() { window.print(); } };
}
