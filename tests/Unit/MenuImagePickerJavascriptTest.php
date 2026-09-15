<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('closing a photo editor releases preview URLs and its workspace dirty registration', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
let factory;
const released = [];
runInNewContext(readFileSync('resources/js/menu-image-picker.js', 'utf8'), {
    window: { Alpine: { data(name, callback) { if (name === 'menuImagePicker') factory = callback; } } },
    URL: { revokeObjectURL(value) { released.push(value); } },
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
});
const picker = factory({ itemId: 31 });
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
