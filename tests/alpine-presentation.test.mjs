import assert from 'node:assert/strict';
import test from 'node:test';
import { browser, Element } from './alpine-support.mjs';

const factories = () => import('../resources/js/alpine/components/presentation.js');

test('dashboard focuses prepared invalid fields and releases queued animation frames on removal', async t => {
    const app = browser(t), frames = new Map();
    let next = 0;
    const originalRequest = globalThis.requestAnimationFrame, originalCancel = globalThis.cancelAnimationFrame;
    globalThis.requestAnimationFrame = callback => { frames.set(++next, callback); return next; };
    globalThis.cancelAnimationFrame = id => frames.delete(id);
    t.after(() => { globalThis.requestAnimationFrame = originalRequest; globalThis.cancelAnimationFrame = originalCancel; });
    const { restaurantDashboard } = await factories();
    const dashboard = app.component(restaurantDashboard), picker = new Element(), search = new Element(), summary = new Element(), field = new Element(), details = new Element();
    dashboard.$refs = { branchPicker: picker, branchSearch: search, branchPickerSummary: summary };
    const dialogs = []; dashboard.$flux = { modal(name) { return { show() { dialogs.push(['show', name]); }, close() { dialogs.push(['close', name]); } }; } };
    dashboard.openBranchPicker(); assert.deepEqual(dialogs.at(-1), ['show', 'workspace-restaurant']);
    details.tagName = 'DETAILS'; details.parentElement = dashboard.$el; field.parentElement = details;
    dashboard.$el.children.set('[aria-invalid="true"]', [field]);
    dashboard.focusValidationError(); dashboard.focusValidationError(); assert.equal(frames.size, 1);
    const [id, callback] = [...frames.entries()][0]; frames.delete(id); callback();
    assert.equal(details.open, true); assert.equal(field.focused, 1);
    dashboard.focusValidationError(); const late = [...frames.values()][0]; dashboard.destroy(); late();
    assert.equal(frames.size, 0); assert.equal(field.focused, 1);
});

test('onboarding and CSV focus stay inside a mounted scope and recovery visibility is local', async t => {
    const app = browser(t);
    const { onboardingFocus, catalogTransfer, recoveryCodes } = await factories();
    const onboarding = app.component(onboardingFocus), heading = new Element(), error = new Element(), summary = new Element();
    onboarding.$el.children.set('[data-onboarding-step-heading]', [heading]);
    onboarding.$el.children.set('[aria-invalid="true"]', [error]);
    onboarding.$el.children.set('#onboarding-validation-summary', [summary]);
    onboarding.$el.children.set('[aria-invalid="true"], #onboarding-validation-summary', [summary, error]);
    onboarding.focusStep(); onboarding.focusValidationError();
    assert.equal(heading.focused, 1); assert.equal(error.focused, 1); assert.equal(summary.focused, 0);
    onboarding.$el.children.delete('[aria-invalid="true"]');
    onboarding.focusValidationError(); assert.equal(summary.focused, 1);
    onboarding.$el.isConnected = false; onboarding.focusStep(); assert.equal(heading.focused, 1);
    const transfer = app.component(catalogTransfer); transfer.$refs.feedback = heading;
    transfer.focusFeedback(); assert.equal(heading.focused, 2);
    transfer.$el.isConnected = false; transfer.focusFeedback(); assert.equal(heading.focused, 2);
    const codes = recoveryCodes(); codes.show(); assert.equal(codes.showRecoveryCodes, true); codes.hide(); assert.equal(codes.showRecoveryCodes, false);
});

test('CSV upload keeps dirty state after failed upload and cancels only its active Livewire transport on removal', async t => {
    const app = browser(t);
    const { catalogUpload } = await factories();
    const upload = app.component(catalogUpload), cancelled = [];
    upload.$wire.$cancelUpload = property => cancelled.push(property);
    upload.selectionChanged({ target: { files: [{ name: 'menu.csv' }] } });
    assert.equal(app.state.events.at(-1).detail.dirty, true);
    upload.startUpload(); upload.updateProgress({ detail: { progress: 45 } }); assert.equal(upload.progress, 45);
    upload.failUpload(); assert.equal(upload.uploading, false); assert.equal(app.state.events.at(-1).detail.dirty, true);
    upload.startUpload(); upload.finishUpload(); assert.equal(upload.progress, 100);
    upload.startUpload(); upload.cancelUpload(); assert.equal(upload.uploading, false);
    upload.startUpload(); upload.destroy(); assert.deepEqual(cancelled, ['form.file']); assert.equal(app.state.events.at(-1).detail.dirty, false);
    upload.destroy(); assert.equal(cancelled.length, 1);
});

test('named bindings label dialogs, focus surviving controls and invoke native printing', async t => {
    const app = browser(t);
    const { dialogLabel, focusInput, focusSelf, printDocument } = await import('../resources/js/alpine/bindings/presentation.js');
    const heading = new Element(), dialog = new Element(), input = new Element(); heading.id = 'heading'; heading.ancestors.set('dialog', dialog);
    dialogLabel()['x-init'].call({ $el: heading }); assert.equal(dialog.getAttribute('aria-labelledby'), 'heading');
    heading.matches = () => false; heading.children.set('input', [input]);
    focusInput()['x-init'].call({ $el: heading, $nextTick: callback => callback() }); assert.equal(input.focused, 1);
    heading.matches = () => true; focusInput()['x-init'].call({ $el: heading, $nextTick: callback => callback() }); assert.equal(heading.focused, 1);
    heading.isConnected = false; focusInput()['x-init'].call({ $el: heading, $nextTick: callback => callback() }); assert.equal(heading.focused, 1);
    focusSelf()['@click'].call({ $el: heading }); assert.equal(heading.focused, 2);
    let printed = 0; app.window.print = () => printed++; printDocument()['@click'](); assert.equal(printed, 1);
});

test('a selected identity logo upload cancels its own field and clears only its own dirty marker', async t => {
    const app = browser(t);
    const { catalogUpload } = await factories();
    const upload = app.component(() => catalogUpload({ field: 'logo', key: 'restaurant-logo' })), cancelled = [];
    upload.$wire.$cancelUpload = property => cancelled.push(property);
    upload.selectionChanged({ target: { files: [{ name: 'logo.png' }] } });
    assert.deepEqual(app.state.events.at(-1).detail, { key: 'restaurant-logo', dirty: true });
    upload.startUpload(); upload.destroy();
    assert.deepEqual(cancelled, ['logo']);
    upload.clearSelection();
    assert.deepEqual(app.state.events.at(-1).detail, { key: 'restaurant-logo', dirty: false });
});
