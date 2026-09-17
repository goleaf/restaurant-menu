import assert from 'node:assert/strict';
import test from 'node:test';
import { browser } from './alpine-support.mjs';

test('restore finalization is blocked while uploading offline or already submitted and never replays on reconnect', async t => {
    const app = browser(t);
    const { restoreUpload } = await import('../resources/js/alpine/components/restore-upload.js');
    const upload = app.component(restoreUpload);
    let prevented = 0;
    const event = { preventDefault() { prevented++; } };
    upload.init();
    upload.startUpload(); upload.submit(event); assert.equal(prevented, 1);
    upload.finishUpload(); assert.equal(upload.uploading, false);
    upload.goOffline(); upload.submit(event); assert.equal(prevented, 2);
    upload.goOnline(); assert.equal(upload.submitting, false);
    upload.submit(event); assert.equal(upload.submitting, true);
    upload.submit(event); assert.equal(prevented, 3);
    upload.goOffline(); upload.goOnline(); assert.equal(upload.submitting, true);
    upload.startUpload(); upload.failUpload(); assert.equal(upload.uploading, false);
    upload.startUpload(); upload.cancelUpload(); assert.equal(upload.uploading, false);
    const cancelled = [];
    upload.$wire.$cancelUpload = property => cancelled.push(property);
    upload.startUpload(); upload.destroy(); upload.destroy();
    assert.deepEqual(cancelled, ['upload.backup']);
});
