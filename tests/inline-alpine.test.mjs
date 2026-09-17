import assert from 'node:assert/strict';
import test from 'node:test';
import { connectivity } from '../resources/js/alpine/components/connectivity.js';
import { httpForm } from '../resources/js/alpine/components/http-form.js';
import { twoFactorChallenge } from '../resources/js/alpine/components/two-factor.js';
import { branchPickerDisabled } from '../resources/js/alpine/components/branch-picker.js';
import { notificationPanel } from '../resources/js/alpine/components/notification-panel.js';
import { guestMenu, guestDishDialog } from '../resources/js/alpine/components/guest-menu.js';
import { guestInvite } from '../resources/js/alpine/components/guest-invite.js';
import { securityClipboard } from '../resources/js/alpine/components/security-clipboard.js';
import { passkeyRegistration, passkeyVerification } from '../resources/js/alpine/components/passkeys.js';
import { createPasskeysAdapter } from '../resources/js/integrations/passkeys.js';

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}
function browser({ online = true, clipboard = async () => {}, share, secure = true } = {}) {
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { onLine: online, clipboard: { writeText: clipboard }, share } });
    globalThis.window = { isSecureContext: secure, setTimeout, clearTimeout, Livewire: { navigate() {} } };
    globalThis.document = { getElementById() { return null; } };
    globalThis.requestAnimationFrame = callback => { callback(); return 1; };
    globalThis.cancelAnimationFrame = () => {};
}
function scope(factory, extra = {}) {
    return Object.assign(factory, { $el: { dataset: {}, isConnected: true }, $refs: {}, $nextTick: callback => callback(), ...extra });
}

// These tests execute the exported production factories, mocking only browser and SDK boundaries.
test('connectivity and HTTP forms prevent offline or duplicate submissions and recover after cached navigation', () => {
    browser({ online: false });
    const status = connectivity();
    status.init();
    assert.equal(status.online, false);
    status.goOnline();
    assert.equal(status.online, true);
    status.goOffline();
    assert.equal(status.online, false);
    const form = httpForm();
    form.init();
    let prevented = 0;
    const event = { preventDefault() { prevented++; } };
    form.submit(event);
    assert.equal(prevented, 1);
    form.goOnline();
    form.submit(event);
    assert.equal(form.submitting, true);
    form.submit(event);
    assert.equal(prevented, 2);
    form.goOffline();
    assert.equal(form.offline, true);
    navigator.onLine = true;
    form.restore();
    assert.equal(form.submitting, false);
    assert.equal(form.offline, false);
});

test('two factor focus follows server mode changes and cancels stale focus after destruction', t => {
    const ticks = [], frames = new Map(), focused = [];
    let frameId = 0, visible = 'otp', modeChanged;
    const originalRequest = globalThis.requestAnimationFrame, originalCancel = globalThis.cancelAnimationFrame;
    globalThis.requestAnimationFrame = callback => { frames.set(++frameId, callback); return frameId; };
    globalThis.cancelAnimationFrame = id => frames.delete(id);
    t.after(() => { globalThis.requestAnimationFrame = originalRequest; globalThis.cancelAnimationFrame = originalCancel; });
    const input = kind => ({ focus() { assert.equal(visible, kind, 'focus must follow x-show visibility'); focused.push(kind); } });
    const component = scope(twoFactorChallenge(), {
        $el: { dataset: { recovery: 'false' }, isConnected: true },
        $watch: (name, callback) => { assert.equal(name, '$wire.recovery'); modeChanged = callback; },
        $refs: { otp: { querySelector: () => input('otp') }, recovery_code: input('recovery') },
        $nextTick: callback => ticks.push(callback),
    });
    const render = mode => {
        ticks.splice(0).forEach(callback => callback());
        visible = mode;
        const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback());
    };
    component.init(); render('otp'); assert.deepEqual(focused, ['otp']);
    const toggle = () => modeChanged(!component.showRecoveryInput);
    toggle();
    assert.equal(component.showRecoveryInput, true);
    ticks.splice(0).forEach(callback => callback());
    assert.deepEqual(focused, ['otp']);
    assert.equal(frames.size, 1);
    visible = 'recovery'; const recoveryFrame = [...frames.values()][0]; frames.clear(); recoveryFrame();
    assert.deepEqual(focused, ['otp', 'recovery']);
    toggle(); render('otp'); assert.deepEqual(focused, ['otp', 'recovery', 'otp']);
    toggle(); ticks.splice(0).forEach(callback => callback());
    toggle(); assert.equal(frames.size, 0); render('otp');
    toggle(); toggle(); render('otp');
    const count = focused.length;
    toggle(); ticks.splice(0).forEach(callback => callback());
    const stale = [...frames.values()][0]; component.destroy(); assert.equal(frames.size, 0); stale();
    assert.equal(focused.length, count);
    const restored = scope(twoFactorChallenge(), { $el: { dataset: { recovery: 'true' }, isConnected: true }, $watch: (name, callback) => { modeChanged = callback; }, $refs: { recovery_code: input('recovery') }, $nextTick: callback => ticks.push(callback) });
    restored.init(); assert.equal(restored.showRecoveryInput, true); render('recovery');
    modeChanged(false); restored.destroy(); render('otp');
    assert.equal(frames.size, 0);
});

test('branch radio compatibility owns and disconnects only its disabled observer', () => {
    let callback, disconnected = 0;
    globalThis.MutationObserver = class { constructor(cb) { callback = cb; } observe(el, options) { assert.deepEqual(options, { attributeFilter: ['disabled'] }); } disconnect() { disconnected++; } };
    let disabled = false, announced;
    const component = scope(branchPickerDisabled(), { $el: { hasAttribute: () => disabled, setAttribute: (name, value) => { assert.equal(name, 'aria-disabled'); announced = value; } } });
    component.init(); assert.equal(announced, 'false');
    disabled = true; callback(); assert.equal(announced, 'true');
    component.destroy(); assert.equal(disconnected, 1);
    component.destroy(); assert.equal(disconnected, 1);
});

test('notification history retains its root when a pagination button invokes loading', async () => {
    browser();
    let focused = 0;
    const component = scope(notificationPanel(), { $wire: { async browseHistory() {} }, $el: { querySelector: () => ({ focus() { focused++; } }) } });
    component.init();
    component.$el = { querySelector: () => null };
    await component.loadPanel('older');
    assert.equal(focused, 1);
});

test('guest detail dismissal retains its menu heading through a nested dialog scope', () => {
    browser();
    let focused = 0;
    document.getElementById = id => id === 'menu-heading' ? { focus() { focused++; } } : null;
    const component = scope(guestMenu(), { $el: { dataset: { menuHeading: 'menu-heading' }, isConnected: true }, $wire: { selectedItemId: 42 } });
    component.init();
    component.$el = { dataset: {}, isConnected: true };
    component.closeDetails();
    assert.equal(focused, 1);
});

test('native sharing reads current localized configuration from its owning invite root', async () => {
    let shared;
    browser({ share: async data => { shared = data; } });
    const root = { dataset: { inviteTitle: 'First title', inviteText: 'First text' }, isConnected: true };
    const component = scope(guestInvite(), { $el: root, $refs: { inviteLink: { value: 'private-link' } } });
    component.init();
    component.$el = { dataset: {}, isConnected: true };
    root.dataset.inviteTitle = 'Localized title';
    root.dataset.inviteText = 'Localized text';
    await component.shareInvite();
    assert.deepEqual(shared, { title: 'Localized title', text: 'Localized text', url: 'private-link' });
});

test('notification opening is read-only and history focus follows rendering', async () => {
    browser();
    const calls = [], focused = [];
    const component = scope(notificationPanel(), { $wire: { async openPanel() { calls.push('open'); }, async browseHistory(direction) { calls.push(direction); }, $set(...args) { calls.push(args); } }, $el: { querySelector: () => ({ focus() { focused.push('history'); } }) } });
    component.init();
    await component.loadPanel();
    assert.deepEqual(calls, ['open']); assert.equal(component.panelReady, true);
    await component.loadPanel('older');
    assert.deepEqual(calls, ['open', 'older']); assert.deepEqual(focused, ['history']);
    component.closePanel();
    assert.equal(component.panelReady, false); assert.deepEqual(calls.at(-1), ['panelOpen', false, false]);
    component.destroy();
});

test('notification close or destroy invalidates late replies and queued focus', async () => {
    browser();
    const request = deferred(), calls = [];
    let frame;
    globalThis.requestAnimationFrame = callback => { frame = callback; return 7; };
    globalThis.cancelAnimationFrame = id => calls.push(['cancel', id]);
    const component = scope(notificationPanel(), { $wire: { openPanel: () => request.promise, async browseHistory() {}, $set: (...args) => calls.push(args) }, $el: { querySelector() { throw new Error('Late focus'); } } });
    component.init();
    const opening = component.loadPanel(); component.closePanel(); request.resolve(); await opening;
    assert.equal(component.panelReady, false);
    await component.loadPanel('newer'); component.closePanel(); frame();
    await component.loadPanel('latest'); component.destroy(); frame();
    assert.ok(calls.some(value => value[0] === 'cancel'));
    const rejected = deferred();
    const returned = scope(notificationPanel(), { $wire: { openPanel: () => rejected.promise } });
    returned.init();
    const pending = returned.loadPanel(); returned.destroy(); rejected.reject(new Error('Lost')); await pending;
    assert.equal(returned.panelFailed, false);
});

test('notification offline and failed loading never imply success and allow retry', async () => {
    browser({ online: false });
    let calls = 0;
    const component = scope(notificationPanel(), { $wire: { async openPanel() { calls++; throw new Error('Unavailable'); } } });
    component.init();
    await component.loadPanel(); assert.equal(calls, 0); assert.equal(component.panelFailed, true);
    navigator.onLine = true;
    await component.loadPanel(); assert.equal(calls, 1); assert.equal(component.panelFailed, true); assert.equal(component.panelReady, false);
});

test('guest details retain native dialog state and restore the trigger or surviving heading', () => {
    browser();
    const focused = [];
    const heading = { focus() { focused.push('heading'); } };
    const trigger = { focus() { focused.push('trigger'); } };
    const menu = scope(guestMenu(), { $el: { dataset: { menuHeading: 'heading' }, isConnected: true }, $wire: { selectedItemId: 42 } });
    menu.init();
    document.getElementById = id => id === 'guest-menu-item-details-42' ? trigger : heading;
    menu.detailsOpen = true; menu.closeDetails(); assert.equal(menu.detailsOpen, false); assert.deepEqual(focused, ['trigger']);
    document.getElementById = id => id === 'heading' ? heading : null;
    menu.closeDetails(); assert.deepEqual(focused, ['trigger', 'heading']);
    document.getElementById = () => null; menu.closeDetails();
    menu.$el.isConnected = false; menu.closeDetails();
    let shown = 0, closed = 0;
    const dialog = scope(guestDishDialog(), { $el: { open: false, showModal() { this.open = true; shown++; }, close() { this.open = false; closed++; } } });
    dialog.image = 3; dialog.sync(true); assert.equal(dialog.image, 0); assert.equal(shown, 1);
    dialog.sync(true); assert.equal(shown, 1);
    dialog.sync(false); dialog.sync(false); assert.equal(closed, 1);
    dialog.destroy(); dialog.sync(true); dialog.destroy(); assert.equal(closed, 2);
});

test('guest clipboard confirms real writes and presents a manual fallback after rejection', async () => {
    let copied;
    browser({ clipboard: async text => { copied = text; } });
    const component = scope(guestInvite(), { $el: { dataset: { inviteTitle: 'Title', inviteText: 'Text' }, isConnected: true }, $refs: { inviteLink: { value: 'private-link', focus() {}, select() {} } } });
    component.init(); assert.equal(component.supportsNativeShare, false);
    await component.copyInvite(); assert.equal(copied, 'private-link'); assert.equal(component.copied, true);
    navigator.clipboard.writeText = async () => { throw new Error('Denied'); };
    await component.copyInvite(); assert.equal(component.copied, false); assert.equal(component.copyFailed, true);
    window.isSecureContext = false; await component.copyInvite(); assert.equal(component.copyFailed, true);
    delete navigator.clipboard; await component.copyInvite(); assert.equal(component.copyFailed, true);
    component.destroy();
});

test('guest sharing handles cancel separately from failure and ignores stale clipboard replies', async () => {
    let shared;
    browser({ share: async data => { shared = data; } });
    const component = scope(guestInvite(), { $el: { dataset: { inviteTitle: 'Title', inviteText: 'Text' }, isConnected: true }, $refs: { inviteLink: { value: 'private-link', focus() {}, select() {} } } });
    component.init(); assert.equal(component.supportsNativeShare, true);
    await component.shareInvite(); assert.deepEqual(shared, { title: 'Title', text: 'Text', url: 'private-link' });
    navigator.share = async () => { throw Object.assign(new Error('Cancelled'), { name: 'AbortError' }); };
    await component.shareInvite(); assert.equal(component.shareFailed, false);
    navigator.share = async () => { throw new Error('Denied'); };
    await component.shareInvite(); assert.equal(component.shareFailed, true);
    const delayed = deferred(); navigator.clipboard.writeText = () => delayed.promise;
    const writing = component.copyInvite(); await component.copyInvite(); component.destroy(); delayed.resolve(); await writing;
    assert.equal(component.copied, false);
    component.$refs.inviteLink = undefined; component.copyFallback();
});

test('security clipboard owns its reset timer and keeps denied copying visible without logs', async () => {
    const timeouts = new Map(); let id = 0, writes = 0;
    browser({ clipboard: async () => { writes++; } });
    window.setTimeout = callback => { timeouts.set(++id, callback); return id; }; window.clearTimeout = key => timeouts.delete(key);
    const component = scope(securityClipboard(), { $refs: { setupKey: { value: 'private-key', focus() {}, select() {} } } });
    await component.copy(); assert.equal(component.copied, true); assert.equal(writes, 1);
    await component.copy(); assert.equal(timeouts.size, 1);
    const [timerId, timer] = [...timeouts.entries()][0]; timeouts.delete(timerId); timer(); assert.equal(component.copied, false);
    navigator.clipboard.writeText = async () => { throw new Error('Blocked'); };
    await component.copy(); assert.equal(component.failed, true);
    delete navigator.clipboard; await component.copy(); assert.equal(component.failed, true);
    component.$refs.setupKey = undefined; await component.copy();
    component.destroy(); assert.equal(timeouts.size, 0);
});

function passkeysHarness(factory) {
    browser();
    const calls = [];
    const adapter = { isSupported: () => true, register: async (_owner, data) => calls.push(['register', data]), verify: async (_owner, data) => { calls.push(['verify', data]); return { redirect: '/authorized' }; }, cancel: _owner => calls.push(['cancel']), isCancelled: error => error?.name === 'UserCancelledError' };
    const component = scope(factory(adapter), { $el: { dataset: { failure: 'Localized failure', optionsUrl: '/options', submitUrl: '/submit', fallbackUrl: '/dashboard' }, isConnected: true }, $wire: { async loadPasskeys() { calls.push(['refresh']); } } });
    window.Livewire.navigate = url => calls.push(['navigate', url]);
    component.init();
    return { component, adapter, calls };
}

test('passkey configuration belongs to its root when a child invokes a controller method', async () => {
    const registration = passkeysHarness(passkeyRegistration);
    registration.component.$el = { dataset: {} };
    registration.component.name = 'Test device';
    registration.adapter.register = async () => { throw new Error('Device unavailable'); };
    await registration.component.register();
    assert.equal(registration.component.error, 'Localized failure');

    const verification = passkeysHarness(passkeyVerification);
    verification.component.$el = { dataset: {} };
    let requested;
    verification.adapter.verify = async (_owner, options) => { requested = options; return {}; };
    await verification.component.verify();
    assert.deepEqual(requested.routes, { options: '/options', submit: '/submit' });
    assert.deepEqual(verification.calls.at(-1), ['navigate', '/dashboard']);
});

test('passkey registration blocks empty/duplicate attempts, clears successful input and localizes failures', async () => {
    const { component, adapter, calls } = passkeysHarness(passkeyRegistration);
    assert.equal(component.supported, true);
    await component.register(); assert.deepEqual(calls, []);
    component.name = ' Device '; component.showForm = true;
    await component.register(); assert.deepEqual(calls, [['register', { name: 'Device' }], ['refresh']]);
    assert.equal(component.name, ''); assert.equal(component.showForm, false); assert.equal(component.loading, false);
    component.name = 'Retry'; adapter.register = async () => { throw new Error('Internal English details'); };
    await component.register(); assert.equal(component.error, 'Localized failure'); assert.equal(component.name, 'Retry');
    adapter.register = async () => { throw Object.assign(new Error(), { name: 'UserCancelledError' }); };
    await component.register(); assert.equal(component.error, null);
    component.cancel(); assert.equal(component.name, ''); assert.equal(component.showForm, false);
    component.destroy();
});

test('passkey registration cancel/destroy rejects late state and refreshes only its successful current operation', async () => {
    const { component, adapter, calls } = passkeysHarness(passkeyRegistration);
    const pending = deferred(); adapter.register = () => pending.promise;
    component.name = 'Device'; const registering = component.register(); await component.register();
    component.cancel(); pending.resolve(); await registering;
    assert.equal(calls.some(call => call[0] === 'refresh'), false);
    const failed = deferred(); adapter.register = () => failed.promise;
    component.name = 'Device'; const later = component.register(); component.destroy(); failed.reject(new Error('late')); await later;
    assert.equal(component.error, null);
    component.supported = false; await component.register();
});

test('passkey verification uses prepared endpoints, fallback navigation and localized SDK errors', async () => {
    const { component, adapter, calls } = passkeysHarness(passkeyVerification);
    await component.verify(); assert.deepEqual(calls, [['verify', { routes: { options: '/options', submit: '/submit' } }], ['navigate', '/authorized']]);
    adapter.verify = async () => ({}); await component.verify(); assert.deepEqual(calls.at(-1), ['navigate', '/dashboard']);
    adapter.verify = async () => { throw new Error('Private response'); }; await component.verify(); assert.equal(component.error, 'Localized failure');
    adapter.verify = async () => { throw Object.assign(new Error(), { name: 'UserCancelledError' }); }; await component.verify(); assert.equal(component.error, null);
    component.supported = false; await component.verify(); component.destroy();
});

test('passkey verification neither duplicates a ceremony nor navigates after destruction', async () => {
    const { component, adapter, calls } = passkeysHarness(passkeyVerification);
    const pending = deferred(); adapter.verify = () => pending.promise;
    const verifying = component.verify(); await component.verify(); component.destroy(); pending.resolve({ redirect: '/private' }); await verifying;
    assert.equal(calls.some(call => call[0] === 'navigate'), false);
    const failure = deferred(); adapter.verify = () => failure.promise;
    const retry = component.verify(); component.destroy(); failure.reject(new Error('late')); await retry;
    assert.equal(component.error, null);
});

function passkeyTransport(sdk, load) {
    browser();
    window.location = { href: 'https://menu.test/login', origin: 'https://menu.test' };
    document.querySelector = () => null;
    document.cookie = '';
    return createPasskeysAdapter(sdk, load, async (_url, init) => ({ ok: true, json: async () => init.method === 'GET' ? { options: {} } : { redirect: '/' } }));
}

test('passkeys adapter keeps SDK ceremonies and cancels only the current owner operation', async () => {
    const calls = [], owner = {}, other = {};
    const sdk = {
        browserSupportsWebAuthn: () => true,
        async startRegistration(options) { calls.push(['register', options]); return {}; },
        async startAuthentication(options) { calls.push(['verify', options]); return {}; },
        WebAuthnAbortService: { cancelCeremony() { calls.push(['cancel']); } },
    };
    const adapter = passkeyTransport(sdk);
    assert.equal(adapter.isSupported(), true);
    await adapter.register(owner, { name: 'device' }); await adapter.verify(owner, { routes: {} });
    adapter.cancel(owner); assert.equal(calls.filter(call => call[0] === 'cancel').length, 0);
    const pending = deferred(); sdk.startRegistration = () => pending.promise;
    const first = adapter.register(owner, {}); await new Promise(setImmediate);
    adapter.cancel(other); adapter.cancel(owner); pending.resolve(); await assert.rejects(first, error => adapter.isCancelled(error));
    assert.equal(calls.filter(call => call[0] === 'cancel').length, 1);
    assert.equal(adapter.isCancelled({ name: 'UserCancelledError' }), true);
    assert.equal(adapter.isCancelled(new Error()), false);
    assert.equal(adapter.isCancelled(null), false);
    const failed = deferred(); sdk.startAuthentication = () => failed.promise;
    const last = adapter.verify(other, {}); await new Promise(setImmediate);
    failed.reject(new Error('failure')); await assert.rejects(last); adapter.cancel(other);
    assert.equal(calls.filter(call => call[0] === 'cancel').length, 1);
});

test('an older notification response cannot close a newer panel request', async () => {
    browser();
    const first = deferred(), second = deferred(), calls = [];
    let attempt = 0;
    const component = scope(notificationPanel(), { $wire: { openPanel: () => ++attempt === 1 ? first.promise : second.promise, $set: (...args) => calls.push(args) } });
    component.init();
    const old = component.loadPanel(), current = component.loadPanel();
    second.resolve(); await current; first.resolve(); await old;
    assert.equal(component.panelReady, true); assert.deepEqual(calls, []);
    component.destroy();
});

test('lazy passkeys adapter loads only on operation and cancellation during import never begins a ceremony', async () => {
    let loaded = 0, registered = 0;
    const loading = deferred();
    const sdk = { browserSupportsWebAuthn: () => true, startRegistration: async () => { registered++; return {}; }, WebAuthnAbortService: { cancelCeremony() {} } };
    const adapter = passkeyTransport(null, () => { loaded++; return loading.promise; });
    const owner = {};
    assert.equal(loaded, 0);
    const pending = adapter.register(owner, { name: 'Device' }); adapter.cancel(owner); loading.resolve(sdk);
    await assert.rejects(pending, error => adapter.isCancelled(error));
    assert.equal(loaded, 1); assert.equal(registered, 0);
    await adapter.register(owner, { name: 'Device' }); assert.equal(loaded, 1); assert.equal(registered, 1);
});

test('passkeys adapter retries a failed SDK import and loads the official SDK without starting unsupported authentication', async () => {
    const owner = {}; let attempts = 0;
    const sdk = { browserSupportsWebAuthn: () => true, async startRegistration() { return {}; } };
    const retrying = passkeyTransport(null, async () => { if (++attempts === 1) throw new Error('Chunk unavailable'); return sdk; });
    assert.equal(retrying.isSupported(), false);
    await assert.rejects(retrying.register(owner, {}), /Chunk unavailable/);
    assert.deepEqual(await retrying.register(owner, {}), { redirect: '/' }); assert.equal(attempts, 2);
    const official = createPasskeysAdapter();
    await assert.rejects(official.verify(owner, {}), error => error.name === 'NotSupportedError');
});

test('guest clipboard fallback waits for the x-show animation frame before selecting the link', async () => {
    browser({ clipboard: async () => { throw new Error('Denied'); } });
    let visible = false, focused = false;
    const frames = new Map(); let nextFrame = 0;
    globalThis.requestAnimationFrame = callback => { frames.set(++nextFrame, callback); return nextFrame; };
    globalThis.cancelAnimationFrame = id => frames.delete(id);
    const component = scope(guestInvite(), { $refs: { inviteLink: { value: 'current-link', focus() { focused = visible; }, select() {} } } });
    component.init();
    await component.copyInvite();
    assert.equal(focused, false);
    visible = true;
    for (const [id, callback] of frames) { frames.delete(id); callback(); }
    assert.equal(focused, true);
    await component.copyInvite();
    assert.equal(frames.size, 1);
    component.destroy();
    assert.equal(frames.size, 0);
});

test('guest clipboard fallback waits for Alpine to reveal the manual-copy input before focusing it', async () => {
    browser({ clipboard: async () => { throw new Error('Denied'); } });
    const ticks = [], focused = [];
    let visible = false;
    const input = {
        value: 'current-link',
        focus() { if (visible) focused.push('focus'); },
        select() { if (visible) focused.push('select'); },
    };
    const component = scope(guestInvite(), { $refs: { inviteLink: input }, $nextTick: callback => ticks.push(callback) });
    component.init();
    await component.copyInvite();
    assert.equal(component.copyFailed, true);
    assert.deepEqual(focused, []);
    assert.equal(ticks.length, 1);
    visible = true;
    ticks.shift()();
    assert.deepEqual(focused, ['focus', 'select']);
    component.destroy();
});

test('guest clipboard fallback ignores deferred focus after destruction or replacement of its link', () => {
    browser();
    for (const change of ['destroy', 'value', 'element', 'removed', 'hidden', 'retry', 'disconnected']) {
        const ticks = [], focused = [];
        const input = { value: 'first-link', focus() { focused.push('focus'); }, select() { focused.push('select'); } };
        const component = scope(guestInvite(), { $refs: { inviteLink: input }, $nextTick: callback => ticks.push(callback) });
        component.init();
        component.copyFallback();
        if (change === 'destroy') component.destroy();
        if (change === 'value') input.value = 'new-link';
        if (change === 'element') component.$refs.inviteLink = { ...input };
        if (change === 'removed') component.$refs.inviteLink = undefined;
        if (change === 'hidden') component.copyFailed = false;
        if (change === 'retry') component.epoch++;
        if (change === 'disconnected') component.$el.isConnected = false;
        ticks.forEach(callback => callback());
        assert.deepEqual(focused, [], change);
        component.destroy();
    }
});

test('a denied clipboard write for a replaced guest link does not reveal or focus the newer link', async () => {
    const pending = deferred();
    browser({ clipboard: () => pending.promise });
    const ticks = [];
    const input = { value: 'first-link', focus() { throw new Error('Stale focus'); }, select() {} };
    const component = scope(guestInvite(), { $refs: { inviteLink: input }, $nextTick: callback => ticks.push(callback) });
    component.init();
    const copying = component.copyInvite();
    input.value = 'new-link';
    pending.reject(new Error('Denied'));
    await copying;
    assert.equal(component.copyFailed, false);
    assert.equal(ticks.length, 0);
    component.destroy();
});
