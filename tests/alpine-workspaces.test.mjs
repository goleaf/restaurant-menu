import assert from 'node:assert/strict';
import test from 'node:test';
import { menuWorkspace } from '../resources/js/alpine/components/menu-workspace.js';
import { staffWorkspace, invitationClipboard, staffEditor } from '../resources/js/alpine/components/staff-workspace.js';
import { browser, Element, Events, event } from './alpine-support.mjs';

function workspace(t, factory) {
    const app = browser(t), history = [], dialogs = [];
    app.window.location = { href: 'https://menu.test/menu?section=catalog', pathname: '/menu' };
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
