import assert from 'node:assert/strict';
import { mock, test } from 'node:test';

test('the single bootstrap reapplies offline state after a late render without enabling concurrent online controls', async t => {
    const listeners = new Map(), interceptors = [], events = [];
    const button = { disabled: false };
    const globals = {
        document: { addEventListener: (name, handler) => listeners.set(name, handler), querySelectorAll: () => [] },
        window: { dispatchEvent(event) { events.push(event.type); button.disabled = event.type === 'offline'; } },
        navigator: { onLine: false },
    };
    for (const [name, value] of Object.entries(globals)) {
        const original = Object.getOwnPropertyDescriptor(globalThis, name);
        Object.defineProperty(globalThis, name, { configurable: true, value });
        t.after(() => original ? Object.defineProperty(globalThis, name, original) : delete globalThis[name]);
    }
    mock.module('../vendor/livewire/livewire/dist/livewire.esm.js', { exports: {
        Alpine: { data() {}, bind() {} },
        Livewire: { start() {}, interceptMessage(callback) { interceptors.push(callback); } },
    } });
    t.after(() => mock.restoreAll());
    await import('../resources/js/app.js');
    const renders = [];
    interceptors.forEach(intercept => intercept({ onSuccess: callback => callback({ onRender: render => renders.push(render) }) }));
    listeners.get('livewire:navigated')();
    assert.equal(button.disabled, true);
    button.disabled = false;
    renders.forEach(render => render());
    assert.equal(button.disabled, true);
    assert.deepEqual(events, ['offline', 'offline']);

    navigator.onLine = true;
    button.disabled = true;
    renders.forEach(render => render());
    assert.equal(button.disabled, true);
    assert.deepEqual(events, ['offline', 'offline']);
    listeners.get('livewire:navigated')();
    assert.equal(interceptors.length, 1);
});
