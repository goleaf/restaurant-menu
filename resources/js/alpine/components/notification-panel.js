export function notificationPanel() {
    let ownerRoot;
    return {
        panelClosed: false,
        destroyed: false,
        panelReady: false,
        panelFailed: false,
        panelEpoch: 0,
        historyDirection: null,
        focusFrame: null,
        init() { ownerRoot = this.$el; },
        cancelFocus() {
            if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
            this.focusFrame = null;
        },
        closePanel() {
            this.panelClosed = true;
            this.panelEpoch++;
            this.panelReady = false;
            this.cancelFocus();
            this.$wire.$set('panelOpen', false, false);
        },
        destroy() {
            this.destroyed = true;
            this.panelEpoch++;
            this.panelReady = false;
            this.cancelFocus();
        },
        async loadPanel(direction = null) {
            if (this.destroyed) return;
            this.panelClosed = false;
            this.cancelFocus();
            this.historyDirection = direction;
            this.panelReady = false;
            this.panelFailed = false;
            const epoch = ++this.panelEpoch;
            if (!navigator.onLine) {
                this.panelFailed = true;
                return;
            }
            try {
                if (direction === null) await this.$wire.openPanel();
                else await this.$wire.browseHistory(direction);
                if (epoch === this.panelEpoch) {
                    this.panelReady = true;
                    if (direction !== null) this.$nextTick(() => {
                        if (epoch !== this.panelEpoch) return;
                        this.focusFrame = requestAnimationFrame(() => {
                            this.focusFrame = null;
                            if (epoch === this.panelEpoch && this.panelReady) {
                                ownerRoot.querySelector('[data-history-heading]')?.focus();
                            }
                        });
                    });
                } else if (this.panelClosed && !this.destroyed) this.$wire.$set('panelOpen', false, false);
            } catch {
                if (epoch === this.panelEpoch) this.panelFailed = true;
            }
        },
    };
}
