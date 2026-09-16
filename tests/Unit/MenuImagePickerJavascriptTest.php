<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('closing a photo editor releases preview URLs and its workspace dirty registration', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
const { menuImagePicker } = await import('./resources/js/alpine/components/menu-image-picker.js');
const released = [];
globalThis.URL.revokeObjectURL = (value) => released.push(value);
const picker = menuImagePicker({ itemId: 31 });
const registrations = new Set();
let connected = true;
const receive = (name, detail) => {
    assert.equal(name, 'menu-workspace-dirty');
    if (detail.dirty) registrations.add(detail.key);
    else registrations.delete(detail.key);
};
picker.$dispatch = (name, detail) => { if (connected) receive(name, detail); };
picker.workspace = { isConnected: true, dispatchEvent(event) { receive(event.type, event.detail); } };
picker.previews = [{ key: 1, url: 'blob:test' }];
picker.metadataDirty = true;
picker.notifyDirty();
assert.equal(registrations.has('images-31'), true);
connected = false;
picker.destroy();
assert.equal(registrations.size, 0);
assert.equal(picker.metadataDirty, false);
assert.deepEqual(released, ['blob:test']);
picker.destroy();
assert.deepEqual(released, ['blob:test']);
JS;
    $process = new Process(['node', '--input-type=module', '-e', $script], dirname(__DIR__, 2));
    $process->run();
    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
});
