import assert from 'node:assert/strict';
import test from 'node:test';
import { menuImagePicker, menuImagePresentationEditor } from '../resources/js/alpine/components/menu-image-picker.js';
import { menuTranslations } from '../resources/js/alpine/components/menu-translations.js';
import { workspaceNavigation } from '../resources/js/alpine/components/navigation-search.js';
import { browser, Element, event } from './alpine-support.mjs';

function images(t) {
    const app = browser(t);
    const urls = { created: [], revoked: [] };
    t.mock.method(URL, 'createObjectURL', file => { const url = `blob:${file.name}:${urls.created.length}`; urls.created.push(url); return url; });
    t.mock.method(URL, 'revokeObjectURL', url => urls.revoked.push(url));
    const instance = app.component(menuImagePicker, { itemId: 42 });
    instance.$refs.files = new Element();
    instance.init();
    return { ...app, instance, urls, choose(names) { instance.preview(event({ target: { files: names.map(name => ({ name })) } })); } };
}

test('image selection appends batches, failed/cancelled temporary uploads release only their URLs', t => {
    const app = images(t);
    const picker = app.instance;
    picker.preview(event({ target: {} }));
    assert.deepEqual(picker.previews, []);
    app.choose(['first']);
    picker.finishUpload();
    assert.equal(picker.progress, 100);
    app.choose(['second']);
    picker.failUpload();
    assert.deepEqual(picker.previews.map(image => image.name), ['first']);
    assert.deepEqual(app.urls.revoked, ['blob:second:1']);
    assert.equal(picker.failed, true);
    app.choose(['third']);
    picker.cancelUpload();
    assert.deepEqual(picker.previews.map(image => image.name), ['first']);
    assert.equal(picker.failed, false);
    picker.notifyDirty();
    assert.deepEqual(app.state.events.at(-1), { type: 'menu-workspace-dirty', detail: { key: 'images-42', dirty: true } });
    picker.destroy();
    assert.deepEqual(app.urls.revoked.sort(), app.urls.created.sort());
    assert.equal(app.state.events.at(-1).detail.dirty, false);
    assert.equal(app.state.interceptors.size, 0);
});

test('image controls serialize busy changes and scoped save callbacks', t => {
    const app = images(t);
    const picker = app.instance;
    for (const flag of ['uploading', 'removing', 'saving', 'offline']) {
        picker[flag] = true;
        const selection = event({ target: { files: [{ name: 'blocked' }] } });
        picker.preview(selection);
        assert.equal(selection.prevented && selection.stopped, true);
        const save = event();
        picker.beginSave(save);
        assert.equal(save.prevented && save.stopped, true);
        picker.drop({ dataTransfer: { files: [{ name: 'blocked' }] } });
        picker[flag] = false;
    }
    assert.deepEqual(app.urls.created, []);
    picker.drop({});
    const changes = [];
    picker.$refs.files.addEventListener('change', change => changes.push(change));
    picker.drop({ dataTransfer: { files: [{ name: 'dropped' }] } });
    assert.equal(changes.length, 1);
    assert.equal(picker.$refs.files.files[0].name, 'dropped');
    const unrelated = app.message({ id: 'other', el: new Element() }, [{ name: 'saveItemImages', params: [42] }]);
    unrelated.send();
    assert.equal(picker.saving, false);
    app.message({ id: 'component' }, [{ name: 'saveItemImages', params: [43] }]).send();
    assert.equal(picker.saving, false);
    const request = app.message({ id: 'component' }, [{ name: 'saveItemImages', params: [42] }]);
    picker.beginSave(event());
    request.send();
    assert.equal(picker.saving, true);
    request.finish();
    assert.equal(picker.saving, false);
    picker.destroy();
});

test('image removal waits for the authorized server result and preserves previews on failure', async t => {
    const app = images(t);
    const picker = app.instance;
    app.choose(['one', 'two']);
    picker.finishUpload();
    const requests = [];
    picker.$wire.removePendingItemImage = async (...args) => { requests.push(args); throw new Error('Offline'); };
    picker.offline = true;
    await picker.remove(1);
    picker.offline = false;
    await picker.remove(99);
    assert.equal(requests.length, 0);
    await picker.remove(1);
    assert.deepEqual(requests, [[42, 0]]);
    assert.equal(picker.previews.length, 2);
    assert.equal(picker.failed, true);
    picker.$wire.removePendingItemImage = async (...args) => { requests.push(args); };
    await picker.remove(1);
    assert.equal(picker.failed, false);
    assert.deepEqual(picker.previews.map(preview => preview.name), ['two']);
    picker.$wire.removePendingItemImage = async () => { picker.clear(); };
    await picker.remove(2);
    assert.equal(picker.removing, false);
    assert.deepEqual(app.urls.revoked.sort(), app.urls.created.sort());
    picker.$refs = {};
    picker.clear();
    picker.discardPendingPreviews();
    picker.destroy();
});

test('image metadata notifies its owning workspace and presentation focus follows locale/errors', t => {
    const app = browser(t);
    const picker = app.component(menuImagePicker, { itemId: 12 });
    const workspace = new Element();
    picker.$el.ancestors.set('[data-page="branch-menu"]', workspace);
    let dirty;
    workspace.addEventListener('menu-workspace-dirty', notification => { dirty = notification.detail; });
    const watches = [];
    picker.$watch = (name, callback) => watches.push(callback);
    picker.init();
    picker.metadataDirty = true;
    watches[0]();
    assert.deepEqual(dirty, { key: 'images-12', dirty: true });
    picker.destroy();
    assert.equal(dirty.dirty, false);
    const presentation = app.component(menuImagePresentationEditor, { focalX: 25, focalY: 80 });
    const heading = new Element(), tab = new Element(), panel = new Element(), invalid = new Element(), error = new Element();
    presentation.$refs.heading = heading;
    presentation.$el.children.set('[role="tab"][aria-selected="true"]', [tab]);
    presentation.init();
    assert.equal(heading.focused, 1);
    assert.equal(presentation.position, '25% 80%');
    presentation.nextLocale(-1);
    assert.equal(presentation.locale, 'ru');
    assert.equal(tab.focused, 1);
    presentation.$el.children.set('[data-photo-locale="lt"]', [panel]);
    panel.children.set('[aria-invalid="true"]', [invalid]);
    presentation.showError('lt');
    assert.equal(invalid.focused, 1);
    presentation.$el.children.set('[data-image-error]', [error]);
    presentation.showError('unsupported');
    assert.equal(presentation.locale, 'en');
    assert.equal(error.focused, 1);
    presentation.$el.children.clear();
    presentation.showError('ru');
    assert.equal(heading.focused, 2);
});

test('image presentation keeps its owner root when a child tab invokes locale navigation', t => {
    const app = browser(t), presentation = app.component(menuImagePresentationEditor, { focalX: 50, focalY: 50 });
    const root = presentation.$el, tab = new Element(), invalid = new Element(), panel = new Element();
    presentation.$refs.heading = new Element();
    root.children.set('[role="tab"][aria-selected="true"]', [tab]);
    root.children.set('[data-photo-locale="lt"]', [panel]);
    panel.children.set('[aria-invalid="true"]', [invalid]);
    presentation.init();
    presentation.$el = new Element();
    presentation.nextLocale(1);
    assert.equal(presentation.locale, 'lt');
    assert.equal(tab.focused, 1);
    presentation.showError('lt');
    assert.equal(invalid.focused, 1);
});

function translations(t, config = {}) {
    const app = browser(t);
    const values = new Map();
    const updates = [];
    const watches = [];
    const instance = app.component(menuTranslations, { model: 'translations', ...config });
    instance.$wire.$get = path => values.get(path);
    instance.$wire.$set = (...args) => { values.set(args[0], args[1]); updates.push(args); };
    instance.$watch = (read, callback) => watches.push({ read, callback });
    const observer = { disconnected: 0, observed: null, callback: null };
    const previous = globalThis.MutationObserver;
    globalThis.MutationObserver = class {
        constructor(callback) { observer.callback = callback; }
        observe(...args) { observer.observed = args; }
        disconnect() { observer.disconnected++; }
    };
    t.after(() => previous ? globalThis.MutationObserver = previous : delete globalThis.MutationObserver);
    return { ...app, instance, values, updates, watches, observer };
}

test('translations copy only empty target fields, keep user changes and synchronize primary authoring without a request', t => {
    const app = translations(t, { baseNameModel: 'name', baseDescriptionModel: 'description' });
    const editor = app.instance;
    app.values.set('translations.en.name', 'Soup');
    app.values.set('translations.en.description', 'Hot soup');
    app.values.set('translations.lt.name', 'Sriuba');
    app.values.set('translations.lt.description', ' ');
    app.values.set('translations.ru.name', 12);
    editor.init();
    assert.equal(editor.filled('en'), true);
    assert.equal(editor.filled('ru'), false);
    assert.equal(editor.canCopyOriginal('en'), false);
    assert.deepEqual(editor.copyableFields('lt'), ['description']);
    editor.copyOriginal('lt');
    assert.equal(app.values.get('translations.lt.name'), 'Sriuba');
    assert.equal(app.values.get('translations.lt.description'), 'Hot soup');
    assert.equal(editor.hasCopiedText('lt'), true);
    app.values.set('translations.lt.description', 'Changed');
    assert.equal(editor.hasCopiedText('lt'), false);
    editor.copyOriginal('lt');
    assert.equal(app.state.events.length, 1);
    assert.equal(editor.length('ą😀'), 2);
    for (const watch of app.watches) { watch.read(); watch.callback(); }
    assert.equal(app.values.get('name'), 'Soup');
    assert.equal(app.values.get('description'), 'Hot soup');
    assert.ok(app.updates.every(([, , live]) => live === false));
    editor.destroy();
    assert.equal(app.observer.disconnected, 1);
});

test('translation tabs reveal changed/repeated server errors and tear down their form subscription', t => {
    const app = translations(t, { nameOnly: true });
    const editor = app.instance;
    const form = new Element(), component = new Element();
    form.setAttribute('wire:submit', 'save(1)');
    component.setAttribute('wire:id', 'editor-component');
    editor.$el.ancestors.set('form', form);
    editor.$el.ancestors.set('[wire\\:id]', component);
    const tabs = ['en', 'lt', 'ru'].map(locale => { const tab = new Element(); tab.dataset.localeTab = locale; editor.$el.children.set(`[data-locale-tab="${locale}"]`, [tab]); return tab; });
    editor.$el.children.set('[data-locale-tab]', tabs);
    const invalid = new Element(), field = new Element();
    invalid.dataset.localePanel = 'ru';
    invalid.children.set('[aria-invalid="true"], input, textarea', [field]);
    editor.$el.children.set('[data-locale-panel][data-invalid="true"]', [invalid]);
    editor.init();
    assert.equal(editor.hasError('ru'), true);
    assert.equal(editor.active, 'ru');
    assert.equal(field.focused, 1);
    app.observer.callback();
    assert.equal(field.focused, 1);
    editor.active = 'en';
    app.message({ id: 'other' }, [{ name: 'save' }]).finish();
    app.message({ id: 'editor-component' }, [{ name: 'other' }]).finish();
    assert.equal(editor.active, 'en');
    form.dispatchEvent({ type: 'submit' });
    app.message({ id: 'editor-component' }, [{ name: 'save' }]).finish();
    assert.equal(editor.active, 'ru');
    assert.equal(field.focused, 2);
    for (const [key, expected] of [['ArrowRight', 'en'], ['ArrowLeft', 'ru'], ['Home', 'en'], ['End', 'ru']]) {
        const keypress = event({ key });
        editor.navigate(keypress);
        assert.equal(editor.active, expected);
        assert.equal(keypress.prevented, true);
    }
    const otherKey = event({ key: 'Enter' });
    editor.navigate(otherKey);
    assert.equal(otherKey.prevented, false);
    app.values.set('translations.en', 'Original');
    assert.equal(editor.description('en'), '');
    assert.equal(editor.canCopyOriginal('ru'), true);
    editor.copyOriginal('ru');
    assert.equal(app.values.get('translations.ru'), 'Original');
    editor.$el.children.clear();
    editor.navigate(event({ key: 'Home' }));
    editor.revealError();
    editor.$el.isConnected = false;
    app.message({ id: 'editor-component' }, [{ name: 'save' }]).finish();
    editor.destroy();
    assert.equal(form.listenerCount(), 0);
    assert.equal(app.state.interceptors.size, 0);
});

test('navigation search uses accents and permitted DOM results, ignoring typing and critical dialogs', t => {
    const app = browser(t);
    const instance = app.component(workspaceNavigation);
    const trigger = new Element(), search = new Element(), link = new Element(), row = new Element();
    row.dataset.searchLabel = 'Padaliniai — Šiauliai';
    row.children.set('a', [link]);
    instance.$el.children.set('[data-search-label]', [row]);
    instance.$el.children.set('[data-navigation-search-trigger]', [trigger]);
    instance.$el.children.set('[data-workspace-search-input]', [search]);
    let opened = 0, dialog = null;
    instance.$flux = { modal(name) { assert.equal(name, 'workspace-navigation'); return { show() { opened++; } }; } };
    app.document.querySelector = () => dialog;
    instance.init();
    instance.query = ' SIAULIAI ';
    assert.equal(instance.hasMatches, true);
    instance.focusFirstResult(); instance.visitFirstResult();
    assert.equal(link.focused, 1); assert.equal(link.clicks, 1);
    instance.query = 'no match';
    assert.equal(instance.hasMatches, false);
    instance.focusFirstResult(); instance.visitFirstResult();
    const target = new Element();
    for (const mods of [{ key: 'x' }, { ctrlKey: false }, { altKey: true }, { shiftKey: true }, { repeat: true }, { isComposing: true }]) {
        const keypress = event({ key: 'k', ctrlKey: true, target, ...mods });
        instance.handleShortcut(keypress);
        assert.equal(keypress.prevented, false);
    }
    target.isContentEditable = true;
    instance.handleShortcut(event({ key: 'k', ctrlKey: true, target }));
    target.isContentEditable = false;
    target.ancestors.set('input, textarea, select, [role="textbox"]', search);
    instance.handleShortcut(event({ key: 'k', ctrlKey: true, target }));
    target.ancestors.clear();
    dialog = new Element();
    instance.handleShortcut(event({ key: 'k', ctrlKey: true, target }));
    assert.equal(opened, 0);
    dialog = null;
    const keypress = event({ key: 'K', metaKey: true, target });
    instance.handleShortcut(keypress);
    assert.equal(keypress.prevented, true);
    assert.equal(opened, 1); assert.equal(search.focused, 1); assert.equal(trigger.focused, 1);
    assert.equal(instance.query, '');
});

test('destroy cancels its pending image upload and ignores late save/removal callbacks', async t => {
    const app = images(t), picker = app.instance, cancelled = [];
    picker.$wire.$cancelUpload = property => cancelled.push(property);
    app.choose(['pending']);
    picker.destroy();
    assert.deepEqual(cancelled, ['itemImageUploads.42']);
    assert.equal(picker.uploading, false);
    const later = images(t), active = later.instance;
    later.choose(['ready']); active.finishUpload();
    let reject;
    active.$wire.removePendingItemImage = () => new Promise((resolve, fail) => { reject = fail; });
    const removing = active.remove(1);
    const save = later.message({ id: 'component' }, [{ name: 'saveItemImages', params: [42] }]);
    active.destroy(); save.send(); reject(new Error('late'));
    await removing;
    assert.equal(active.failed, false); assert.equal(active.saving, false); assert.equal(active.removing, false);
    save.finish(); assert.equal(active.saving, false);
});

test('image preview allocation failure releases the partial batch and prevents an untracked upload', t => {
    const app = images(t), picker = app.instance;
    let count = 0;
    t.mock.method(URL, 'createObjectURL', () => { if (count++) throw new Error('Unavailable'); return 'blob:allocated'; });
    const selection = event({ target: { files: [{ name: 'one' }, { name: 'two' }] } });
    picker.preview(selection);
    assert.deepEqual(app.urls.revoked, ['blob:allocated']); assert.equal(selection.prevented && selection.stopped, true);
    assert.equal(picker.failed, true); assert.equal(picker.uploading, false); assert.deepEqual(picker.previews, []);
    picker.destroy();
});

test('image bindings keep progress, metadata dirtiness and successful receipts scoped to their item', t => {
    const app = images(t), picker = app.instance, input = new Element(), settings = new Element();
    picker.startUpload(); picker.updateProgress({ detail: { progress: 30 } }); assert.equal(picker.progress, 30);
    picker.finishUpload(); picker.goOffline(); assert.equal(picker.offline, true); picker.goOnline(); assert.equal(picker.offline, false);
    picker.markMetadataDirty({ target: input }); assert.equal(picker.metadataDirty, false);
    input.ancestors.set('[data-image-presentation-editor]', new Element()); picker.markMetadataDirty({ target: input }); assert.equal(picker.metadataDirty, true);
    picker.settingsTrigger = settings; picker.closePresentation({ detail: { itemId: 43 } }); assert.equal(picker.metadataDirty, true);
    picker.closePresentation({ detail: { itemId: 42 } }); assert.equal(picker.metadataDirty, false); assert.equal(settings.focused, 1);
    app.choose(['photo']); picker.finishUpload(); picker.imagesSaved({ detail: { itemId: 43 } }); assert.equal(picker.previews.length, 1);
    picker.imagesSaved({ detail: { itemId: 42 } }); assert.equal(picker.previews.length, 0); picker.destroy();
});
