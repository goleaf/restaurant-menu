import assert from 'node:assert/strict';
import test from 'node:test';
import { createPasskeysAdapter } from '../resources/js/integrations/passkeys.js';
import { browser } from './alpine-support.mjs';

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}
const flush = () => new Promise(setImmediate);
const json = value => ({ ok: true, json: async () => value });

function environment(t) {
    const app = browser(t), calls = [];
    app.window.location = { href: 'https://menu.test/settings/security', origin: 'https://menu.test' };
    app.document.querySelector = () => ({ getAttribute: () => 'csrf-meta' });
    app.document.cookie = '';
    const sdk = {
        browserSupportsWebAuthn: () => true,
        startRegistration: async options => { calls.push(['register', options]); return { id: 'attestation' }; },
        startAuthentication: async options => { calls.push(['verify', options]); return { id: 'assertion' }; },
        WebAuthnAbortService: { cancelCeremony() { calls.push(['cancel']); } },
    };
    const requests = [];
    const request = async (url, init) => {
        requests.push({ url, ...init });
        return json(init.method === 'GET' ? { options: { challenge: 'challenge' } } : { redirect: '/dashboard' });
    };
    return { ...app, calls, requests, sdk, request, adapter: createPasskeysAdapter(sdk, undefined, request) };
}

test('passkey transport preserves Laravel endpoints, credential payloads, CSRF and remember with same-origin credentials', async t => {
    const { adapter, requests, calls } = environment(t), owner = {};
    assert.equal(adapter.isSupported(), true);
    assert.deepEqual(await adapter.register(owner, { name: 'Laptop' }), { redirect: '/dashboard' });
    assert.equal(requests[0].url, 'https://menu.test/user/passkeys/options');
    assert.equal(requests[1].url, 'https://menu.test/user/passkeys');
    assert.deepEqual(JSON.parse(requests[1].body), { name: 'Laptop', credential: { id: 'attestation' } });
    assert.equal(requests[1].headers['X-CSRF-TOKEN'], 'csrf-meta');
    await adapter.verify(owner, { remember: () => true, routes: { options: '/custom/options', submit: '/custom/login' } });
    assert.equal(requests[2].url, 'https://menu.test/custom/options');
    assert.deepEqual(JSON.parse(requests[3].body), { credential: { id: 'assertion' }, remember: true });
    await adapter.verify(owner);
    assert.equal(requests[4].url, 'https://menu.test/passkeys/login/options');
    assert.equal(requests[5].url, 'https://menu.test/passkeys/login');
    assert.equal(JSON.parse(requests[5].body).remember, false);
    for (const request of requests) {
        assert.equal(request.credentials, 'same-origin');
        assert.equal(request.redirect, 'error');
        assert.equal(request.headers.Accept, 'application/json');
        assert.equal(request.signal.aborted, false);
    }
    assert.deepEqual(calls[0], ['register', { optionsJSON: { challenge: 'challenge' } }]);
    adapter.cancel(owner); assert.equal(calls.some(call => call[0] === 'cancel'), false);
});

test('cancelled options GET cannot start even the real installed WebAuthn SDK after its response arrives', { timeout: 5000 }, async t => {
    const app = environment(t), waiting = deferred(), requested = deferred();
    let requests = 0, prompted = 0, signal;
    app.navigator.credentials = { create() { prompted++; throw new Error('Unexpected prompt'); } };
    const previous = Object.getOwnPropertyDescriptor(globalThis, 'PublicKeyCredential');
    Object.defineProperty(globalThis, 'PublicKeyCredential', { configurable: true, value: function PublicKeyCredential() {} });
    t.after(() => previous ? Object.defineProperty(globalThis, 'PublicKeyCredential', previous) : delete globalThis.PublicKeyCredential);
    const adapter = createPasskeysAdapter(null, undefined, async (_url, init) => { requests++; signal = init.signal; requested.resolve(); return waiting.promise; });
    const owner = {}, operation = adapter.register(owner, { name: 'Device' });
    await requested.promise;
    assert.equal(requests, 1);
    adapter.cancel(owner);
    assert.equal(signal.aborted, true);
    waiting.resolve(json({ options: { challenge: 'YQ', user: { id: 'Yg' } } }));
    await assert.rejects(operation, error => adapter.isCancelled(error));
    assert.equal(prompted, 0); assert.equal(requests, 1);
});

test('destroying an owner during options JSON or credential resolution never sends a credential POST', async t => {
    for (const stage of ['json', 'credential']) {
        const app = environment(t), waiting = deferred(), owner = {}, requests = [];
        const sdk = { ...app.sdk, startRegistration: () => waiting.promise };
        const adapter = createPasskeysAdapter(sdk, undefined, async (_url, init) => {
            requests.push(init);
            return stage === 'json' ? { ok: true, json: () => waiting.promise } : json({ options: {} });
        });
        const operation = adapter.register(owner, { name: 'Device' }); await flush();
        adapter.cancel({}); assert.equal(requests[0].signal.aborted, false);
        adapter.cancel(owner); waiting.resolve(stage === 'json' ? { options: {} } : { id: 'credential' });
        await assert.rejects(operation, error => adapter.isCancelled(error));
        assert.equal(requests.length, 1);
        assert.equal(app.calls.filter(call => call[0] === 'cancel').length, stage === 'credential' ? 1 : 0);
    }
});

test('an aborted pending POST remains an unknown server outcome and never resolves as UI success', async t => {
    const app = environment(t), posted = deferred(), owner = {};
    let signal, submissions = 0;
    const adapter = createPasskeysAdapter(app.sdk, undefined, async (_url, init) => {
        if (init.method === 'GET') return json({ options: {} });
        submissions++; signal = init.signal; return posted.promise;
    });
    const operation = adapter.verify(owner, {}); await flush();
    assert.equal(submissions, 1); adapter.cancel(owner); assert.equal(signal.aborted, true);
    posted.resolve(json({ redirect: '/authenticated' }));
    await assert.rejects(operation, error => adapter.isCancelled(error));
    assert.equal(submissions, 1);
});

test('passkey transport fails closed for cross-origin routes, non-OK responses and unreadable JSON', async t => {
    const app = environment(t), owner = {};
    await assert.rejects(app.adapter.verify(owner, { routes: { options: 'https://other.test/options' } }));
    assert.equal(app.requests.length, 0);
    const failed = createPasskeysAdapter(app.sdk, undefined, async () => ({ ok: false, status: 419 }));
    await assert.rejects(failed.verify(owner, {}));
    const malformed = createPasskeysAdapter(app.sdk, undefined, async () => ({ ok: true, json: async () => { throw new Error('Invalid JSON'); } }));
    await assert.rejects(malformed.register(owner, { name: 'Device' }), /Invalid JSON/);
    assert.equal(app.calls.length, 0);
});

test('cookie CSRF fallback and browser cancellation preserve localized caller behavior without sensitive error output', async t => {
    const app = environment(t), owner = {};
    app.document.querySelector = () => null; app.document.cookie = 'other=ignored; XSRF-TOKEN=csrf%3Dcookie';
    await app.adapter.register(owner, { name: 'Device' });
    assert.equal(app.requests[1].headers['X-XSRF-TOKEN'], 'csrf=cookie');
    app.document.cookie = ''; await app.adapter.verify(owner, {});
    assert.equal(app.requests[3].headers['X-XSRF-TOKEN'], undefined);
    for (const name of ['NotAllowedError', 'AbortError']) {
        app.sdk.startAuthentication = async () => { throw Object.assign(new Error('Private browser details'), { name }); };
        await assert.rejects(app.adapter.verify(owner, {}), error => app.adapter.isCancelled(error));
    }
    app.sdk.startAuthentication = async () => { throw new Error('Browser failed'); };
    await assert.rejects(app.adapter.verify(owner, {}), error => !app.adapter.isCancelled(error));
    assert.equal(app.adapter.isCancelled(null), false);
});

test('a new owner cancels the old request and stale completion never cancels the newer credential prompt', async t => {
    const app = environment(t), first = deferred(), credential = deferred(), previous = {}, owner = {};
    let requestIndex = 0, firstSignal;
    app.sdk.startAuthentication = () => credential.promise;
    const adapter = createPasskeysAdapter(app.sdk, undefined, async (_url, init) => {
        if (++requestIndex === 1) { firstSignal = init.signal; return first.promise; }
        return json({ options: {} });
    });
    const old = adapter.verify(previous); await flush();
    const current = adapter.verify(owner); await flush();
    assert.equal(firstSignal.aborted, true);
    first.resolve(json({ options: {} })); await assert.rejects(old, error => adapter.isCancelled(error));
    adapter.cancel(previous); assert.equal(app.calls.length, 0);
    adapter.cancel(owner); assert.deepEqual(app.calls, [['cancel']]);
    credential.resolve({ id: 'credential' }); await assert.rejects(current, error => adapter.isCancelled(error));
    assert.equal(requestIndex, 2);
});

test('POST JSON arriving after cancellation cannot return its authenticated redirect', async t => {
    const app = environment(t), waiting = deferred(), owner = {};
    const adapter = createPasskeysAdapter(app.sdk, undefined, async (_url, init) => init.method === 'GET'
        ? json({ options: {} }) : { ok: true, json: () => waiting.promise });
    const pending = adapter.verify(owner); await flush(); adapter.cancel(owner);
    waiting.resolve({ redirect: '/authenticated' });
    await assert.rejects(pending, error => adapter.isCancelled(error));
});
