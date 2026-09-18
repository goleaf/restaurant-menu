import assert from 'node:assert/strict';
import test from 'node:test';
import { menuWorkspace } from '../resources/js/alpine/components/menu-workspace.js';
import { staffWorkspace, invitationClipboard, staffEditor } from '../resources/js/alpine/components/staff-workspace.js';
import { browser, Element, Events, event } from './alpine-support.mjs';

function workspace(t, factory, address = 'https://menu.test/menu?section=catalog') {
    const app = browser(t), history = [], dialogs = [];
    app.window.location = { href: address, pathname: new URL(address).pathname };
    app.window.history = { state: { existing: true }, replaceState(value) { this.state = value; history.push(['stamp', value]); }, go(delta) { history.push(['go', delta]); } };
    const instance = app.component(factory);
    instance.$el.setAttribute('wire:id', 'workspace');
    instance.$flux = { modal(name) { return { show() { dialogs.push(['show', name]); }, close() { dialogs.push(['close', name]); } }; } };
    instance.$wire.section = 'catalog';
    instance.init();
    return { ...app, instance, history, dialogs };
}

function dirtyMenu(app) {
    const content = new Element(), form = new Element(), input = new Element(), child = new Element();
    child.setAttribute('wire:id', 'editor'); form.setAttribute('wire:submit', 'saveItem(42)');
    form.ancestors.set('[wire\\:id]', child); input.ancestors.set('form[wire\\:submit]', form);
    content.children.set('forms', [form]); app.instance.$el.children.set('[data-menu-workspace-content]', [content]);
    app.instance.$el.children.set('editor', [child]);
    app.instance.$el.dispatchEvent({ type: 'input', target: input });
    return { form, input, child };
}

test('menu keeps edits made during a save, preserves validation errors, and clears only a successful matching revision', t => {
    const app = workspace(t, menuWorkspace), menu = app.instance;
    const { form, input, child } = dirtyMenu(app);
    assert.equal(menu.hasUnsavedChanges(), true);
    app.message({ id: 'other', el: new Element() }, [{ name: 'saveItem' }]).finish();
    assert.equal(menu.hasUnsavedChanges(), true);
    app.message({ id: 'editor', el: child }, [{ name: 'saveItem' }], { snapshot: JSON.stringify({ memo: { errors: { name: ['Required'] } } }) }).finish();
    assert.equal(menu.hasUnsavedChanges(), true);
    const pending = app.message({ id: 'editor', el: child }, [{ name: 'saveItem' }], { snapshot: { memo: { errors: {} } } });
    menu.$el.dispatchEvent({ type: 'input', target: input }); pending.finish(); assert.equal(menu.hasUnsavedChanges(), true);
    app.message({ id: 'editor', el: child }, [{ name: 'saveItem' }], { snapshot: { memo: { errors: {} } } }).finish();
    assert.equal(menu.hasUnsavedChanges(), false);
    menu.dirtyForms.set(form, 1); form.isConnected = false; assert.equal(menu.hasUnsavedChanges(), false);
    menu.destroy(); assert.equal(app.state.interceptors.size, 0); assert.equal(app.window.listenerCount() + app.document.listenerCount() + menu.$el.listenerCount(), 0);
});

test('a confirmed dish save clears its submitted revision before redirect effects without clearing later input', t => {
    const app = workspace(t, menuWorkspace), menu = app.instance;
    const { input, child } = dirtyMenu(app);
    const response = app.message({ id: 'editor', el: child }, [{ name: 'saveItem' }], { snapshot: { memo: { errors: {} } } });
    response.sync();
    const redirect = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/menu/items/42') } });
    app.document.dispatchEvent(redirect);
    assert.equal(redirect.prevented, false);
    assert.deepEqual(app.dialogs, []);
    menu.$el.dispatchEvent({ type: 'input', target: input });
    response.finish();
    assert.equal(menu.hasUnsavedChanges(), true);
    const later = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/menu/items/43') } });
    app.document.dispatchEvent(later);
    assert.equal(later.prevented, true);
    menu.destroy();
});

test('response synchronization preserves failed, unrelated and concurrently changed dish drafts', t => {
    for (const scenario of ['validation', 'unrelated', 'new-input', 'photos']) {
        const app = workspace(t, menuWorkspace), menu = app.instance;
        const { input, child } = dirtyMenu(app);
        const errors = scenario === 'validation' ? { name: ['Required'] } : {};
        const response = app.message({ id: 'editor', el: child }, [{ name: scenario === 'unrelated' ? 'refreshPreview' : 'saveItem' }], { snapshot: { memo: { errors } } });
        if (scenario === 'new-input') menu.$el.dispatchEvent({ type: 'input', target: input });
        if (scenario === 'photos') menu.dirtySections.add('photos');
        response.sync();
        const redirect = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/menu/items/42') } });
        app.document.dispatchEvent(redirect);
        assert.equal(redirect.prevented, true, scenario);
        menu.destroy();
    }
});

test('menu dirty navigation cancels safely, proceeds once on discard and restores busy state after server failure', async t => {
    const app = workspace(t, menuWorkspace), menu = app.instance, target = new Element();
    menu.$el.children.set('[data-menu-section]', [target]);
    const calls = []; menu.$wire.selectSection = async section => { calls.push(section); };
    const modified = event({ ctrlKey: true }); await menu.navigateSection(modified, 'extras'); assert.equal(modified.prevented, false);
    app.navigator.onLine = false; await menu.navigateSection(event(), 'extras'); assert.deepEqual(calls, []); app.navigator.onLine = true;
    menu.dirtySections.add('images-42');
    await menu.navigateSection(event(), 'extras'); assert.deepEqual(calls, []); menu.cancelNavigation(); assert.equal(menu.hasUnsavedChanges(), true);
    await menu.navigateSection(event(), 'extras'); await menu.discardAndNavigate(); assert.deepEqual(calls, ['extras']); assert.equal(menu.hasUnsavedChanges(), false);
    menu.$wire.selectSection = async () => { throw new Error('offline'); };
    await menu.navigateSection(event(), 'extras'); assert.equal(menu.navigating, false); assert.equal(target.hasAttribute('aria-disabled'), false);
    menu.destroy();
});

test('dish section navigation retains drafts while departure still requires an explicit discard', async t => {
    const app = workspace(t, () => menuWorkspace({ retainSectionDrafts: true })), menu = app.instance;
    const { form } = dirtyMenu(app);
    menu.dirtySections.add('images-42');
    const calls = [];
    menu.$wire.selectSection = async section => { calls.push(section); menu.$wire.section = section; };
    await menu.navigateSection(event(), 'photos');
    assert.deepEqual(calls, ['photos']);
    assert.equal(menu.dirtyForms.has(form), true);
    assert.equal(menu.dirtySections.has('images-42'), true);
    assert.deepEqual(app.dialogs, []);
    let departures = 0;
    menu.requestNavigation(() => departures++);
    assert.equal(departures, 0);
    menu.cancelNavigation();
    assert.equal(menu.hasUnsavedChanges(), true);
    menu.requestNavigation(() => departures++);
    menu.discardAndNavigate();
    assert.equal(departures, 1);
    menu.destroy();
});

test('dish query history cancels only redundant cached page swaps without discarding section drafts', t => {
    const app = workspace(t, () => menuWorkspace({ retainSectionDrafts: true }), 'https://menu.test/menu/items/42?q=&page=1&section=photos&language=lt'), menu = app.instance;
    const { form } = dirtyMenu(app);
    const cachedHistory = event({ type: 'livewire:navigate', detail: { history: true, cached: true, url: new URL('https://menu.test/menu/items/42?q=&page=1&section=main') } });
    app.document.dispatchEvent(cachedHistory);
    assert.equal(cachedHistory.prevented, true);
    assert.deepEqual(app.dialogs, []);
    assert.equal(menu.pendingNavigation, null);
    assert.equal(menu.dirtyForms.has(form), true);
    menu.dirtyForms.clear();
    const cleanHistory = event({ type: 'livewire:navigate', detail: { history: true, url: cachedHistory.detail.url } });
    app.document.dispatchEvent(cleanHistory);
    assert.equal(cleanHistory.prevented, true);
    dirtyMenu(app);
    const otherCard = event({ type: 'livewire:navigate', detail: { history: true, url: new URL('https://menu.test/menu/items/99?q=&page=1&section=main') } });
    app.document.dispatchEvent(otherCard);
    assert.equal(otherCard.prevented, true);
    assert.equal(app.dialogs.length, 1);
    menu.cancelNavigation();
    const ordinaryVisit = event({ type: 'livewire:navigate', detail: { history: false, url: cachedHistory.detail.url } });
    app.document.dispatchEvent(ordinaryVisit);
    assert.equal(ordinaryVisit.prevented, true);
    assert.equal(app.dialogs.at(-1)[0], 'show');
    assert.equal(menu.hasUnsavedChanges(), true);
    menu.destroy();
});

test('clean and explicitly discarded cross card history still swaps the actual page after popstate', t => {
    for (const dirty of [false, true]) {
        const ownerAddress = 'https://menu.test/menu/items/42?section=main';
        const targetAddress = 'https://menu.test/menu/items/99?section=main';
        const app = workspace(t, () => menuWorkspace({ retainSectionDrafts: true }), ownerAddress), menu = app.instance;
        app.window.navigation = { currentEntry: { index: 5 } };
        menu.stampHistory();
        if (dirty) dirtyMenu(app);
        app.window.location.href = targetAddress;
        app.window.navigation.currentEntry.index = 4;
        const backwards = event();
        menu.guardHistory(backwards);
        if (dirty) {
            assert.equal(backwards.stopped, true);
            assert.deepEqual(app.history.at(-1), ['go', 1]);
            app.window.location.href = ownerAddress;
            app.window.navigation.currentEntry.index = 5;
            menu.guardHistory(event());
            menu.discardAndNavigate();
            assert.equal(menu.hasUnsavedChanges(), false);
            assert.deepEqual(app.history.at(-1), ['go', -1]);
            app.window.location.href = targetAddress;
            app.window.navigation.currentEntry.index = 4;
            menu.guardHistory(event());
        } else {
            assert.equal(backwards.stopped, false);
            assert.deepEqual(app.dialogs, []);
        }
        const pageSwap = event({ type: 'livewire:navigate', detail: { history: true, cached: true, url: new URL(targetAddress) } });
        app.document.dispatchEvent(pageSwap);
        assert.equal(pageSwap.prevented, false);
        assert.equal(menu.pendingNavigation, null);
        menu.destroy();
    }
});

test('read only dish preview selections do not become persistent drafts', t => {
    const app = workspace(t, menuWorkspace), menu = app.instance;
    const { form, input } = dirtyMenu(app);
    menu.dirtyForms.clear();
    input.ancestors.set('[data-menu-preview]', new Element());
    menu.$el.dispatchEvent({ type: 'input', target: input });
    assert.equal(menu.dirtyForms.has(form), false);
    menu.destroy();
});

test('bounded dish option search does not dirty the editor but its eventual selection does', t => {
    const app = workspace(t, menuWorkspace), menu = app.instance;
    const { form, input } = dirtyMenu(app);
    menu.dirtyForms.clear();
    input.ancestors.set('[data-menu-search]', new Element());
    menu.$el.dispatchEvent({ type: 'input', target: input });
    assert.equal(menu.dirtyForms.has(form), false);
    input.ancestors.delete('[data-menu-search]');
    menu.$el.dispatchEvent({ type: 'change', target: input });
    assert.equal(menu.dirtyForms.has(form), true);
    menu.destroy();
});

test('discarding dish main fields leaves child and image drafts protected', t => {
    const app = workspace(t, () => menuWorkspace({ cleanFormActions: { discardMainChanges: 'saveItem' } })), menu = app.instance;
    const { form, child } = dirtyMenu(app);
    const variant = new Element();
    variant.setAttribute('wire:submit', 'saveVariant');
    variant.ancestors.set('[wire\\:id]', child);
    menu.dirtyForms.set(variant, 2);
    menu.dirtySections.add('images-42');
    app.message({ id: 'editor', el: child }, [{ name: 'discardMainChanges' }], { snapshot: { memo: { errors: { denied: ['Denied'] } } } }).finish();
    assert.equal(menu.dirtyForms.has(form), true);
    app.message({ id: 'other', el: child }, [{ name: 'discardMainChanges' }]).finish();
    assert.equal(menu.dirtyForms.has(form), true);
    app.message({ id: 'editor', el: child }, [{ name: 'discardMainChanges' }]).finish();
    assert.equal(menu.dirtyForms.has(form), false);
    assert.equal(menu.dirtyForms.has(variant), true);
    assert.equal(menu.dirtySections.has('images-42'), true);
    menu.destroy();
});

test('offline main cancellation restores its baseline without sending a request or clearing photo drafts', t => {
    const app = workspace(t, menuWorkspace), menu = app.instance;
    const { form } = dirtyMenu(app), button = new Element(), error = new Element(), field = new Element();
    button.ancestors.set('form', form);
    form.children.set('[role="alert"]', [error]);
    form.children.set('[aria-invalid="true"]', [field]);
    form.children.set('[data-invalid="true"]', [field]);
    const baseline = { itemName: 'Saved', itemTranslations: { en: { name: 'Saved' } } }, updates = [];
    menu.$wire.$get = () => new Proxy(baseline, {});
    menu.$wire.$set = (...args) => updates.push(args);
    menu.dirtySections.add('images-42');
    let resets = 0;
    form.addEventListener('menu-form-discarded', () => resets++);
    const click = event({ currentTarget: button });
    menu.discardFormLocally(click, 'editingItemForm', 'mainBaseline');
    assert.equal(click.prevented && click.stopped, true);
    assert.deepEqual(updates, [['editingItemForm', baseline, false]]);
    assert.notEqual(updates[0][1], baseline);
    assert.equal(menu.dirtyForms.has(form), false);
    assert.equal(menu.dirtySections.has('images-42'), true);
    assert.equal(error.hidden, true);
    assert.equal(field.getAttribute('aria-invalid'), 'false');
    assert.equal(field.getAttribute('data-invalid'), 'false');
    assert.equal(resets, 1);
    menu.destroy();
});

test('dish query history preserves drafts only within the same card and supported section state', t => {
    const app = workspace(t, () => menuWorkspace({ retainSectionDrafts: true })), menu = app.instance;
    dirtyMenu(app);
    app.window.location.href = 'https://menu.test/menu?section=photos&language=lt';
    const same = event({ state: { menuWorkspace: { id: menu.historyId, index: 1 } } });
    menu.guardHistory(same);
    assert.equal(same.stopped, false);
    assert.deepEqual(app.dialogs, []);
    assert.equal(menu.hasUnsavedChanges(), true);
    app.window.location.href = 'https://menu.test/menu?section=main&item=99';
    menu.guardHistory(event({ state: { menuWorkspace: { id: menu.historyId, index: 2 } } }));
    assert.equal(app.dialogs.at(-1)[0], 'show');
    app.window.location.href = 'https://menu.test/menu?section=photos&language=lt';
    const restored = event({ state: { menuWorkspace: { id: menu.historyId, index: 1 } } });
    menu.guardHistory(restored);
    assert.equal(restored.stopped, true);
    assert.equal(menu.returningToIndex, null);
    menu.cancelNavigation();
    menu.destroy();
});

test('dish invalid main fields are focused after reveal and pending focus is cancelled on departure', t => {
    const frames = new Map(); let sequence = 0;
    const previousRequest = globalThis.requestAnimationFrame, previousCancel = globalThis.cancelAnimationFrame;
    globalThis.requestAnimationFrame = callback => { frames.set(++sequence, callback); return sequence; };
    globalThis.cancelAnimationFrame = id => frames.delete(id);
    t.after(() => {
        if (previousRequest) globalThis.requestAnimationFrame = previousRequest; else delete globalThis.requestAnimationFrame;
        if (previousCancel) globalThis.cancelAnimationFrame = previousCancel; else delete globalThis.cancelAnimationFrame;
    });
    const app = workspace(t, () => menuWorkspace({ invalidEvent: 'dish-main-invalid', invalidSelector: '[data-dish-invalid]' }));
    const error = new Element();
    app.instance.$el.children.set('[data-dish-invalid]', [error]);
    app.instance.$el = new Element();
    app.window.dispatchEvent({ type: 'dish-main-invalid' });
    assert.equal(error.focused, 0);
    assert.equal(frames.size, 1);
    frames.get(sequence)();
    assert.equal(error.focused, 1);
    app.window.dispatchEvent({ type: 'dish-main-invalid' });
    const pending = frames.get(sequence);
    app.instance.destroy();
    pending();
    assert.equal(error.focused, 1);
});

test('menu child navigation updates all owner links and focuses owner targets or errors', async t => {
    const app = workspace(t, menuWorkspace), menu = app.instance, root = menu.$el;
    const link = new Element(), target = new Element(), error = new Element();
    root.children.set('[data-menu-section]', [link, target]);
    root.children.set('[data-menu-section="extras"]', [target]);
    root.children.set('[data-menu-workspace-content] [role="alert"]', [error]);
    menu.$el = link;
    let resolve;
    menu.$wire.selectSection = () => new Promise(done => { resolve = done; });
    const pending = menu.navigateSection(event(), 'extras');
    assert.equal(link.getAttribute('aria-disabled'), 'true');
    assert.equal(target.getAttribute('aria-disabled'), 'true');
    resolve(); await pending;
    assert.equal(target.focused, 1);
    assert.equal(link.hasAttribute('aria-disabled'), false);
    assert.equal(target.hasAttribute('aria-disabled'), false);
    menu.$wire.selectSection = async () => { throw new Error('Lost'); };
    await menu.navigateSection(event(), 'extras');
    assert.equal(error.focused, 1);
    menu.destroy();
});

test('staff child navigation and dismissal retain the owner error, editor and heading', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance, root = staff.$el;
    const error = new Element(), heading = new Element(), editor = new Element();
    root.children.set('[role=alert]', [error]);
    root.children.set('[data-staff-heading]', [heading]);
    root.children.set('[data-staff-editor]', [editor]);
    root.ancestors.set('[data-staff-workspace]', root);
    let closed = 0;
    editor.close = () => { closed++; };
    staff.$el = new Element();
    staff.$wire.selectSection = async () => { throw new Error('Lost'); };
    await staff.navigateSection(event(), 'invitations');
    assert.equal(error.focused, 1);
    staff.online = false;
    await staff.dismissEditor();
    assert.equal(closed, 1);
    assert.equal(heading.focused, 1);
    staff.destroy();
});

test('staff opens a plain card section without assuming a modal presenter', t => {
    const app = workspace(t, staffWorkspace), staff = app.instance, editor = new Element(), input = new Element();
    editor.hidden = true;
    editor.setAttribute('inert', '');
    editor.children.set('input:not([type=hidden]), select', [input]);
    staff.$el.children.set('[data-staff-editor]', [editor]);
    app.window.Alpine = { $data: () => ({}) };
    staff.dirty = true;
    app.window.dispatchEvent({ type: 'staff-editor-opened' });
    assert.equal(editor.hidden, false);
    assert.equal(editor.hasAttribute('inert'), false);
    assert.equal(input.focused, 1);
    assert.equal(staff.dirty, false);
    staff.destroy();
});

test('offline card dismissal hides only the editor and reconnect waits for an explicit reopen', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance, editor = new Element(), overview = new Element(), trigger = new Element();
    const root = staff.$el;
    root.ancestors.set('[data-staff-workspace]', root);
    root.children.set('[data-staff-editor]', [editor]);
    root.children.set('[data-staff-heading]', [overview]);
    trigger.setAttribute('wire:click', 'openPermissions');
    trigger.ancestors.set('[wire\\:click]', trigger);
    root.children.set('[wire\\:click]', [trigger]);
    let discarded = 0;
    staff.$wire.discardEditor = async () => { discarded++; };
    staff.dirty = true;
    app.window.dispatchEvent({ type: 'offline' });
    await staff.dismissEditor();
    assert.equal(editor.hidden, true);
    assert.equal(editor.hasAttribute('inert'), true);
    assert.equal(overview.hidden, false);
    assert.equal(overview.focused, 1);
    assert.equal(staff.dirty, false);
    assert.equal(staff.editorDismissed, true);
    assert.equal(discarded, 0);
    app.window.dispatchEvent({ type: 'online' });
    await new Promise(setImmediate);
    assert.equal(discarded, 0);
    root.dispatchEvent(event({ type: 'click', target: trigger }));
    await new Promise(setImmediate);
    assert.equal(discarded, 1);
    assert.equal(trigger.clicks, 1);
    staff.destroy();
});

test('staff waits for in-flight requests before dismissal or navigation without queuing a replay', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance;
    const editor = new Element();
    staff.$el.children.set('[data-staff-editor]', [editor]);
    let navigated = 0, discarded = 0;
    staff.$wire.selectSection = async () => { navigated++; };
    staff.$wire.discardEditor = async () => { discarded++; };
    const saving = app.message({ id: 'workspace', el: staff.$el }, [{ name: 'saveMember' }]);
    saving.send();
    await staff.navigateSection(event(), 'access');
    await staff.dismissEditor();
    const leaving = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/elsewhere') } });
    app.document.dispatchEvent(leaving);
    assert.equal(leaving.prevented, true);
    assert.equal(navigated, 0);
    assert.equal(discarded, 0);
    assert.equal(editor.hidden, false);
    assert.equal(staff.pendingNavigation, null);
    const unloading = event({ type: 'beforeunload' });
    app.window.dispatchEvent(unloading);
    assert.equal(unloading.prevented, true);
    saving.finish();
    await new Promise(setImmediate);
    assert.equal(navigated, 0);
    await staff.navigateSection(event(), 'access');
    assert.equal(navigated, 1);
    staff.destroy();
});

test('assignment removal keeps its focus target and explicitly clears an offline dismissed editor before reopening', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance, trigger = new Element();
    trigger.setAttribute('wire:click', 'openAssignmentRemoval');
    trigger.ancestors.set('[wire\\:click]', trigger);
    staff.$el.children.set('[wire\\:click]', [trigger]);
    staff.$el.dispatchEvent(event({ type: 'click', target: trigger }));
    assert.equal(staff.trigger, trigger);
    let discarded = 0;
    staff.$wire.discardEditor = async () => { discarded++; };
    app.window.dispatchEvent({ type: 'offline' });
    await staff.dismissEditor();
    assert.equal(trigger.focused, 1);
    app.window.dispatchEvent({ type: 'online' });
    assert.equal(discarded, 0);
    staff.$el.dispatchEvent(event({ type: 'click', target: trigger }));
    await new Promise(setImmediate);
    assert.equal(discarded, 1);
    assert.equal(trigger.clicks, 1);
    staff.destroy();
});

test('an explicit section change clears an offline dismissed draft and retains it if discard fails', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance, calls = [];
    staff.$wire.discardEditor = async () => { calls.push('discard'); };
    staff.$wire.selectSection = async section => { calls.push(section); };
    app.window.dispatchEvent({ type: 'offline' });
    await staff.dismissEditor();
    app.window.dispatchEvent({ type: 'online' });
    assert.deepEqual(calls, []);
    await staff.navigateSection(event(), 'overview');
    assert.deepEqual(calls, ['discard', 'overview']);
    assert.equal(staff.editorDismissed, false);

    staff.editorDismissed = true;
    staff.$wire.discardEditor = async () => { throw new Error('Disconnected again'); };
    await staff.navigateSection(event(), 'areas');
    assert.deepEqual(calls, ['discard', 'overview']);
    assert.equal(staff.editorDismissed, true);
    assert.equal(staff.navigating, false);

    let finishDiscard;
    staff.$wire.discardEditor = () => new Promise(resolve => { finishDiscard = resolve; });
    const navigation = staff.navigateSection(event(), 'areas');
    staff.destroy();
    finishDiscard();
    await navigation;
    assert.deepEqual(calls, ['discard', 'overview']);
});

test('staff restores Back during a pending save without opening a discard prompt', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance;
    app.window.navigation = { currentEntry: { index: 5 } };
    staff.stampHistory();
    const saving = app.message({ id: 'workspace', el: staff.$el }, [{ name: 'saveMember' }]);
    saving.send();
    app.window.navigation.currentEntry.index = 4;
    staff.guardHistory(event());
    assert.deepEqual(app.history.at(-1), ['go', 1]);
    assert.deepEqual(app.dialogs, []);
    app.window.navigation.currentEntry.index = 5;
    staff.guardHistory(event());
    saving.finish();
    await new Promise(setImmediate);
    assert.equal(staff.pendingNavigation, null);
    staff.destroy();
});

test('menu and staff restore native history before prompting and let cancelled back traversal repeat', t => {
    for (const factory of [menuWorkspace, staffWorkspace]) {
        const app = workspace(t, factory), instance = app.instance;
        app.window.navigation = { currentEntry: { index: 5 } }; instance.stampHistory();
        if (factory === menuWorkspace) instance.dirtySections.add('draft'); else instance.dirty = true;
        app.window.navigation.currentEntry.index = 4;
        const backwards = event({ state: null }); instance.guardHistory(backwards);
        assert.equal(backwards.stopped, true); assert.deepEqual(app.history.at(-1), ['go', 1]);
        app.window.navigation.currentEntry.index = 5; instance.guardHistory(event()); instance.cancelNavigation();
        app.window.navigation.currentEntry.index = 4; instance.guardHistory(event());
        assert.deepEqual(app.history.at(-1), ['go', 1]);
        instance.destroy();
    }
});

test('staff section failure is handled and failed discard preserves its pending navigation and dirty draft', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance;
    staff.$wire.selectSection = async () => { throw new Error('Lost'); };
    await staff.navigateSection(event(), 'invitations'); assert.equal(staff.navigating, false);
    staff.dirty = true;
    let navigated = 0; staff.requestNavigation(() => { navigated++; });
    staff.$wire.discardEditor = async () => { throw new Error('Lost'); };
    await staff.discardAndNavigate(); assert.equal(staff.dirty, true); assert.equal(navigated, 0); assert.equal(typeof staff.pendingNavigation, 'function');
    staff.$wire.discardEditor = async () => {};
    await staff.discardAndNavigate(); assert.equal(staff.dirty, false); assert.equal(navigated, 1);
    staff.destroy(); assert.equal(app.state.interceptors.size, 0);
});

test('destroyed workspace cannot stamp history or focus from a queued render callback', async t => {
    for (const factory of [menuWorkspace, staffWorkspace]) {
        const app = workspace(t, factory), instance = app.instance;
        const message = app.message({ id: 'workspace', el: instance.$el });
        const stamps = app.history.length;
        instance.destroy(); message.finish(); await new Promise(setImmediate);
        assert.equal(app.history.length, stamps);
        assert.equal(app.state.interceptors.size, 0);
    }
});

test('invitation copy ignores late replies after destruction and isolates denied clipboard fallback', async t => {
    const app = browser(t), clipboard = app.component(invitationClipboard), link = new Element();
    clipboard.$refs.link = link; link.value = 'invite';
    let resolve; app.navigator.clipboard = { writeText: () => new Promise(done => { resolve = done; }) };
    const copying = clipboard.copy(); clipboard.destroy(); resolve(); await copying;
    assert.equal(clipboard.copied, false); assert.equal(clipboard.busy, false);
    const next = app.component(invitationClipboard); next.$refs.link = link;
    app.navigator.clipboard.writeText = async () => { throw new Error('Denied'); };
    await next.copy(); assert.equal(next.failed, true); assert.equal(link.focused, 1); assert.equal(link.selected, 1);
    next.destroy();
});

test('menu dirty section events and unload/navigation guards preserve draft until explicit discard', async t => {
    const app = workspace(t, menuWorkspace), menu = app.instance;
    menu.$el.dispatchEvent({ type: 'menu-workspace-dirty', detail: { key: 123, dirty: true } });
    menu.$el.dispatchEvent({ type: 'menu-workspace-dirty', detail: { key: 'photos', dirty: true } });
    const unloading = event({ type: 'beforeunload' }); app.window.dispatchEvent(unloading); assert.equal(unloading.prevented, true);
    const navigate = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/other') } });
    app.document.dispatchEvent(navigate); assert.equal(navigate.prevented, true); assert.equal(app.state.navigation, undefined);
    menu.discardAndNavigate(); assert.equal(app.state.navigation, 'https://menu.test/other');
    menu.$el.dispatchEvent({ type: 'menu-workspace-dirty', detail: { key: 'photos', dirty: false } });
    app.document.dispatchEvent(navigate);
    const clean = event({ type: 'beforeunload' }); app.window.dispatchEvent(clean); assert.equal(clean.prevented, false);
    menu.destroy();
});

test('workspace history fallback protects repeated Back without a Navigation API and ignores foreign history', t => {
    for (const factory of [menuWorkspace, staffWorkspace]) {
        const app = workspace(t, factory), instance = app.instance;
        const key = factory === menuWorkspace ? 'menuWorkspace' : 'staffWorkspace';
        instance.guardHistory(event({ state: { [key]: { id: 'foreign', index: 0 } } }));
        app.window.location.href = 'https://menu.test/menu?section=extras'; instance.stampHistory(); assert.equal(instance.historyIndex, 1);
        if (factory === menuWorkspace) instance.dirtySections.add('draft'); else instance.dirty = true;
        instance.guardHistory(event({ state: { [key]: { id: instance.historyId, index: 0 } } }));
        assert.deepEqual(app.history.at(-1), ['go', 1]);
        instance.stampHistory();
        instance.guardHistory(event({ state: { [key]: { id: instance.historyId, index: 1 } } }));
        instance.cancelNavigation();
        instance.guardHistory(event({ state: { [key]: { id: instance.historyId, index: 0 } } }));
        instance.discardAndNavigate();
        if (factory === menuWorkspace) instance.dirtySections.clear(); else instance.dirty = false;
        instance.guardHistory(event({ state: { [key]: { id: instance.historyId, index: 0 } } }));
        assert.equal(instance.historyIndex, 0);
        instance.destroy();
    }
});

test('staff lifecycle tracks edits, restores editor focus, blocks offline submissions and disposes DOM listeners', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance, editor = new Element(), input = new Element(), trigger = new Element(), error = new Element(), heading = new Element();
    input.ancestors.set('[data-staff-editor]', editor); trigger.setAttribute('wire:click', 'openMember(1)'); trigger.ancestors.set('[wire\\:click]', trigger);
    staff.$el.children.set('[data-staff-editor]', [editor]); staff.$el.children.set('[wire\\:click]', [trigger]); staff.$el.children.set('[role=alert]', [error]);
    staff.$el.ancestors.set('[data-staff-workspace]', staff.$el); staff.$el.children.set('[data-staff-heading]', [heading]);
    let presented = 0, closed = 0, discarded = 0;
    editor.close = () => { closed++; }; editor.children.set('input:not([type=hidden]), select', [input]);
    app.window.Alpine = { $data: () => ({ present() { presented++; } }) };
    staff.$wire.discardEditor = async () => { discarded++; };
    staff.$el.dispatchEvent({ type: 'input', target: input }); assert.equal(staff.dirty, true);
    const unload = event({ type: 'beforeunload' }); app.window.dispatchEvent(unload); assert.equal(unload.prevented, true);
    const navigate = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/away') } }); app.document.dispatchEvent(navigate); assert.equal(navigate.prevented, true);
    staff.cancelNavigation();
    staff.$el.dispatchEvent({ type: 'click', target: trigger });
    app.window.dispatchEvent({ type: 'staff-editor-opened' }); assert.equal(staff.dirty, false); assert.equal(presented, 1); assert.equal(input.focused, 1);
    app.window.dispatchEvent({ type: 'staff-validation-failed' }); assert.equal(error.focused, 1);
    app.window.dispatchEvent({ type: 'staff-editor-closed' }); assert.equal(trigger.focused, 1);
    app.window.dispatchEvent({ type: 'offline' }); const submit = event({ type: 'submit' }); staff.$el.dispatchEvent(submit); assert.equal(submit.prevented && submit.stopped, true);
    await staff.dismissEditor(); assert.equal(closed, 1); assert.equal(discarded, 0); assert.equal(staff.editorDismissed, true);
    app.window.dispatchEvent({ type: 'online' });
    staff.$el.dispatchEvent(event({ type: 'click', target: trigger })); await new Promise(setImmediate); assert.equal(discarded, 1); assert.equal(trigger.clicks, 1);
    staff.editorDismissed = true; staff.$wire.discardEditor = async () => { throw new Error('Lost'); };
    staff.$el.dispatchEvent(event({ type: 'click', target: trigger })); await new Promise(setImmediate); assert.ok(error.focused > 1);
    await staff.dismissEditor(); trigger.isConnected = false; staff.restoreFocus(); assert.ok(heading.focused > 0);
    staff.dirty = true; app.window.dispatchEvent({ type: 'staff-editor-clean' }); assert.equal(staff.dirty, false);
    app.window.dispatchEvent(event({ type: 'beforeunload' })); app.document.dispatchEvent(navigate);
    staff.dirty = true; staff.requestNavigation(() => { throw new Error('navigation failed'); }); staff.discardEditorBeforeNavigation = false; await staff.discardAndNavigate();
    staff.dirty = true; staff.requestNavigation(() => { staff.bypassNavigation = true; app.window.Livewire.navigate('/confirmed'); }, false); await staff.discardAndNavigate();
    staff.destroy(); assert.equal(app.window.listenerCount() + app.document.listenerCount() + staff.$el.listenerCount(), 0);
});

test('staff dialog responds to viewport changes and destroys its modal and media listener', t => {
    const app = browser(t), editor = app.component(staffEditor), viewport = new Events(), active = new Element(), opened = [];
    viewport.matches = false; app.window.matchMedia = () => viewport;
    app.document.activeElement = active; editor.$el.children.set('input', [active]);
    editor.$el.close = () => { editor.$el.open = false; opened.push('close'); };
    editor.$el.show = () => { editor.$el.open = true; opened.push('show'); };
    editor.$el.showModal = () => { editor.$el.open = true; opened.push('modal'); };
    editor.init(); assert.equal(opened.at(-1), 'modal');
    viewport.matches = true; viewport.dispatchEvent({ type: 'change' }); assert.equal(opened.at(-1), 'show'); assert.equal(active.focused, 2);
    editor.$el.isConnected = false; editor.present(); editor.$el.isConnected = true;
    editor.destroy(); assert.equal(viewport.listenerCount(), 0); assert.equal(editor.$el.open, false);
});

test('clean native history traversal updates the baseline and staff document navigation discards before leaving', async t => {
    for (const factory of [menuWorkspace, staffWorkspace]) {
        const app = workspace(t, factory), instance = app.instance;
        app.window.navigation = { currentEntry: { index: 3 } }; instance.stampHistory();
        app.window.navigation.currentEntry.index = 2; instance.guardHistory(event());
        assert.equal(instance.nativeHistoryIndex, 2);
        app.message({ id: 'workspace', el: instance.$el }).finish(); await new Promise(setImmediate);
        if (factory === staffWorkspace) {
            instance.dirty = true; instance.$wire.discardEditor = async () => {};
            app.document.dispatchEvent(event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/after') } }));
            await instance.discardAndNavigate(); assert.equal(app.state.navigation, 'https://menu.test/after');
        }
        instance.destroy();
    }
});

test('a pending staff reopen cannot click the old editor after the owning workspace is removed', async t => {
    const app = workspace(t, staffWorkspace), staff = app.instance, trigger = new Element();
    trigger.setAttribute('wire:click', 'openMember(1)'); trigger.ancestors.set('[wire\\:click]', trigger);
    staff.$el.children.set('[wire\\:click]', [trigger]); staff.editorDismissed = true;
    let resolve; staff.$wire.discardEditor = () => new Promise(done => { resolve = done; });
    staff.$el.dispatchEvent(event({ type: 'click', target: trigger })); staff.destroy(); resolve(); await new Promise(setImmediate);
    assert.equal(trigger.clicks, 0);
});

test('ordering uses the same dirty history guard and clears only explicitly discarded drafts', async t => {
    const { restaurantDashboard } = await import('../resources/js/alpine/components/presentation.js');
    const app = workspace(t, restaurantDashboard), dashboard = app.instance;
    const content = new Element(), form = new Element(), input = new Element();
    form.setAttribute('wire:submit', 'saveOrdering'); form.ancestors.set('[wire\\:id]', dashboard.$el);
    input.ancestors.set('form[wire\\:submit]', form); content.children.set('forms', [form]);
    dashboard.$el.children.set('[data-dashboard-ordering]', [content]);
    dashboard.$el.dispatchEvent({ type: 'input', target: input }); assert.equal(dashboard.hasUnsavedChanges(), true);
    const navigation = event({ type: 'livewire:navigate', detail: { url: new URL('https://menu.test/other') } });
    app.document.dispatchEvent(navigation); assert.equal(navigation.prevented, true);
    assert.deepEqual(app.dialogs.at(-1), ['show', 'dashboard-unsaved']);
    dashboard.cancelNavigation(); assert.equal(dashboard.hasUnsavedChanges(), true);
    app.message({ el: dashboard.$el, id: 'workspace' }, [{ name: 'discardOrdering' }], { snapshot: { memo: { errors: {} } } }).finish();
    assert.equal(dashboard.hasUnsavedChanges(), false);
    dashboard.destroy(); assert.equal(app.state.interceptors.size, 0);
});
