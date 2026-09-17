export function twoFactorChallenge() {
    return {
        showRecoveryInput: false,
        focusFrame: null,
        focusEpoch: 0,
        destroyed: false,
        init() {
            this.showRecoveryInput = this.$el.dataset.recovery === 'true';
            this.$watch('$wire.recovery', value => {
                this.showRecoveryInput = value;
                this.focusInput();
            });
            this.focusInput();
        },
        cancelFocus() {
            if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
            this.focusFrame = null;
        },
        focusInput() {
            const epoch = ++this.focusEpoch;
            this.cancelFocus();
            this.$nextTick(() => {
                if (this.destroyed || epoch !== this.focusEpoch) return;
                this.focusFrame = requestAnimationFrame(() => {
                    this.focusFrame = null;
                    if (this.destroyed || epoch !== this.focusEpoch || !this.$el.isConnected) return;
                    const input = this.showRecoveryInput ? this.$refs.recovery_code : this.$refs.otp?.querySelector('input');
                    input?.focus();
                });
            });
        },
        destroy() {
            this.destroyed = true;
            this.focusEpoch++;
            this.cancelFocus();
        },
    };
}
