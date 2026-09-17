import assert from 'node:assert/strict';
import test from 'node:test';
import { availabilityWorkspace } from '../resources/js/alpine/components/availability-workspace.js';
import { browser, Element, event } from './alpine-support.mjs';

function createWorkspace(t) {
    const app = browser(t);
    app.window.location = { href: 'https://menu.test/availability?section=stoplist' };
    app.window.history = { state: {}, replaceState(value) { this.state = value; } };
    const instance = app.component(availabilityWorkspace);
    instance.$el.setAttribute('wire:id', 'availability');
    instance.$flux = { modal() { return { show() {}, close() {} }; } };
    instance.$wire.section = 'stoplist';
    instance.init();
    return { ...app, instance };
}

test('availability preview remains a draft until applied or explicitly cancelled', t => {
    const app = createWorkspace(t), workspace = app.instance;
    app.window.dispatchEvent({ type: 'availability-preview-ready' });
    assert.equal(workspace.hasUnsavedChanges(), true);
    app.window.dispatchEvent({ type: 'availability-draft-cleared' });
    assert.equal(workspace.hasUnsavedChanges(), false);
    workspace.destroy();
    assert.equal(app.state.interceptors.size, 0);
    assert.equal(app.window.listenerCount() + app.document.listenerCount() + workspace.$el.listenerCount(), 0);
});

test('availability offline cancel does not replay a restriction and clears deferred state before explicit navigation', async t => {
    const app = createWorkspace(t), workspace = app.instance, editor = new Element();
    workspace.$el.children.set('[data-availability-editor]', [editor]);
    let discarded = 0, navigated = 0;
    workspace.$wire.discardDraft = async () => { discarded++; };
    workspace.$wire.selectSection = async () => { navigated++; };
    app.navigator.onLine = false;
    workspace.dirtySections.add('availability-draft');
    await workspace.cancelDraft();
    assert.equal(editor.hidden, true);
    assert.equal(discarded, 0);
    assert.equal(workspace.hasUnsavedChanges(), false);
    app.navigator.onLine = true;
    app.window.dispatchEvent({ type: 'online' });
    await new Promise(setImmediate);
    assert.equal(discarded, 0);
    await workspace.navigateSection(event(), 'now');
    assert.equal(discarded, 1);
    assert.equal(navigated, 1);
    workspace.destroy();
});

test('availability keyboard submission offline cannot enqueue a server apply', t => {
    const app = createWorkspace(t);
    app.navigator.onLine = false;
    const submit = event({ type: 'submit', target: new Element() });
    app.instance.$el.dispatchEvent(submit);
    assert.equal(submit.prevented, true);
    app.instance.destroy();
});

test('server validation and editor lifecycle return focus and track only the active unsaved draft', t => {
    const app = createWorkspace(t), workspace = app.instance, editor = new Element(), error = new Element();
    workspace.$el.children.set('[data-availability-editor]', [editor]);
    workspace.$el.children.set('[role="alert"]', [error]);
    workspace.locallyDiscarded = true;
    editor.hidden = true;
    app.window.dispatchEvent({ type: 'availability-editor-opened' });
    assert.equal(workspace.locallyDiscarded, false);
    assert.equal(editor.hidden, false);
    assert.equal(editor.focused, 1);
    app.window.dispatchEvent({ type: 'availability-draft-dirty' });
    assert.equal(workspace.hasUnsavedChanges(), true);
    app.window.dispatchEvent({ type: 'availability-validation-failed' });
    assert.equal(error.focused, 1);
    assert.equal(workspace.hasUnsavedChanges(), true);
    workspace.destroy();
    editor.hidden = true;
    app.window.dispatchEvent({ type: 'availability-editor-opened' });
    assert.equal(editor.hidden, true);
});

test('changing the active editor waits for a deliberate discard and leaves a cancelled draft intact', async t => {
    const app = createWorkspace(t), workspace = app.instance, trigger = new Element();
    trigger.setAttribute('wire:click', 'openPause');
    trigger.ancestors.set('[wire\\:click]', trigger);
    let discarded = 0;
    workspace.$wire.discardDraft = async () => { discarded++; };
    workspace.dirtySections.add('availability-draft');
    const click = event({ type: 'click', target: trigger });
    workspace.$el.dispatchEvent(click);
    assert.equal(click.prevented, true);
    assert.equal(discarded, 0);
    workspace.cancelNavigation();
    assert.equal(workspace.hasUnsavedChanges(), true);
    workspace.$el.dispatchEvent(event({ type: 'click', target: trigger }));
    await workspace.discardAndNavigate();
    assert.equal(trigger.clicks, 1);
    assert.equal(workspace.hasUnsavedChanges(), false);
    workspace.destroy();
});
