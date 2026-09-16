export function httpForm() {
    return {
        submitting: false,
        offline: false,
        init() { this.restore(); },
        restore() {
            this.submitting = false;
            this.offline = !navigator.onLine;
        },
        goOnline() { this.offline = false; },
        goOffline() { this.offline = true; },
        submit(event) {
            if (this.offline || this.submitting) event.preventDefault();
            else this.submitting = true;
        },
    };
}
