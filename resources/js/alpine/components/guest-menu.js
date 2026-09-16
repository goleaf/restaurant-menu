export function guestMenu() {
    let ownerRoot;
    return {
        detailsOpen: false,
        init() { ownerRoot = this.$el; },
        closeDetails() {
            this.detailsOpen = false;
            this.$nextTick(() => {
                if (!ownerRoot.isConnected) return;
                const trigger = document.getElementById('guest-menu-item-details-' + this.$wire.selectedItemId);
                const heading = document.getElementById(ownerRoot.dataset.menuHeading);
                (trigger ?? heading)?.focus();
            });
        },
    };
}

export function guestDishDialog() {
    return {
        image: 0,
        sync(open) {
            if (open && !this.$el.open) {
                this.image = 0;
                this.$el.showModal();
            } else if (!open && this.$el.open) this.$el.close();
        },
        destroy() {
            if (this.$el.open) this.$el.close();
        },
    };
}
