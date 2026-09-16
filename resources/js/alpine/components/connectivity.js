export function connectivity() {
    return {
        online: true,
        init() { this.online = navigator.onLine; },
        goOnline() { this.online = true; },
        goOffline() { this.online = false; },
    };
}
