<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

test('translation copy fills only empty fields and keeps a review notice until copied text changes', function (): void {
    $script = <<<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

let factory;
runInNewContext(readFileSync('resources/js/menu-translations.js', 'utf8'), {
    window: { Alpine: { data(name, callback) { factory = callback; } } },
});
function editor(values, nameOnly = false) {
    const writes = [];
    const events = [];
    const instance = factory({ model: 'translations', nameOnly });
    instance.$dispatch = (name) => events.push(name);
    instance.$wire = {
        $get(path) { return values[path]; },
        $set(path, value, live) { writes.push({ path, value, live }); values[path] = value; },
    };
    return { instance, writes, events };
}
const values = {
    'translations.en.name': 'Beet soup',
    'translations.en.description': 'First paragraph.\n\nSecond paragraph <strong>plain text</strong>.',
    'translations.lt.name': 'Šaltibarščiai',
    'translations.lt.description': '   ',
    'translations.ru.name': '',
    'translations.ru.description': 'Сохранённое описание',
};
const { instance, writes, events } = editor(values);
assert.equal(instance.canCopyOriginal('lt'), true);
instance.copyOriginal('lt');
assert.equal(values['translations.lt.name'], 'Šaltibarščiai');
assert.equal(values['translations.lt.description'], values['translations.en.description']);
assert.equal(instance.hasCopiedText('lt'), true);
assert.equal(instance.canCopyOriginal('lt'), false);
assert.equal(writes.length, 1);
assert.equal(writes[0].live, false);
assert.deepEqual(events, ['input']);
instance.copyOriginal('lt');
assert.equal(writes.length, 1);
assert.equal(events.length, 1);
values['translations.lt.description'] = 'Pirma pastraipa.\n\nAntra pastraipa.';
assert.equal(instance.hasCopiedText('lt'), false);
instance.copyOriginal('ru');
assert.equal(values['translations.ru.name'], 'Beet soup');
assert.equal(values['translations.ru.description'], 'Сохранённое описание');
assert.equal(instance.hasCopiedText('ru'), true);
instance.copyOriginal('en');
instance.copyOriginal('__proto__');
instance.copyOriginal('de');
assert.equal(writes.length, 2);
const names = editor({ 'translations.en': 'Lunch', 'translations.lt': '', 'translations.ru': 'Обед' }, true);
names.instance.copyOriginal('lt');
assert.equal(names.writes.length, 1);
assert.equal(names.writes[0].path, 'translations.lt');
assert.equal(names.instance.hasCopiedText('lt'), true);
assert.equal(names.instance.canCopyOriginal('ru'), false);
JS;

    $process = new Process(['node', '--input-type=module', '-e', $script], dirname(__DIR__, 2));
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
});
