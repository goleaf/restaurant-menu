export function guestInvite() {
    let ownerRoot;
    return {
        copied: false,
        copyFailed: false,
        shareFailed: false,
        busy: false,
        epoch: 0,
        focusFrame: null,
        supportsNativeShare: false,
        init() {
            ownerRoot = this.$el;
            this.supportsNativeShare = typeof navigator.share === 'function';
        },
        cancelFocus() {
            if (this.focusFrame !== null) cancelAnimationFrame(this.focusFrame);
            this.focusFrame = null;
        },
        destroy() { this.epoch++; this.cancelFocus(); },
        copyFallback() {
            this.cancelFocus();
            this.copyFailed = true;
            const epoch = this.epoch;
            const input = this.$refs.inviteLink;
            const link = input?.value;
            const isCurrent = () => epoch === this.epoch && this.copyFailed && ownerRoot.isConnected
                && this.$refs.inviteLink === input && input?.value === link;
            this.$nextTick(() => {
                if (!isCurrent()) return;
                this.focusFrame = requestAnimationFrame(() => {
                    this.focusFrame = null;
                    if (!isCurrent()) return;
                    input?.focus();
                    input?.select();
                });
            });
        },
        async copyInvite() {
            if (this.busy) return;
            this.cancelFocus();
            this.busy = true;
            this.copied = false;
            this.copyFailed = false;
            const epoch = ++this.epoch;
            const link = this.$refs.inviteLink.value;
            try {
                if (!navigator.clipboard || !window.isSecureContext) {
                    this.copyFallback();
                    return;
                }
                await navigator.clipboard.writeText(link);
                if (epoch === this.epoch && this.$refs.inviteLink?.value === link) this.copied = true;
            } catch {
                if (epoch === this.epoch && this.$refs.inviteLink?.value === link) this.copyFallback();
            } finally {
                if (epoch === this.epoch) this.busy = false;
            }
        },
        async shareInvite() {
            if (this.busy) return;
            this.cancelFocus();
            this.busy = true;
            this.shareFailed = false;
            const epoch = ++this.epoch;
            try {
                await navigator.share({
                    title: ownerRoot.dataset.inviteTitle,
                    text: ownerRoot.dataset.inviteText,
                    url: this.$refs.inviteLink.value,
                });
            } catch (error) {
                if (epoch === this.epoch && error?.name !== 'AbortError') this.shareFailed = true;
            } finally {
                if (epoch === this.epoch) this.busy = false;
            }
        },
    };
}
