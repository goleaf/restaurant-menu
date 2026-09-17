export function restoreUpload() {
    return {
        uploading: false,
        offline: false,
        submitting: false,
        init() { this.offline = !navigator.onLine; },
        startUpload() { this.uploading = true; },
        finishUpload() { this.uploading = false; },
        failUpload() { this.uploading = false; },
        cancelUpload() { this.uploading = false; },
        goOnline() { this.offline = false; },
        goOffline() { this.offline = true; },
        submit(event) {
            if (this.uploading || this.offline || this.submitting) event.preventDefault();
            else this.submitting = true;
        },
        destroy() {
            if (this.uploading) this.$wire.$cancelUpload('upload.backup');
            this.uploading = false;
        },
    };
}
