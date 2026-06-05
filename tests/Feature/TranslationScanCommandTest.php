<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(translationScanFixturePath());
});

afterEach(function () {
    File::deleteDirectory(translationScanFixturePath());
});

test('translation scanner reports used missing unused and legacy keys', function () {
    $langDir = translationScanFixturePath('report/lang');
    $scanDir = translationScanFixturePath('report/app');

    translationScanWriteJson($langDir, 'en', [
        'guest.forms.name' => 'Your name',
        'qr.errors.not_found.title' => 'QR code not found',
        'ui.actions.cancel' => 'Cancel',
        'ui.actions.save' => 'Save',
        'QR code not found' => 'QR code not found',
    ]);
    translationScanWriteJson($langDir, 'lt', [
        'guest.forms.name' => 'Jūsų vardas',
        'qr.errors.not_found.title' => 'QR kodas nerastas',
        'ui.actions.cancel' => 'Atšaukti',
        'ui.actions.save' => 'Išsaugoti',
        'QR code not found' => 'QR kodas nerastas',
    ]);
    translationScanWriteJson($langDir, 'ru', [
        'guest.forms.name' => 'Ваше имя',
        'ui.actions.cancel' => 'Отмена',
        'ui.actions.save' => 'Сохранить',
        'QR code not found' => 'QR-код не найден',
    ]);

    File::ensureDirectoryExists($scanDir);
    File::put($scanDir.'/Example.php', <<<'PHP'
<?php

__('ui.actions.save');
trans('guest.forms.name');
trans_choice('orders.items.count', 2);
__('QR code not found');
__('Зона');
PHP);
    File::put($scanDir.'/example.blade.php', <<<'BLADE'
<span>@lang('ui.actions.missing')</span>
BLADE);

    $this->artisan('translations:scan', [
        '--lang-dir' => $langDir,
        '--scan-dir' => [$scanDir],
    ])
        ->expectsOutputToContain('Translation key scan report')
        ->expectsOutputToContain('Used keys')
        ->expectsOutputToContain('ui.actions.save')
        ->expectsOutputToContain('Missing keys')
        ->expectsOutputToContain('orders.items.count')
        ->expectsOutputToContain('ui.actions.missing')
        ->expectsOutputToContain('Unused JSON keys')
        ->expectsOutputToContain('ui.actions.cancel')
        ->expectsOutputToContain('Legacy phrase keys')
        ->expectsOutputToContain('QR code not found')
        ->expectsOutputToContain('Зона')
        ->assertSuccessful();
});

test('translation scanner can return json output', function () {
    $langDir = translationScanFixturePath('json/lang');
    $scanDir = translationScanFixturePath('json/app');

    foreach (['en', 'lt', 'ru'] as $locale) {
        translationScanWriteJson($langDir, $locale, [
            'ui.actions.save' => 'Save',
            'ui.actions.unused' => 'Unused',
        ]);
    }

    File::ensureDirectoryExists($scanDir);
    File::put($scanDir.'/Example.php', "<?php\n\n__('ui.actions.save');\n__('Missing phrase');\n");

    Artisan::call('translations:scan', [
        '--lang-dir' => $langDir,
        '--scan-dir' => [$scanDir],
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload['counts'])
        ->toMatchArray([
            'used_keys' => 2,
            'semantic_used_keys' => 1,
            'phrase_used_keys' => 1,
            'missing_keys' => 1,
            'unused_json_keys' => 1,
        ])
        ->and($payload['used_keys'])->toContain('ui.actions.save')
        ->and($payload['missing_keys'])->toContain('Missing phrase')
        ->and($payload['unused_json_keys'])->toContain('ui.actions.unused')
        ->and($payload['legacy_phrase_keys'])->toContain('Missing phrase');
});

function translationScanFixturePath(?string $path = null): string
{
    $basePath = storage_path('framework/testing/translation-scan');

    return $path === null ? $basePath : $basePath.'/'.$path;
}

/**
 * @param  array<string, string>  $lines
 */
function translationScanWriteJson(string $langDir, string $locale, array $lines): void
{
    File::ensureDirectoryExists($langDir);

    File::put(
        $langDir.'/'.$locale.'.json',
        json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    );
}
