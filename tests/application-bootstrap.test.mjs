import assert from 'node:assert/strict';
import { mock, test } from 'node:test';

test('one bootstrap registers every factory before starting Livewire and releases direct/history roots', async () => {
    const providers = new Map();
    const bindings = new Map();
    const listeners = new Map();
    const removed = [];
    const status = { hidden: false };
    const root = { removeAttribute: name => removed.push(name) };
    let starts = 0;
    globalThis.document = {
        addEventListener: (name, handler, options) => listeners.set(name, { handler, options }),
        querySelectorAll: selector => selector === '[data-page-module]' ? [root] : [status],
    };
    mock.module('../vendor/livewire/livewire/dist/livewire.esm.js', { exports: {
        Alpine: { data: (name, factory) => providers.set(name, factory), bind: (name, binding) => bindings.set(name, binding) },
        Livewire: { interceptMessage() {}, start() {
            assert.ok(providers.has('menuWorkspace'));
            assert.ok(providers.has('passkeyVerification'));
            assert.ok(bindings.has('dialogLabel'));
            starts++;
            listeners.get('alpine:init').handler();
        } },
    } });
    await import('../resources/js/app.js');
    assert.equal(starts, 1);
    assert.deepEqual(removed, ['x-ignore', 'inert']);
    assert.equal(status.hidden, true);
    assert.equal(listeners.get('alpine:init').options.once, true);
    status.hidden = false;
    listeners.get('livewire:navigating').handler({ detail: { onSwap(callback) { callback(); } } });
    assert.deepEqual(removed, ['x-ignore', 'inert', 'x-ignore', 'inert']);
    assert.equal(status.hidden, true);
    const connectivityEvents = [];
    globalThis.window = { dispatchEvent: event => connectivityEvents.push(event.type) };
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { onLine: true } });
    listeners.get('livewire:navigated').handler();
    navigator.onLine = false;
    listeners.get('livewire:navigated').handler();
    assert.deepEqual(connectivityEvents, ['online', 'offline']);
    mock.restoreAll();
    delete globalThis.document;
    delete globalThis.window;
    delete globalThis.navigator;
});
