const storageKey = 'restaurant-menu:waiter-sounds-enabled';

const soundPatterns = Object.freeze({
    'new-draft': [
        { frequency: 659, offset: 0, duration: 0.12 },
        { frequency: 784, offset: 0.15, duration: 0.14 },
    ],
    'waiter-call': [
        { frequency: 880, offset: 0, duration: 0.16 },
        { frequency: 880, offset: 0.22, duration: 0.16 },
    ],
    'bill-request': [
        { frequency: 523, offset: 0, duration: 0.14 },
        { frequency: 659, offset: 0.17, duration: 0.16 },
    ],
    'ready-item': [
        { frequency: 784, offset: 0, duration: 0.1 },
        { frequency: 1046, offset: 0.12, duration: 0.12 },
    ],
    test: [{ frequency: 740, offset: 0, duration: 0.16 }],
});

export function waiterSounds() {
    let audioContext;
    let enabled = false;
    let root;
    let nextPatternStart = 0;
    let playbackFailed = false;
    let navigating = false;

    function waiterSurface() {
        return navigating || !root?.isConnected ? null : root;
    }

    function releaseAudio() {
        const context = audioContext;
        audioContext = undefined;
        nextPatternStart = 0;
        if (context && context.state !== 'closed') void context.close().catch(() => {});
    }

    function audioContextConstructor() {
        return window.AudioContext || window.webkitAudioContext;
    }

    function readPreference() {
        try {
            return window.localStorage.getItem(storageKey) === 'true';
        } catch {
            return false;
        }
    }

    function writePreference(enabled) {
        try {
            window.localStorage.setItem(storageKey, enabled.toString());
        } catch {
            return;
        }
    }

    function schedulePattern(context, pattern) {
        const startAt = Math.max(context.currentTime + 0.02, nextPatternStart);

        pattern.forEach(({ frequency, offset, duration }) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const noteStart = startAt + offset;
            const noteEnd = noteStart + duration;

            oscillator.onended = () => {
                oscillator.disconnect();
                gain.disconnect();
                oscillator.onended = null;
            };
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(frequency, noteStart);
            gain.gain.setValueAtTime(0.0001, noteStart);
            gain.gain.exponentialRampToValueAtTime(0.07, noteStart + 0.015);
            gain.gain.exponentialRampToValueAtTime(0.0001, noteEnd);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.start(noteStart);
            oscillator.stop(noteEnd + 0.01);
        });

        const patternDuration = Math.max(...pattern.map(({ offset, duration }) => offset + duration));
        nextPatternStart = startAt + patternDuration + 0.06;
    }

    function currentStatus(supported) {
        if (!supported) {
            return 'unavailable';
        }

        if (playbackFailed) {
            return 'failed';
        }

        return enabled ? 'enabled' : 'disabled';
    }

    function updateControls(root) {
        const supported = Boolean(audioContextConstructor());
        const status = currentStatus(supported);
        const toggle = root.querySelector('[data-waiter-sound-toggle]');
        const testButton = root.querySelector('[data-waiter-sound-test]');

        root.setAttribute('data-waiter-sounds-ready', 'true');

        if (toggle instanceof HTMLButtonElement) {
            toggle.disabled = !supported;
            toggle.setAttribute('aria-pressed', enabled.toString());
        }

        if (testButton instanceof HTMLButtonElement) {
            testButton.disabled = !supported;
        }

        root.querySelectorAll('[data-waiter-sound-label]').forEach((label) => {
            label.hidden = label.getAttribute('data-waiter-sound-label') !== (enabled ? 'disable' : 'enable');
        });

        root.querySelectorAll('[data-waiter-sound-status]').forEach((message) => {
            message.hidden = message.getAttribute('data-waiter-sound-status') !== status;
        });
    }

    function updateAllControls() {
        if (!waiterSurface()) {
            releaseAudio();
            return;
        }
        updateControls(root);
    }

    async function play(kind) {
        const surface = waiterSurface();
        if (!surface) return;

        const AudioContext = audioContextConstructor();
        const pattern = soundPatterns[kind] || soundPatterns.test;

        if (!AudioContext) {
            enabled = false;
            updateAllControls();

            return;
        }

        let context;
        try {
            context = audioContext ||= new AudioContext();

            if (context.state === 'suspended') {
                await context.resume();
            }

            if (waiterSurface() !== surface || audioContext !== context) return;

            schedulePattern(context, pattern);
            playbackFailed = false;
        } catch {
            if (waiterSurface() === surface && audioContext === context) playbackFailed = true;
        }

        updateAllControls();
    }

    function notify(kind) {
        if (enabled) {
            void play(kind);
        }
    }


    return {
        abortController: null,
        unsubscribe: null,
        init() {
            root = this.$el;
            enabled = readPreference();
            this.abortController = new AbortController();
            const options = { signal: this.abortController.signal };
            root.addEventListener('click', (event) => {
                if (!(event.target instanceof Element)) return;
                if (event.target.closest('[data-waiter-sound-toggle]')) this.toggle();
                else if (event.target.closest('[data-waiter-sound-test]')) void play('test');
            }, options);
            const notifications = {
                'waiter-new-draft': 'new-draft',
                'waiter-called': 'waiter-call',
                'waiter-bill-requested': 'bill-request',
                'waiter-item-ready': 'ready-item',
            };
            Object.entries(notifications).forEach(([event, kind]) => {
                window.addEventListener(event, () => notify(kind), options);
            });
            window.addEventListener('storage', (event) => {
                if (event.key !== storageKey) return;
                enabled = event.newValue === 'true';
                playbackFailed = false;
                if (!enabled) releaseAudio();
                updateAllControls();
            }, options);
            document.addEventListener('livewire:navigating', () => this.suspend(), options);
            window.addEventListener('pagehide', () => this.suspend(), options);
            window.addEventListener('pageshow', () => {
                navigating = false;
                updateAllControls();
            }, options);
            this.unsubscribe = window.Livewire.interceptMessage(({ message, onSuccess }) => {
                if (message.component.el !== root && !message.component.el.contains(root)) return;
                onSuccess(({ onRender }) => onRender(updateAllControls));
            });
            updateAllControls();
        },
        toggle() {
            if (!waiterSurface()) return;
            enabled = !enabled;
            playbackFailed = false;
            writePreference(enabled);
            updateAllControls();
            if (enabled) void play('test');
            else releaseAudio();
        },
        suspend() {
            navigating = true;
            releaseAudio();
        },
        destroy() {
            this.suspend();
            this.abortController?.abort();
            this.unsubscribe?.();
            this.unsubscribe = null;
            root = null;
        },
    };
}
