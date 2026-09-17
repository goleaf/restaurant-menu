import assert from 'node:assert/strict';
import test from 'node:test';
import { floorWorkspace } from '../resources/js/alpine/components/floor-workspace.js';
import { browser, Element, event } from './alpine-support.mjs';

function setup(t) {
    const app = browser(t), dialogs = [], calls = [];
    app.window.location = { href: 'https://menu.test/branches/1/floor' };
    app.window.history = { state: {}, replaceState(value) { this.state = value; } };
    const instance = app.component(floorWorkspace);
    instance.$el.setAttribute('wire:id', 'floor');
    instance.$wire.$id = 'floor';
    instance.$wire.clearEditor = async () => calls.push('clear');
    instance.$flux = { modal: name => ({ show: () => dialogs.push(['show', name]), close: () => dialogs.push(['close', name]) }) };
    const content = new Element(), editor = new Element(), child = new Element(), form = new Element(), input = new Element();
    child.setAttribute('wire:id', 'child');
    form.setAttribute('wire:submit', 'save');
    form.ancestors.set('[wire\\:id]', child);
    form.ancestors.set('form', form);
    input.ancestors.set('form[wire\\:submit]', form);
    content.children.set('forms', [form]);
    instance.$el.children.set('[data-floor-content]', [content]);
    instance.$el.children.set('[data-floor-editor-shell]', [editor]);
    instance.$el.children.set('children', [child]);
    instance.init();
    const dirty = () => instance.$el.dispatchEvent({ type: 'input', target: input });
    const trigger = () => {
        const button = new Element();
        button.selector = '[data-floor-transition]';
        button.click = () => { button.clicks++; instance.$el.dispatchEvent(event({ type: 'click', target: button })); };
        instance.$el.children.set('trigger', [button]);
        return button;
    };
    return { ...app, instance, dialogs, calls, editor, child, form, input, dirty, trigger };
}

test('floor transitions retain drafts on cancel and replay the selected editor action once', async t => {
    const app = setup(t), button = app.trigger();
    app.dirty();
    const click = event({ type: 'click', target: button });
    app.instance.$el.dispatchEvent(click);
    assert.equal(click.prevented && click.stopped, true);
    assert.deepEqual(app.dialogs, [['show', 'floor-unsaved']]);
    app.instance.cancelNavigation();
    assert.equal(app.instance.hasUnsavedChanges(), true);
    assert.equal(button.clicks, 0);
    app.instance.$el.dispatchEvent(event({ type: 'click', target: button }));
    await app.instance.discardAndNavigate();
    assert.deepEqual(app.calls, []);
    assert.equal(button.clicks, 1);
    assert.equal(app.instance.hasUnsavedChanges(), false);
    app.instance.destroy();
});

test('online closing restores focus and a failed close retains draft protection', async t => {
    const app = setup(t), button = app.trigger(), error = new Element();
    app.instance.$el.children.set('[role="alert"]', [error]);
    await app.instance.handleTransition(event({ target: button }));
    await app.instance.closeEditor();
    assert.deepEqual(app.calls, ['clear']);
    assert.equal(button.focused, 1);
    app.dirty(); app.instance.$wire.clearEditor = async () => { throw new Error('Disconnected'); };
    app.instance.closeEditor();
    await app.instance.discardAndNavigate();
    assert.equal(app.instance.hasUnsavedChanges(), true);
    assert.equal(error.focused, 1);
    assert.equal(app.instance.transitioning, false);
    app.instance.destroy();
});

test('failed reopening remains locally closed and never replays an operation', async t => {
    const app = setup(t), button = app.trigger();
    app.instance.locallyClosed = true;
    app.instance.$wire.clearEditor = async () => { throw new Error('Denied'); };
    await app.instance.handleTransition(event({ target: button }));
    assert.equal(button.clicks, 0);
    assert.equal(app.instance.locallyClosed, true);
    assert.equal(app.instance.transitioning, false);
    app.instance.destroy();
});

test('offline child cancellation hides its locked operation without writing properties and printing needs the scoped ready event', t => {
    const app = setup(t), cancel = new Element(); let prints = 0;
    cancel.selector = '[data-floor-cancel]';
    app.window.print = () => prints++;
    app.instance.$wire.$set = () => assert.fail('Locked operation must not change locally');
    app.dirty(); app.navigator.onLine = false;
    const click = event({ type: 'click', target: cancel }); app.instance.$el.dispatchEvent(click);
    assert.equal(click.prevented && click.stopped, true);
    assert.equal(app.editor.hidden, true);
    assert.deepEqual(app.calls, []);
    assert.equal(app.instance.hasUnsavedChanges(), false);
    app.window.dispatchEvent({ type: 'floor-print-ready' }); assert.equal(prints, 0);
    app.instance.$el.dispatchEvent({ type: 'floor-print-ready' }); assert.equal(prints, 1);
    app.instance.destroy();
    app.instance.$el.dispatchEvent({ type: 'floor-print-ready' }); assert.equal(prints, 1);
});

test('floor local form discard targets the child wire, preserves other drafts, and makes no request', t => {
    const app = setup(t), button = new Element(), error = new Element(), field = new Element(), updates = [];
    app.dirty();
    button.ancestors.set('form', app.form);
    button.ancestors.set('[wire\\:id]', app.child);
    app.form.children.set('[role="alert"]', [error]);
    app.form.children.set('[aria-invalid="true"]', [field]);
    const baseline = { name: 'Stored' };
    app.window.Livewire.find = id => { assert.equal(id, 'child'); return { $get: () => baseline, $set: (...args) => updates.push(args) }; };
    app.instance.$wire.$set = () => assert.fail('Parent form must not be changed');
    app.instance.dirtySections.add('other');
    app.navigator.onLine = false;
    app.instance.discardFormLocally(event({ currentTarget: button }), 'form', 'baseline');
    assert.deepEqual(updates, [['form', baseline, false]]);
    assert.notEqual(updates[0][1], baseline);
    assert.equal(error.hidden, true);
    assert.equal(field.getAttribute('aria-invalid'), 'false');
    assert.equal(app.instance.dirtyForms.has(app.form), false);
    assert.equal(app.instance.dirtySections.has('other'), true);
    assert.deepEqual(app.calls, []);
    app.instance.destroy();
});

test('offline editor close stays local and reconnect sends nothing until a fresh explicit transition', async t => {
    const app = setup(t), button = app.trigger();
    app.dirty(); app.navigator.onLine = false;
    app.instance.closeEditor();
    await app.instance.discardAndNavigate();
    assert.equal(app.editor.hidden, true);
    assert.equal(app.instance.locallyClosed, true);
    assert.deepEqual(app.calls, []);
    app.navigator.onLine = true;
    app.window.dispatchEvent({ type: 'online' });
    assert.deepEqual(app.calls, []);
    await app.instance.handleTransition(event({ target: button }));
    assert.deepEqual(app.calls, ['clear']);
    assert.equal(button.clicks, 1);
    app.instance.destroy();
});

test('floor filters and unrelated controls are clean while offline requests and concurrent transitions are blocked', async t => {
    const app = setup(t), button = app.trigger();
    app.instance.$el.dispatchEvent({ type: 'input', target: new Element() });
    assert.equal(app.instance.hasUnsavedChanges(), false);
    const ordinary = event({ target: new Element() });
    await app.instance.handleTransition(ordinary);
    assert.equal(ordinary.prevented, false);
    const clean = event({ target: button });
    await app.instance.handleTransition(clean);
    assert.equal(clean.prevented, false);
    app.navigator.onLine = false;
    const click = event({ target: button }), submit = event({ type: 'submit' });
    await app.instance.handleTransition(click); app.instance.$el.dispatchEvent(submit);
    assert.equal(click.prevented && submit.prevented, true);
    assert.deepEqual(app.calls, []);
    app.navigator.onLine = true;
    const onlineSubmit = event({ type: 'submit' }); app.instance.$el.dispatchEvent(onlineSubmit);
    assert.equal(onlineSubmit.prevented, false);
    app.instance.transitioning = true;
    const concurrent = event({ target: button }); await app.instance.handleTransition(concurrent);
    assert.equal(concurrent.prevented, true);
    app.instance.destroy();
});

test('pending child operations block dismissal and transitions until sync releases the matching request once', async t => {
    const app = setup(t), button = app.trigger();
    const first = app.message({ id: 'child', el: app.child }, [{ name: 'save' }]);
    const second = app.message({ id: 'floor', el: app.instance.$el }, [{ name: 'selectPage' }]);
    first.send(); second.send();
    assert.equal(app.instance.pendingRequests, 2);
    await app.instance.closeEditor();
    const click = event({ target: button }); await app.instance.handleTransition(click);
    assert.equal(click.prevented, true);
    assert.deepEqual(app.calls, []);
    first.sync(); first.finish();
    assert.equal(app.instance.pendingRequests, 1);
    second.sync(); second.sync(); second.finish();
    assert.equal(app.instance.pendingRequests, 0);
    await app.instance.closeEditor();
    assert.deepEqual(app.calls, ['clear']);
    app.instance.destroy();
});

test('successful child save clears only submitted drafts and scoped lifecycle focuses and cleans subscriptions', t => {
    const app = setup(t);
    app.dirty();
    app.message({ id: 'child', el: app.child }, [{ name: 'save' }], { snapshot: { memo: { errors: { 'form.name': ['Required'] } } } }).finish();
    assert.equal(app.instance.hasUnsavedChanges(), true);
    app.message({ id: 'child', el: app.child }, [{ name: 'save' }], { snapshot: { memo: { errors: {} } } }).finish();
    assert.equal(app.instance.hasUnsavedChanges(), false);
    app.instance.locallyClosed = true; app.editor.hidden = true;
    app.message({ id: 'floor', el: app.instance.$el }, [{ name: 'openPoint' }]).finish();
    assert.equal(app.editor.hidden, false);
    assert.equal(app.editor.focused, 1);
    app.instance.$el.dispatchEvent({ type: 'floor-invalid', target: app.child });
    app.dirty(); app.child.selector = '[wire\\:id]';
    app.instance.dirtySections.add('qr');
    app.instance.$el.dispatchEvent({ type: 'floor-editor-saved', target: app.child, detail: { draftKey: 'qr' } });
    assert.equal(app.instance.hasUnsavedChanges(), false);
    app.dirty();
    app.instance.$el.dispatchEvent({ type: 'floor-editor-cancelled', target: app.child });
    assert.equal(app.instance.hasUnsavedChanges(), false);
    app.instance.destroy();
    assert.equal(app.state.interceptors.size, 0);
    assert.equal(app.window.listenerCount() + app.document.listenerCount() + app.instance.$el.listenerCount(), 0);
});
