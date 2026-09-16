import assert from 'node:assert/strict';
import test from 'node:test';
import { kitchenTimers } from '../resources/js/alpine/components/kitchen-timers.js';
import { waiterSounds } from '../resources/js/alpine/components/waiter-sounds.js';
import { browser, Button, Element, Time } from './alpine-support.mjs';

function runtime(t, { enabled = false, suspended = false } = {}) {
    const app = browser(t);
    const state = Object.assign(app.state, { constructed: 0, closed: 0, notes: 0, noteStarts: [], oscillators: [], disconnected: 0 });
    const timer = new Element();
    timer.dataset = { elapsedSeconds: '600', attentionAfterSeconds: '600', delayedAfterSeconds: '900', labelAttention: 'Attention', labelDelayed: 'Delayed', labelOnTrack: 'On track', delayTemplate: 'Late :time' };
    timer.children.set('[data-kitchen-delay-value]', [new Time()]);
    timer.children.set('[data-kitchen-delay-status]', [new Element()]);
    timer.children.set('[data-kitchen-delay-overrun]', [new Element()]);
    const toggle = new Button();
    toggle.selector = '[data-waiter-sound-toggle]';
    const testButton = new Button();
    testButton.selector = '[data-waiter-sound-test]';
    const preference = new Map(enabled ? [['restaurant-menu:waiter-sounds-enabled', 'true']] : []);
    app.window.localStorage = { getItem: key => preference.get(key) ?? null, setItem: (key, value) => preference.set(key, value) };
    let resume;
    app.window.AudioContext = class {
        state = suspended ? 'suspended' : 'running';
        currentTime = 0;
        constructor() { state.constructed++; state.context = this; }
        resume() { return new Promise((resolve, reject) => { resume = { resolve, reject }; }); }
        close() { state.closed++; this.state = 'closed'; return Promise.resolve(); }
        createOscillator() {
            state.notes++;
            const oscillator = { frequency: { setValueAtTime() {} }, connect() {}, disconnect() { state.disconnected++; }, start(value) { state.noteStarts.push(value); }, stop() {} };
            state.oscillators.push(oscillator);
            return oscillator;
        }
        createGain() { return { gain: { setValueAtTime() {}, exponentialRampToValueAtTime() {} }, connect() {}, disconnect() { state.disconnected++; } }; }
    };
    return {
        ...app, timer, toggle, testButton,
        mountTimers() {
            const instance = app.component(kitchenTimers);
            instance.$el.children.set('[data-kitchen-delay-timer]', [timer]);
            instance.init();
            return instance;
        },
        mountSounds() {
            const instance = app.component(waiterSounds);
            instance.$el.children.set(toggle.selector, [toggle]);
            instance.$el.children.set(testButton.selector, [testButton]);
            instance.$el.children.set('[data-waiter-sound-label]', ['enable', 'disable'].map(label => { const el = new Element(); el.setAttribute('data-waiter-sound-label', label); return el; }));
            instance.$el.children.set('[data-waiter-sound-status]', ['enabled', 'disabled', 'unavailable', 'failed'].map(status => { const el = new Element(); el.setAttribute('data-waiter-sound-status', status); return el; }));
            instance.init();
            return instance;
        },
        click(instance, target) { instance.$el.dispatchEvent({ type: 'click', target }); },
        async resume(fail = false) { if (fail) resume?.reject(new Error('Blocked')); else resume?.resolve(); await new Promise(setImmediate); },
        status(instance) { return instance.$el.querySelectorAll('[data-waiter-sound-status]').find(el => !el.hidden)?.getAttribute('data-waiter-sound-status'); },
    };
}

test('kitchen timer belongs to its visible dashboard and is disposed across repeated navigation', t => {
    const app = runtime(t);
    assert.equal(app.state.intervals.size, 0);
    const instance = app.mountTimers();
    instance.synchronize();
    assert.equal(app.state.intervals.size, 1);
    app.tick(2000);
    assert.equal(app.timer.querySelector('[data-kitchen-delay-value]').textContent, '10:02');
    app.document.hidden = true;
    app.document.dispatchEvent({ type: 'visibilitychange' });
    assert.equal(app.state.intervals.size, 0);
    app.tick(5000);
    app.document.hidden = false;
    app.document.dispatchEvent({ type: 'visibilitychange' });
    assert.equal(app.timer.querySelector('[data-kitchen-delay-value]').textContent, '10:07');
    app.document.dispatchEvent({ type: 'livewire:navigating' });
    assert.equal(app.state.intervals.size, 0);
    app.message({ el: instance.$el }).finish();
    assert.equal(app.state.intervals.size, 0);
    instance.destroy();
    for (let visit = 0; visit < 10; visit++) {
        const next = app.mountTimers();
        assert.equal(app.state.intervals.size, 1);
        next.destroy();
        assert.equal(app.state.intervals.size, 0);
        assert.equal(app.state.interceptors.size, 0);
        assert.equal(app.document.listenerCount() + app.window.listenerCount(), 0);
    }
});

test('timer morphs are component-scoped and stopped values survive page hide and restore', t => {
    const app = runtime(t);
    const instance = app.mountTimers();
    app.timer.dataset.elapsedSeconds = '300';
    app.timer.dataset.timerStopped = 'true';
    app.message({ el: new Element() }).finish();
    assert.equal(app.timer.querySelector('[data-kitchen-delay-value]').textContent, '10:00');
    app.message({ el: instance.$el }).finish();
    app.tick(10000);
    assert.equal(app.timer.querySelector('[data-kitchen-delay-value]').textContent, '05:00');
    app.window.dispatchEvent({ type: 'pagehide' });
    assert.equal(app.state.intervals.size, 0);
    app.window.dispatchEvent({ type: 'pageshow' });
    assert.equal(app.state.intervals.size, 1);
    instance.$el.isConnected = false;
    instance.synchronize();
    assert.equal(app.state.intervals.size, 0);
    instance.destroy();
});

test('timer formatting, thresholds, malformed values and missing optional nodes retain safe presentation', t => {
    const app = runtime(t);
    const instance = app.mountTimers();
    for (const [seconds, formatted, state, overrun] of [['0', '00:00', 'on-track', ''], ['600', '10:00', 'attention', ''], ['901', '15:01', 'delayed', 'Late 00:01'], ['3601', '1:00:01', 'delayed', 'Late 45:01']]) {
        app.timer.dataset.elapsedSeconds = seconds;
        instance.synchronize();
        assert.equal(app.timer.querySelector('[data-kitchen-delay-value]').textContent, formatted);
        assert.equal(app.timer.dataset.delayState, state);
        assert.equal(app.timer.querySelector('[data-kitchen-delay-overrun]').textContent, overrun);
    }
    app.timer.dataset = { elapsedSeconds: '-5', attentionAfterSeconds: 'bad' };
    instance.synchronize();
    assert.equal(app.timer.querySelector('[data-kitchen-delay-value]').textContent, '00:00');
    assert.equal(app.timer.querySelector('[data-kitchen-delay-status]').textContent, '');
    app.timer.dataset = {};
    app.timer.children.clear();
    instance.synchronize();
    instance.$el.children.clear();
    instance.synchronize();
    assert.equal(app.state.intervals.size, 0);
    instance.destroy();
});

test('cached waiter module has no guest import effects or remaining event subscription after destroy', t => {
    const app = runtime(t, { enabled: true });
    assert.equal(app.state.constructed, 0);
    const instance = app.mountSounds();
    assert.equal(app.state.constructed, 0);
    instance.destroy();
    app.window.dispatchEvent({ type: 'waiter-called' });
    app.click(instance, app.testButton);
    instance.toggle();
    assert.equal(app.state.constructed, 0);
    assert.equal(app.document.listenerCount() + app.window.listenerCount() + instance.$el.listenerCount(), 0);
    assert.equal(app.state.interceptors.size, 0);
});

test('leaving waiter closes audio and a new instance preserves explicit preferences without autoplay', t => {
    const app = runtime(t);
    const instance = app.mountSounds();
    app.click(instance, app.toggle);
    assert.equal(app.state.constructed, 1);
    assert.equal(app.state.notes, 1);
    app.document.dispatchEvent({ type: 'livewire:navigating' });
    assert.equal(app.state.closed, 1);
    app.window.dispatchEvent({ type: 'waiter-called' });
    assert.equal(app.state.notes, 1);
    instance.destroy();
    const returned = app.mountSounds();
    assert.equal(app.toggle.getAttribute('aria-pressed'), 'true');
    assert.equal(app.state.constructed, 1);
    app.click(returned, app.testButton);
    assert.equal(app.state.constructed, 2);
    assert.equal(app.state.notes, 2);
    app.click(returned, app.toggle);
    app.window.dispatchEvent({ type: 'waiter-called' });
    assert.equal(app.state.notes, 2);
    returned.destroy();
});

test('pending audio resume cannot schedule notes after destruction or hide', async t => {
    const app = runtime(t, { suspended: true });
    const instance = app.mountSounds();
    app.click(instance, app.toggle);
    app.window.dispatchEvent({ type: 'pagehide' });
    await app.resume();
    assert.equal(app.state.notes, 0);
    app.window.dispatchEvent({ type: 'pageshow' });
    app.click(instance, app.testButton);
    instance.destroy();
    await app.resume(true);
    assert.equal(app.state.notes, 0);
});

test('audio failures and unavailable browser support remain visible without uncaught promises', async t => {
    const app = runtime(t, { suspended: true });
    const instance = app.mountSounds();
    app.click(instance, app.toggle);
    await app.resume(true);
    assert.equal(app.status(instance), 'failed');
    app.click(instance, app.testButton);
    await app.resume();
    assert.equal(app.status(instance), 'enabled');
    app.state.context.close = async () => { throw new Error('Closed externally'); };
    app.click(instance, app.toggle);
    await new Promise(setImmediate);
    delete app.window.AudioContext;
    app.click(instance, app.testButton);
    assert.equal(app.status(instance), 'unavailable');
    assert.equal(app.toggle.disabled, true);
    instance.destroy();
});

test('sound controls update only their owner and local preference errors remain recoverable', t => {
    const app = runtime(t);
    app.window.localStorage.getItem = () => { throw new Error('Denied'); };
    app.window.localStorage.setItem = () => { throw new Error('Denied'); };
    const instance = app.mountSounds();
    app.click(instance, null);
    app.click(instance, new Element());
    app.window.dispatchEvent({ type: 'storage', key: 'other', newValue: 'true' });
    assert.equal(app.toggle.getAttribute('aria-pressed'), 'false');
    app.click(instance, app.toggle);
    for (const type of ['waiter-new-draft', 'waiter-called', 'waiter-bill-requested', 'waiter-item-ready']) app.window.dispatchEvent({ type });
    assert.equal(app.state.notes, 9);
    assert.ok(app.state.noteStarts[1] > app.state.noteStarts[0]);
    app.toggle.disabled = true;
    app.message({ el: new Element() }).finish();
    assert.equal(app.toggle.disabled, true);
    const parent = new Element();
    parent.children.set('sound-root', [instance.$el]);
    app.message({ el: parent }).finish();
    assert.equal(app.toggle.disabled, false);
    app.window.dispatchEvent({ type: 'storage', key: 'restaurant-menu:waiter-sounds-enabled', newValue: 'false' });
    assert.equal(app.status(instance), 'disabled');
    app.window.dispatchEvent({ type: 'storage', key: 'restaurant-menu:waiter-sounds-enabled', newValue: 'true' });
    assert.equal(app.state.constructed, 1);
    instance.$el.children.clear();
    app.message({ el: instance.$el }).finish();
    instance.$el.isConnected = false;
    app.message({ el: instance.$el }).finish();
    instance.destroy();
});


test('completed notes disconnect both audio nodes without retaining the dashboard', t => {
    const app = runtime(t);
    const instance = app.mountSounds();
    app.click(instance, app.testButton);
    assert.equal(typeof app.state.oscillators[0].onended, 'function');
    app.state.oscillators[0].onended();
    assert.equal(app.state.disconnected, 2);
    instance.destroy();
});
