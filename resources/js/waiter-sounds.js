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

let audioContext;
let enabled = readPreference();
let nextPatternStart = 0;
let playbackFailed = false;

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
    document.querySelectorAll('[data-waiter-sounds]').forEach(updateControls);
}

async function play(kind) {
    const AudioContext = audioContextConstructor();
    const pattern = soundPatterns[kind] || soundPatterns.test;

    if (!AudioContext) {
        enabled = false;
        updateAllControls();

        return;
    }

    try {
        audioContext ||= new AudioContext();

        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }

        schedulePattern(audioContext, pattern);
        playbackFailed = false;
    } catch {
        playbackFailed = true;
    }

    updateAllControls();
}

function notify(kind) {
    if (enabled) {
        void play(kind);
    }
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    if (event.target.closest('[data-waiter-sound-toggle]')) {
        enabled = !enabled;
        playbackFailed = false;
        writePreference(enabled);
        updateAllControls();

        if (enabled) {
            void play('test');
        }

        return;
    }

    if (event.target.closest('[data-waiter-sound-test]')) {
        void play('test');
    }
});

window.addEventListener('waiter-new-draft', () => notify('new-draft'));
window.addEventListener('waiter-called', () => notify('waiter-call'));
window.addEventListener('waiter-bill-requested', () => notify('bill-request'));
window.addEventListener('waiter-item-ready', () => notify('ready-item'));
window.addEventListener('storage', (event) => {
    if (event.key === storageKey) {
        enabled = event.newValue === 'true';
        playbackFailed = false;
        updateAllControls();
    }
});

document.addEventListener('livewire:navigated', updateAllControls);

if (window.Livewire) {
    window.Livewire.hook('morphed', ({ el }) => {
        if (el.matches('[data-waiter-sounds]')) {
            updateControls(el);

            return;
        }

        el.querySelectorAll('[data-waiter-sounds]').forEach(updateControls);
    });
}

updateAllControls();
