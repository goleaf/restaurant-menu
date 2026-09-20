export function stampWorkspaceHistory(root, key) {
    if (this.destroyed || !root.isConnected || this.returningToIndex !== null) return;
    this.nativeHistoryIndex = window.navigation?.currentEntry?.index ?? null;
    if (this.historyUrl !== window.location.href) this.historyIndex++;
    this.historyUrl = window.location.href;
    window.history.replaceState({
        ...window.history.state,
        [key]: { id: this.historyId, index: this.historyIndex },
    }, '', window.location.href);
}

export function guardWorkspaceHistory(event, key, dirty) {
    // Restore committed history before Livewire swaps the page. Cancelling
    // Navigation API traversals can desynchronize repeated Back in WebKit.
    const native = this.nativeHistoryIndex !== null && window.navigation?.currentEntry;
    const entry = event.state?.[key];
    if (!native && entry?.id !== this.historyId) return;
    const index = native ? native.index : entry.index;
    const previousIndex = native ? this.nativeHistoryIndex : this.historyIndex;
    if (this.returningToIndex !== null && index === this.returningToIndex) {
        event.stopImmediatePropagation();
        this.returningToIndex = null;
        return;
    }
    const delta = index - previousIndex;
    if (delta !== 0 && dirty) {
        event.stopImmediatePropagation();
        this.returningToIndex = previousIndex;
        window.history.go(-delta);
        this.requestNavigation(() => window.history.go(delta));
        return;
    }
    if (native) this.nativeHistoryIndex = index;
    else this.historyIndex = index;
    this.historyUrl = window.location.href;
}
