import assert from 'node:assert/strict';
import test from 'node:test';
import { browser, Element, Time, event } from './alpine-support.mjs';
import { kitchenTimers } from '../resources/js/alpine/components/kitchen-timers.js';

function timerFixture(app, phase = 'received') {
    const component = app.component(kitchenTimers), timer = new Element(), value = new Time(), status = new Element(), overrun = new Element();
    timer.dataset = { elapsedSeconds: '599', attentionAfterSeconds: '600', delayedAfterSeconds: '900', timerStopped: 'false', timerKnown: 'true', timerPhase: phase, labelOnTrack: 'On track', labelAttention: 'Attention', labelDelayed: 'Delayed', delayTemplate: 'Late by :time' };
    timer.children.set('[data-kitchen-delay-value]', [value]);
    timer.children.set('[data-kitchen-delay-status]', [status]);
    timer.children.set('[data-kitchen-delay-overrun]', [overrun]);
    component.$el.children.set('[data-kitchen-delay-timer]', [timer]);
    return { component, timer, value, status, overrun };
}

test('preparation timers preserve phases and never count waiting service as cook delay', t => {
    const app = browser(t), fixture = timerFixture(app, 'awaiting_service');
    fixture.timer.dataset.elapsedSeconds = '1000';
    fixture.component.init();
    assert.equal(fixture.timer.dataset.delayState, 'on-track');
    assert.equal(fixture.status.textContent, '');
    assert.equal(fixture.overrun.hidden, true);
    app.tick(1000);
    assert.equal(fixture.value.textContent, '16:41');
    fixture.component.destroy();
});

test('unknown and completed preparation timers cannot invent elapsed values or urgency', t => {
    const app = browser(t), fixture = timerFixture(app, 'completed');
    fixture.timer.dataset.elapsedSeconds = '1234';
    fixture.timer.dataset.timerStopped = 'true';
    fixture.component.init();
    app.tick(10000);
    assert.equal(fixture.value.textContent, '20:34');
    assert.equal(fixture.timer.dataset.delayState, 'on-track');
    fixture.timer.dataset.timerKnown = 'false';
    fixture.value.textContent = 'Unknown';
    fixture.component.synchronize();
    assert.equal(fixture.value.textContent, 'Unknown');
    assert.equal(fixture.status.textContent, '');
    fixture.component.destroy();
});

test('preparation reconnect performs only a fresh read and keeps commands disabled until it succeeds', async t => {
    const app = browser(t), component = app.component(kitchenTimers), reads = [];
    let resolveRead;
    component.$wire.refreshQueue = () => { reads.push('refreshQueue'); return new Promise(resolve => { resolveRead = resolve; }); };
    component.init();
    app.navigator.onLine = false; app.window.dispatchEvent({ type: 'offline' });
    assert.equal(component.commandsDisabled, true);
    app.navigator.onLine = true; app.window.dispatchEvent({ type: 'online' });
    assert.deepEqual(reads, ['refreshQueue']);
    assert.equal(component.commandsDisabled, true);
    resolveRead(); await Promise.resolve(); await Promise.resolve();
    assert.equal(component.commandsDisabled, false);
    component.destroy();
});

test('failed reconnect keeps stale controls disabled and explicit retry belongs to the captured root', async t => {
    const app = browser(t), component = app.component(kitchenTimers), owner = component.$el;
    component.$wire.refreshQueue = async () => { throw new Error('expired session'); };
    component.init();
    await component.refreshContext();
    assert.equal(component.refreshFailed, true);
    assert.equal(component.commandsDisabled, true);
    component.$el = new Element();
    component.$wire.refreshQueue = async () => {};
    await component.refreshContext();
    assert.equal(component.refreshFailed, false);
    owner.isConnected = false;
    component.destroy();
    assert.equal(app.state.interceptors.size, 0);
    assert.equal(app.window.listenerCount(), 0);
});

test('preparation transitions cannot run offline but local dialog dismissal stays available', t => {
    const app = browser(t), component = app.component(kitchenTimers), button = new Element();
    component.init();
    button.ancestors.set('[data-preparation-transition], [data-preparation-selection-apply]', button);
    app.navigator.onLine = false; app.window.dispatchEvent({ type: 'offline' });
    const blocked = event({ target: button });
    component.$el.dispatchEvent({ ...blocked, type: 'click', preventDefault: () => { blocked.prevented = true; }, stopImmediatePropagation: () => { blocked.stopped = true; } });
    assert.equal(blocked.prevented, true);
    const local = event({ target: new Element() });
    component.$el.dispatchEvent({ ...local, type: 'click', preventDefault: () => { local.prevented = true; } });
    assert.equal(local.prevented, false);
    component.destroy();
});

test('preparation timers share one interval and use fresh monotonic baselines at warning boundaries', t => {
    const app = browser(t), fixture = timerFixture(app), root = fixture.component.$el;
    fixture.component.init();
    assert.equal(fixture.status.textContent, 'On track');
    app.tick(1000); assert.equal(fixture.status.textContent, 'Attention');
    app.tick(301000); assert.equal(fixture.status.textContent, 'Delayed');
    assert.equal(fixture.overrun.textContent, 'Late by 00:01');
    assert.equal(fixture.overrun.hidden, false);
    fixture.timer.dataset.elapsedSeconds = '3601';
    fixture.component.$el = new Element();
    fixture.component.synchronize();
    assert.equal(fixture.value.textContent, '1:00:01');
    assert.equal(app.state.intervals.size, 1);
    fixture.timer.dataset.elapsedSeconds = 'invalid'; fixture.component.synchronize();
    assert.equal(fixture.value.textContent, '00:00');
    assert.equal(fixture.overrun.hidden, true);
    app.document.hidden = true; app.document.dispatchEvent({ type: 'visibilitychange' });
    assert.equal(app.state.intervals.size, 0);
    app.document.hidden = false; app.window.dispatchEvent({ type: 'pageshow' });
    assert.equal(app.state.intervals.size, 1);
    app.window.dispatchEvent({ type: 'pagehide' }); assert.equal(app.state.intervals.size, 0);
    app.window.dispatchEvent({ type: 'pageshow' });
    app.document.dispatchEvent({ type: 'livewire:navigating' }); assert.equal(app.state.intervals.size, 0);
    fixture.component.destroy();
    root.isConnected = false;
});

test('preparation messages release pending controls once and ignore unrelated roots', t => {
    const app = browser(t), component = app.component(kitchenTimers), root = component.$el, feedback = new Element();
    root.children.set('[data-preparation-feedback]', [feedback]);
    component.init();
    const unrelated = app.message({ el: new Element() }); unrelated.send();
    assert.equal(component.pendingRequests, 0);
    const message = app.message({ el: root });
    message.send(); message.send(); assert.equal(component.pendingRequests, 1);
    assert.equal(component.commandsDisabled, true);
    message.sync(); assert.equal(component.pendingRequests, 0);
    message.finish(); message.finish(); assert.equal(component.pendingRequests, 0);
    root.dispatchEvent({ type: 'preparation-action-finished' });
    assert.equal(feedback.focused, 1);
    component.destroy();
    root.dispatchEvent({ type: 'preparation-action-finished' });
    assert.equal(feedback.focused, 1);
});

test('an unanswered preparation message blocks mutations until an explicit successful refresh', async t => {
    const app = browser(t), component = app.component(kitchenTimers);
    component.init();
    let send, finish;
    [...app.state.interceptors][0]({ message: { component: { el: component.$el } }, onSend: callback => { send = callback; }, onFinish: callback => { finish = callback; }, onSuccess() {} });
    send(); finish();
    assert.equal(component.refreshFailed, true);
    assert.equal(component.commandsDisabled, true);
    component.$wire.refreshQueue = async () => {};
    await component.refreshContext();
    assert.equal(component.commandsDisabled, false);
    component.destroy();
});

test('late reconnect responses cannot enable a disconnected or replaced preparation surface', async t => {
    const app = browser(t), component = app.component(kitchenTimers);
    let finish;
    component.$wire.refreshQueue = () => new Promise(resolve => { finish = resolve; });
    component.init();
    const first = component.refreshContext();
    await component.refreshContext();
    app.window.dispatchEvent({ type: 'offline' });
    finish(); await first;
    assert.equal(component.commandsDisabled, true);
    await component.refreshContext();
    component.online = true;
    const second = component.refreshContext();
    component.destroy(); finish(); await second;
    assert.equal(component.destroyed, true);
    await component.refreshContext();
});
