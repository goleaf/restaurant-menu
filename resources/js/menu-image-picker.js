function registerImagePicker() {
    window.Alpine.data('menuImagePicker', (config) => ({
        previews: [], uploading: false, failed: false, progress: 0, dragging: false,
        preview(event) {
            this.clearPreviews();
            this.previews = Array.from(event.target.files ?? []).map((file, index) => ({
                key: `${file.name}-${file.size}-${index}`, name: file.name, url: URL.createObjectURL(file),
            }));
        },
        drop(event) {
            this.dragging = false;
            if (this.uploading || !event.dataTransfer?.files.length) return;
            this.$refs.files.files = event.dataTransfer.files;
            this.$refs.files.dispatchEvent(new Event('change', { bubbles: true }));
        },
        async remove(index) {
            if (this.uploading) return;
            await this.$wire.removePendingItemImage(config.itemId, index);
            URL.revokeObjectURL(this.previews[index].url);
            this.previews.splice(index, 1);
        },
        clearPreviews() { this.previews.forEach((preview) => URL.revokeObjectURL(preview.url)); this.previews = []; },
        clear() { this.clearPreviews(); if (this.$refs.files) this.$refs.files.value = ''; this.failed = false; },
        destroy() { this.clearPreviews(); },
    }));
}
if (window.Alpine) registerImagePicker();
else document.addEventListener('alpine:init', registerImagePicker, { once: true });
