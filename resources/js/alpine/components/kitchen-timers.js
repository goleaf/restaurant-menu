export function kitchenTimers() {
    const timerBaselines = new WeakMap();
    let ownerRoot;
    let revalidationVersion = 0;

    function parseSeconds(value) {
        const seconds = Number.parseInt(value ?? '', 10);

        return Number.isFinite(seconds) && seconds >= 0 ? seconds : 0;
    }

    function formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        const minuteLabel = String(minutes).padStart(2, '0');
        const secondLabel = String(remainingSeconds).padStart(2, '0');

        return hours > 0 ? `${hours}:${minuteLabel}:${secondLabel}` : `${minuteLabel}:${secondLabel}`;
    }

    function elapsedSeconds(timer) {
        const sourceValue = timer.dataset.elapsedSeconds ?? '0';
        const timerStopped = timer.dataset.timerStopped === 'true';
        const sourceKey = `${sourceValue}:${timerStopped}`;
        let baseline = timerBaselines.get(timer);

        if (!baseline || baseline.sourceKey !== sourceKey) {
            baseline = {
                elapsedSeconds: parseSeconds(sourceValue),
                observedAt: Date.now(),
                sourceKey,
            };
            timerBaselines.set(timer, baseline);
        }

        if (timerStopped) {
            return baseline.elapsedSeconds;
        }

        return baseline.elapsedSeconds + Math.max(0, Math.floor((Date.now() - baseline.observedAt) / 1000));
    }

    function delayState(elapsed, attentionAfter, delayedAfter) {
        if (elapsed >= delayedAfter) {
            return 'delayed';
        }

        if (elapsed >= attentionAfter) {
            return 'attention';
        }

        return 'on-track';
    }

    function statusLabel(timer, state) {
        if (state === 'delayed') {
            return timer.dataset.labelDelayed ?? '';
        }

        if (state === 'attention') {
            return timer.dataset.labelAttention ?? '';
        }

        return timer.dataset.labelOnTrack ?? '';
    }

    function updateTimer(timer) {
        const known = timer.dataset.timerKnown !== 'false';
        const productionPhase = !['awaiting_service', 'completed'].includes(timer.dataset.timerPhase);
        const elapsed = elapsedSeconds(timer);
        const attentionAfter = parseSeconds(timer.dataset.attentionAfterSeconds);
        const delayedAfter = Math.max(attentionAfter, parseSeconds(timer.dataset.delayedAfterSeconds));
        const state = known && productionPhase ? delayState(elapsed, attentionAfter, delayedAfter) : 'on-track';
        const value = timer.querySelector('[data-kitchen-delay-value]');
        const status = timer.querySelector('[data-kitchen-delay-status]');
        const overrun = timer.querySelector('[data-kitchen-delay-overrun]');

        timer.dataset.delayState = state;
        timer.dataset.kitchenDelayTimerReady = 'true';

        if (known && value instanceof HTMLTimeElement) {
            value.textContent = formatDuration(elapsed);
            value.dateTime = `PT${elapsed}S`;
        }

        if (status instanceof HTMLElement) {
            const label = known && productionPhase ? statusLabel(timer, state) : '';

            if (status.textContent?.trim() !== label) {
                status.textContent = label;
            }
        }

        if (overrun instanceof HTMLElement) {
            if (state === 'delayed') {
                const delay = formatDuration(Math.max(0, elapsed - delayedAfter));
                overrun.textContent = (timer.dataset.delayTemplate ?? ':time').replace(':time', delay);
                overrun.hidden = false;
            } else {
                overrun.textContent = '';
                overrun.hidden = true;
            }
        }
    }

    return {
        timerInterval: null,
        abortController: null,
        unsubscribe: null,
        suspended: false,
        online: true,
        revalidating: false,
        refreshFailed: false,
        pendingRequests: 0,
        destroyed: false,
        get commandsDisabled() {
            return !this.online || this.revalidating || this.refreshFailed || this.pendingRequests > 0;
        },
        init() {
            ownerRoot = this.$el;
            this.online = navigator.onLine;
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            window.addEventListener('offline', () => {
                this.online = false;
                this.refreshFailed = true;
                this.revalidating = false;
                revalidationVersion++;
            }, options);
            window.addEventListener('online', () => {
                this.online = true;
                this.refreshContext();
            }, options);
            ownerRoot.addEventListener('click', event => {
                if (!this.commandsDisabled || !event.target.closest('[data-preparation-transition], [data-preparation-selection-apply]')) return;
                event.preventDefault();
                event.stopImmediatePropagation();
            }, { ...options, capture: true });
            ownerRoot.addEventListener('preparation-action-finished', () => this.$nextTick(() => {
                if (!this.destroyed && ownerRoot.isConnected) {
                    const destination = ownerRoot.querySelector('[data-preparation-feedback]') ?? ownerRoot.querySelector('[data-preparation-ticket-heading]');
                    destination?.focus();
                }
            }), options);
            document.addEventListener('visibilitychange', () => this.synchronize(), options);
            document.addEventListener('livewire:navigating', () => this.suspend(), options);
            window.addEventListener('pagehide', () => this.suspend(), options);
            window.addEventListener('pageshow', () => {
                this.suspended = false;
                this.synchronize();
            }, options);
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSend, onFinish, onSuccess }) => {
                if (message.component.el !== ownerRoot && !ownerRoot.contains(message.component.el)) return;
                let pending = false;
                const release = () => {
                    if (!pending) return;
                    pending = false;
                    this.pendingRequests = Math.max(0, this.pendingRequests - 1);
                };
                onSend(() => { if (!pending) { pending = true; this.pendingRequests++; } });
                onFinish(() => {
                    if (pending) this.refreshFailed = true;
                    release();
                });
                onSuccess(({ onSync, onRender }) => {
                    onSync(release);
                    onRender(() => this.synchronize());
                });
            });
            this.synchronize();
        },
        async refreshContext() {
            if (!this.online || this.revalidating || this.destroyed || this.pendingRequests > 0) return;
            const version = ++revalidationVersion;
            this.revalidating = true;
            this.refreshFailed = false;
            try {
                await this.$wire.refreshQueue();
                if (this.destroyed || version !== revalidationVersion || !ownerRoot.isConnected) return;
                this.refreshFailed = !this.online;
            } catch {
                if (!this.destroyed && version === revalidationVersion) this.refreshFailed = true;
            } finally {
                if (version === revalidationVersion) this.revalidating = false;
            }
        },
        synchronize() {
            const timers = ownerRoot.querySelectorAll('[data-kitchen-delay-timer]');
            if (this.suspended || !ownerRoot.isConnected || document.hidden || timers.length === 0) {
                this.stop();
                return;
            }
            timers.forEach(updateTimer);
            if (this.timerInterval === null) this.timerInterval = window.setInterval(() => this.synchronize(), 1000);
        },
        stop() {
            if (this.timerInterval !== null) window.clearInterval(this.timerInterval);
            this.timerInterval = null;
        },
        suspend() {
            this.suspended = true;
            this.stop();
        },
        destroy() {
            this.destroyed = true;
            revalidationVersion++;
            this.suspend();
            this.abortController?.abort();
            this.unsubscribe?.();
            this.unsubscribe = null;
        },
    };
}
