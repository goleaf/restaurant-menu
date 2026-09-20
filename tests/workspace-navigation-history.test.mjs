import test from 'node:test';
import assert from 'node:assert/strict';
import { workspaceNavigation } from '../resources/js/alpine/components/navigation-search.js';
import { browser, Element, event } from './alpine-support.mjs';

function historyWorkspace(t, native, configure = () => {}) {
    const app = browser(t), entries = [{ url: 'https://menu.test/restaurant?branch=1', state: { alpine: { snapshotIdx: 'first' }, preserved: true } }];
    let index = 0, shell;
    const traversals = [], swaps = [];
    app.window.location = { href: entries[0].url };
    app.window.history = {
        get state() { return entries[index].state; },
        get length() { return entries.length; },
        replaceState(state, unused, url = entries[index].url) { entries[index] = { state, url }; app.window.location.href = url; },
        pushState(state, unused, url) {
            entries.splice(index + 1);
            entries.push({ state, url });
            index++;
            app.window.location.href = url;
        },
        go(delta) { const target = index + delta; traversals.push(delta); queueMicrotask(() => traverse(target - index)); },
    };
    if (native) app.window.navigation = { get currentEntry() { return { index }; } };
    app.window.dispatchEvent = occurrence => {
        const listeners = [...(app.window.listeners.get(occurrence.type) ?? [])].sort((a, b) => Number(Boolean(b.options.capture)) - Number(Boolean(a.options.capture)));
        for (const listener of listeners) {
            if (listener.options.signal?.aborted) continue;
            listener.listener(occurrence);
            if (occurrence.stopped) break;
        }
    };
    function mount() { shell = app.component(workspaceNavigation); shell.init(); }
    function push(url) {
        shell.destroy();
        app.window.history.pushState({ alpine: { ...entries[index].state?.alpine, snapshotIdx: url } }, '', url);
        mount();
    }
    function traverse(delta) {
        index += delta;
        app.window.location.href = entries[index].url;
        const pop = event({ type: 'popstate', state: entries[index].state });
        app.window.dispatchEvent(pop);
        if (!pop.stopped && entries[index].state?.alpine?.snapshotIdx) {
            const navigation = event({ type: 'livewire:navigate', detail: { history: true, cached: true, url: new URL(entries[index].url) } });
            app.document.dispatchEvent(navigation);
            if (!navigation.prevented) swaps.push(entries[index].url);
        }
        return pop;
    }
    function hash(url, replace = false, deferred = false) {
        const oldURL = app.window.location.href;
        if (replace) entries[index] = { state: null, url };
        else {
            entries.splice(index + 1);
            entries.push({ state: null, url });
            index++;
        }
        app.window.location.href = url;
        const dispatch = () => {
            app.window.dispatchEvent(event({ type: 'popstate', state: null }));
            app.window.dispatchEvent(event({ type: 'hashchange', oldURL, newURL: url }));
        };
        if (!deferred) dispatch();
        return dispatch;
    }
    configure(app);
    mount();
    t.after(async () => { shell.destroy(); await Promise.resolve(); });
    return { ...app, entries, traversals, swaps, push, traverse, hash, get shell() { return shell; } };
}

for (const native of [true, false]) {
    test(`pending clean workspace restores multi-entry Back and Forward without replay (${native ? 'Navigation API' : 'history fallback'})`, async t => {
        const app = historyWorkspace(t, native);
        app.push('https://menu.test/restaurant?branch=2');
        app.push('https://menu.test/restaurant?branch=3');
        app.push('https://menu.test/restaurant?branch=4');
        const request = app.message({ el: new Element() }, [{ name: 'save' }]);
        request.send();
        app.traverse(-3);
        await Promise.resolve();
        assert.equal(app.window.location.href, 'https://menu.test/restaurant?branch=4');
        assert.deepEqual(app.traversals, [3]);
        assert.deepEqual(app.swaps, []);
        assert.equal(app.shell.blocked, true);
        request.finish();
        await Promise.resolve();
        assert.deepEqual(app.swaps, []);
        app.traverse(-2);
        assert.deepEqual(app.swaps, ['https://menu.test/restaurant?branch=2']);
        const next = app.message({ el: new Element() }, [{ name: 'save' }]);
        next.send();
        app.traverse(2);
        next.finish();
        await Promise.resolve();
        assert.equal(app.window.location.href, 'https://menu.test/restaurant?branch=2');
        assert.deepEqual(app.traversals, [3, -2]);
        assert.equal(app.swaps.length, 1);
        assert.equal(app.shell.pending, 0);
        app.traverse(2);
        assert.equal(app.swaps.at(-1), 'https://menu.test/restaurant?branch=4');
    });
}

test('pending history compensation prevents dirty guards from prompting but leaves clean traversal to them', async t => {
    const app = historyWorkspace(t, true);
    app.push('https://menu.test/restaurant?branch=2');
    let dirtyGuardCalls = 0;
    app.window.addEventListener('popstate', occurrence => { dirtyGuardCalls++; occurrence.stopImmediatePropagation(); }, { capture: true });
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    app.traverse(-1);
    await Promise.resolve();
    assert.equal(dirtyGuardCalls, 0);
    request.finish();
    app.traverse(-1);
    assert.equal(dirtyGuardCalls, 1);
    app.shell.destroy();
    assert.equal(app.state.interceptors.size, 0);
    assert.equal(app.document.listenerCount(), 0);
    assert.equal(app.window.listenerCount(), 1);
});

test('fallback counts a proven native hash entry before another page and restores a multi-entry jump', async t => {
    const app = historyWorkspace(t, false);
    app.hash('https://menu.test/restaurant?branch=1#main-content');
    assert.equal(app.window.history.state.alpine.snapshotIdx, 'first');
    app.push('https://menu.test/restaurant?branch=2');
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    app.traverse(-2);
    await Promise.resolve();
    assert.equal(app.window.location.href, 'https://menu.test/restaurant?branch=2');
    assert.deepEqual(app.traversals, [2]);
    request.finish();
});

test('fallback retains a hash entry when Livewire navigation starts before its queued hashchange', async t => {
    const app = historyWorkspace(t, false), departures = [];
    app.window.location.assign = url => departures.push(url);
    const hashAddress = 'https://menu.test/restaurant?branch=1#main-content';
    const dispatchHash = app.hash(hashAddress, false, true);
    const second = 'https://menu.test/restaurant?branch=2';
    app.document.dispatchEvent(event({ type: 'livewire:navigate', detail: { history: false, url: new URL(second) } }));
    assert.equal(app.window.history.state?.alpine?.snapshotIdx, 'first');
    app.push(second);
    dispatchHash();
    app.push('https://menu.test/restaurant?branch=3');
    app.traverse(-1);
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    for (const distance of [-2, 1]) {
        app.traverse(distance);
        await Promise.resolve();
        assert.equal(app.window.location.href, second);
        assert.equal(app.shell.pending, 1);
    }
    assert.deepEqual(departures, []);
    assert.deepEqual(app.traversals, [2, -1]);
    request.finish();
    app.traverse(-1);
    assert.equal(app.swaps.at(-1), hashAddress);
});

test('a request sent before hashchange preserves the source snapshot and fallback position', async t => {
    const app = historyWorkspace(t, false);
    const dispatchHash = app.hash('https://menu.test/restaurant?branch=1#main-content', false, true);
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    assert.equal(app.window.history.state?.alpine?.snapshotIdx, 'first');
    assert.equal(app.shell.historyPosition, 1);
    dispatchHash();
    assert.equal(app.shell.historyPosition, 1);
    request.finish();
});

test('ambiguous native hash replacement starts a new segment and never guesses a cross-segment delta', async t => {
    const app = historyWorkspace(t, false), departures = [];
    app.window.location.assign = address => departures.push(address);
    app.push('https://menu.test/restaurant?branch=2');
    app.hash('https://menu.test/restaurant?branch=2#main-content', true);
    app.push('https://menu.test/restaurant?branch=3');
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    app.traverse(-2);
    await Promise.resolve();
    assert.deepEqual(app.traversals, []);
    assert.deepEqual(departures, ['https://menu.test/restaurant?branch=1']);
    assert.deepEqual(app.swaps, []);
    request.finish();
    assert.equal(departures.length, 1);
});

test('a queued response callback cannot write history after its owning shell was destroyed', async t => {
    const app = historyWorkspace(t, false);
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    app.shell.destroy();
    const state = app.window.history.state;
    request.finish();
    await Promise.resolve();
    assert.equal(app.window.history.state, state);
    assert.equal(app.window.listenerCount(), 0);
});

test('fallback records real query pushes and replacements when branching from the middle of history', async t => {
    const app = historyWorkspace(t, false);
    app.push('https://menu.test/menu?section=main');
    app.push('https://menu.test/restaurant?branch=3');
    app.traverse(-1);
    app.window.history.replaceState({ ...app.window.history.state, query: 'retained' }, '', 'https://menu.test/menu?section=main&search=coffee');
    app.window.history.pushState({ alpine: { ...app.window.history.state.alpine, section: { value: 'photos' } } }, '', 'https://menu.test/menu?section=photos&search=coffee');
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    app.traverse(-2);
    await Promise.resolve();
    assert.equal(app.window.location.href, 'https://menu.test/menu?section=photos&search=coffee');
    assert.deepEqual(app.traversals, [2]);
    assert.equal(app.entries.length, 3);
    assert.equal(app.entries[1].state.query, 'retained');
    assert.deepEqual(app.entries[2].state.alpine.section, { value: 'photos' });
    request.finish();
    const address = app.window.location.href;
    app.window.history.pushState({ ...app.window.history.state }, '', address);
    const repeated = app.message({ el: new Element() }, [{ name: 'save' }]);
    repeated.send();
    app.traverse(-1);
    await Promise.resolve();
    assert.equal(app.window.location.href, address);
    assert.deepEqual(app.traversals, [2, 1]);
    repeated.finish();
});

test('native document departure prompts only while an operation is pending and cleanup releases owned resources', async t => {
    const app = historyWorkspace(t, false);
    const request = app.message({ el: new Element() }, [{ name: 'save' }]);
    request.send();
    const departure = event({ type: 'beforeunload' });
    app.window.dispatchEvent(departure);
    assert.equal(departure.prevented, true);
    assert.equal(departure.returnValue, '');
    request.finish();
    const retry = event({ type: 'beforeunload' });
    app.window.dispatchEvent(retry);
    assert.equal(retry.prevented, false);
    const push = app.window.history.pushState;
    app.shell.destroy();
    await Promise.resolve();
    assert.notEqual(app.window.history.pushState, push);
    assert.equal(app.window.listenerCount(), 0);
    assert.equal(app.document.listenerCount(), 0);
    assert.equal(app.state.interceptors.size, 0);
});

for (const native of [true, false]) {
    test(`rapid traversal while compensation is queued does not enqueue competing jumps (${native})`, async t => {
        const app = historyWorkspace(t, native);
        app.push('https://menu.test/restaurant?branch=2');
        app.push('https://menu.test/restaurant?branch=3');
        const request = app.message({ el: new Element() }, [{ name: 'save' }]);
        request.send();
        app.traverse(-1);
        app.traverse(-1);
        request.finish();
        await Promise.resolve();
        assert.deepEqual(app.traversals, [1]);
        assert.equal(app.window.location.href, 'https://menu.test/restaurant?branch=3');
        assert.equal(app.shell.returningToPosition, null);
        assert.deepEqual(app.swaps, []);
    });
}

for (const state of [null, {}]) {
    test(`unknown cross-resource entries without a Livewire snapshot use native departure (${JSON.stringify(state)})`, async t => {
        const app = historyWorkspace(t, false), departures = [];
        app.entries[0].state = state;
        app.window.location.assign = url => departures.push(url);
        app.push('https://menu.test/restaurant?branch=2');
        const request = app.message({ el: new Element() }, [{ name: 'save' }]);
        request.send();
        app.traverse(-1);
        await Promise.resolve();
        assert.deepEqual(departures, ['https://menu.test/restaurant?branch=1']);
        assert.deepEqual(app.swaps, []);
        assert.deepEqual(app.traversals, []);
        request.finish();
        assert.equal(departures.length, 1);
    });
}

test('history adapter preserves native receiver, arguments, return and errors without a second native write', async t => {
    const calls = [], failure = new Error('native rejection');
    const app = historyWorkspace(t, false, environment => {
        const history = environment.window.history, original = history.pushState;
        history.pushState = function (...args) {
            calls.push({ receiver: this, args });
            if (args[1] === 'reject') throw failure;
            if (this === history) original.apply(this, args);
            return 'native result';
        };
    });
    const input = { alpine: { section: { value: 'details' } }, retained: true };
    assert.equal(app.window.history.pushState(input, 'title', 'https://menu.test/restaurant?branch=2', 'extra'), 'native result');
    assert.equal(calls.length, 1);
    assert.equal(calls[0].receiver, app.window.history);
    assert.deepEqual(calls[0].args.slice(1), ['title', 'https://menu.test/restaurant?branch=2', 'extra']);
    assert.deepEqual(input, { alpine: { section: { value: 'details' } }, retained: true });
    assert.equal(app.window.history.state.retained, true);
    const before = app.window.history.state;
    assert.throws(() => app.window.history.pushState({}, 'reject', '/rejected'), error => error === failure);
    assert.equal(app.window.history.state, before);
    const receiver = {};
    assert.equal(app.window.history.pushState.call(receiver, input, 'title', '/foreign'), 'native result');
    assert.equal(calls.at(-1).receiver, receiver);
    assert.equal(calls.at(-1).args[0], input);
    app.window.history.pushState(['unsupported'], '', 'https://menu.test/array');
    assert.deepEqual(app.window.history.state, ['unsupported']);
    app.window.history.replaceState(null, '', 'https://menu.test/reset');
    assert.equal(app.window.history.state.alpine.workspaceNavigation.position, 0);
});

test('teardown preserves a later owner wrapper and makes its retained delegate inactive', async t => {
    const app = historyWorkspace(t, false), owned = app.window.history.pushState;
    const later = function (...args) { return owned.apply(this, args); };
    app.window.history.pushState = later;
    app.shell.destroy();
    await Promise.resolve();
    assert.equal(app.window.history.pushState, later);
    const input = { retained: true };
    later.call(app.window.history, input, '', 'https://menu.test/after-destroy');
    assert.equal(app.window.history.state, input);
    assert.equal(app.window.listenerCount(), 0);
});

function stateMessage(app, name, updates, actions = [{ name: '$set' }]) {
    const original = app.state.interceptors;
    app.state.interceptors = new Set([...original].map(intercept => context => {
        context.message.updates = updates;
        intercept(context);
    }));
    try {
        return app.message({ name, el: new Element() }, actions);
    } finally {
        app.state.interceptors = original;
    }
}

const dishComponent = 'organizations.brands.branches.menu.dish';

for (const updates of [{ section: 'main', contentLanguage: 'en', 'returnFilters.search': 'Garden', 'returnFilters.availability': '', 'returnFilters.menuId': '1', 'returnFilters.quality': '', 'returnFilters.page': 1 }, { section: 'main', 'editingItemForm.name': 'Unsaved draft' }, { contentLanguage: 'lt' }, { section: 'photos', editingItemForm: { name: 'Draft' } }]) {
    test(`audited dish URL state sync permits Forward while retaining the draft (${JSON.stringify(updates)})`, async t => {
        const app = historyWorkspace(t, true);
        app.push('https://menu.test/restaurant?branch=1&section=photos');
        app.traverse(-1);
        const sync = stateMessage(app, dishComponent, updates);
        sync.send();
        assert.equal(app.shell.pending, 1);
        assert.equal(app.shell.pendingDishSync, 1);
        app.traverse(1);
        assert.equal(app.swaps.at(-1), 'https://menu.test/restaurant?branch=1&section=photos');
        assert.deepEqual(app.traversals, []);
        sync.finish();
        await Promise.resolve();
        assert.equal(app.swaps.length, 2);
    });
}

for (const [name, updates, actions] of [
    ['settings.preferences', { section: 'main' }, [{ name: '$set' }]],
    ['settings.preferences', { theme: 'dark' }, [{ name: '$set' }]],
    [dishComponent, { section: 'main', unknown: 'write' }, [{ name: '$set' }]],
    [dishComponent, { section: 'main', 'itemImageUploads.1': [] }, [{ name: '$set' }]],
    [dishComponent, { 'editingItemForm.name': 'Draft' }, [{ name: '$set' }]],
    [dishComponent, null, [{ name: '$set' }]],
    [dishComponent, { section: 'main' }, [{ name: '$set' }, { name: 'saveItem' }]],
]) {
    test(`unclassified or mixed property updates remain protected (${name} ${JSON.stringify(updates)} ${JSON.stringify(actions)})`, async t => {
        const app = historyWorkspace(t, true);
        app.push('https://menu.test/restaurant?branch=2');
        const request = stateMessage(app, name, updates, actions);
        request.send();
        assert.equal(app.shell.pending, 1);
        app.traverse(-1);
        await Promise.resolve();
        assert.equal(app.window.location.href, 'https://menu.test/restaurant?branch=2');
        assert.deepEqual(app.swaps, []);
        request.finish();
        assert.equal(app.shell.pending, 0);
    });
}

test('a read-only dish URL sync never releases a concurrent mutation', async t => {
    const app = historyWorkspace(t, true);
    app.push('https://menu.test/restaurant?branch=2');
    const mutation = stateMessage(app, dishComponent, {}, [{ name: 'saveItem' }]);
    mutation.send();
    const sync = stateMessage(app, dishComponent, { section: 'photos' });
    sync.send();
    assert.equal(app.shell.pending, 2);
    assert.equal(app.shell.pendingDishSync, 1);
    sync.sync();
    sync.finish();
    app.traverse(-1);
    await Promise.resolve();
    assert.equal(app.window.location.href, 'https://menu.test/restaurant?branch=2');
    assert.deepEqual(app.swaps, []);
    assert.equal(app.shell.pending, 1);
    mutation.finish();
});


test('audited dish synchronization still blocks explicit page navigation and native departure without replay', async t => {
    const app = historyWorkspace(t, true);
    const sync = stateMessage(app, dishComponent, { contentLanguage: 'lt' });
    sync.send();
    const navigate = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/dashboard'), history: false } });
    app.document.dispatchEvent(navigate);
    assert.equal(navigate.prevented, true);
    assert.equal(navigate.stopped, true);
    assert.equal(app.shell.blocked, true);
    const unload = event({ type: 'beforeunload' });
    app.window.dispatchEvent(unload);
    assert.equal(unload.prevented, true);
    sync.sync();
    sync.finish();
    await Promise.resolve();
    assert.equal(app.shell.pending, 0);
    assert.equal(app.shell.pendingDishSync, 0);
    assert.equal(app.shell.blocked, false);
    assert.deepEqual(app.swaps, []);
    assert.equal(app.state.navigation, undefined);
});

for (const address of [
    'https://menu.test/restaurant?branch=2&section=photos',
    'https://menu.test/another-dish?branch=1&section=photos',
    'https://menu.test/restaurant?branch=1&section=photos#changed-fragment',
    'https://other.test/restaurant?branch=1&section=photos',
]) {
    test(`pending dish state permits no history departure outside its section and language (${address})`, async t => {
        const app = historyWorkspace(t, true);
        app.push(address);
        const sync = stateMessage(app, dishComponent, { section: 'photos' });
        sync.send();
        app.traverse(-1);
        await Promise.resolve();
        assert.equal(app.window.location.href, address);
        assert.deepEqual(app.swaps, []);
        assert.deepEqual(app.traversals, [1]);
        const navigate = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/restaurant?branch=1'), history: true } });
        app.document.dispatchEvent(navigate);
        assert.equal(navigate.prevented, true);
        sync.finish();
        await Promise.resolve();
        assert.deepEqual(app.swaps, []);
    });
}

test('multiple audited URL requests allow only local history and release their counters once', async t => {
    const app = historyWorkspace(t, false);
    app.push('https://menu.test/restaurant?branch=1&section=photos&language=lt');
    const section = stateMessage(app, dishComponent, { section: 'photos' });
    const language = stateMessage(app, dishComponent, { contentLanguage: 'lt' });
    section.send();
    language.send();
    assert.equal(app.shell.pending, 2);
    assert.equal(app.shell.pendingDishSync, 2);
    app.traverse(-1);
    assert.equal(app.swaps.at(-1), 'https://menu.test/restaurant?branch=1');
    section.sync();
    section.finish();
    assert.equal(app.shell.pending, 1);
    assert.equal(app.shell.pendingDishSync, 1);
    app.traverse(1);
    assert.equal(app.swaps.length, 2);
    language.sync();
    language.finish();
    await Promise.resolve();
    assert.equal(app.shell.pending, 0);
    assert.equal(app.shell.pendingDishSync, 0);
    assert.deepEqual(app.traversals, []);
});
