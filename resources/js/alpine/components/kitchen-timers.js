export function kitchenTimers() {
    const timerBaselines = new WeakMap();

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
        const elapsed = elapsedSeconds(timer);
        const attentionAfter = parseSeconds(timer.dataset.attentionAfterSeconds);
        const delayedAfter = Math.max(attentionAfter, parseSeconds(timer.dataset.delayedAfterSeconds));
        const state = delayState(elapsed, attentionAfter, delayedAfter);
        const value = timer.querySelector('[data-kitchen-delay-value]');
        const status = timer.querySelector('[data-kitchen-delay-status]');
        const overrun = timer.querySelector('[data-kitchen-delay-overrun]');

        timer.dataset.delayState = state;
        timer.dataset.kitchenDelayTimerReady = 'true';

        if (value instanceof HTMLTimeElement) {
            value.textContent = formatDuration(elapsed);
            value.dateTime = `PT${elapsed}S`;
        }

        if (status instanceof HTMLElement) {
            const label = statusLabel(timer, state);

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
        init() {
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            document.addEventListener('visibilitychange', () => this.synchronize(), options);
            document.addEventListener('livewire:navigating', () => this.suspend(), options);
            window.addEventListener('pagehide', () => this.suspend(), options);
            window.addEventListener('pageshow', () => {
                this.suspended = false;
                this.synchronize();
            }, options);
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (message.component.el !== this.$el && !this.$el.contains(message.component.el)) return;
                onSuccess(({ onRender }) => onRender(() => this.synchronize()));
            });
            this.synchronize();
        },
        synchronize() {
            const timers = this.$el.querySelectorAll('[data-kitchen-delay-timer]');
            if (this.suspended || !this.$el.isConnected || document.hidden || timers.length === 0) {
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
            this.suspend();
            this.abortController?.abort();
            this.unsubscribe?.();
            this.unsubscribe = null;
        },
    };
}
