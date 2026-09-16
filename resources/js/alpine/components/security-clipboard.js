export function securityClipboard() {
    return {
        copied: false,
        failed: false,
        epoch: 0,
        resetTimer: null,
        clearTimer() {
            if (this.resetTimer !== null) window.clearTimeout(this.resetTimer);
            this.resetTimer = null;
        },
        destroy() {
            this.epoch++;
            this.clearTimer();
        },
        async copy() {
            this.clearTimer();
            this.copied = false;
            this.failed = false;
            const epoch = ++this.epoch;
            const key = this.$refs.setupKey?.value;
            if (!key) return;
            try {
                if (!navigator.clipboard || !window.isSecureContext) throw new Error('Clipboard unavailable');
                await navigator.clipboard.writeText(key);
                if (epoch !== this.epoch || this.$refs.setupKey?.value !== key) return;
                this.copied = true;
                this.resetTimer = window.setTimeout(() => {
                    this.copied = false;
                    this.resetTimer = null;
                }, 1500);
            } catch {
                if (epoch !== this.epoch) return;
                this.failed = true;
                this.$refs.setupKey?.focus();
                this.$refs.setupKey?.select();
            }
        },
    };
}
