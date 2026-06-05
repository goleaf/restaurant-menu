<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(translationAuditFixturePath());
});

afterEach(function () {
    File::deleteDirectory(translationAuditFixturePath());
});

test('translation audit passes for aligned semantic json keys and clean code scan', function () {
    $langDir = translationAuditFixturePath('clean/lang');
    $scanDir = translationAuditFixturePath('clean/app');

    translationAuditWriteJson($langDir, 'en', [
        'guest.forms.name' => 'Your name',
        'qr.errors.not_found.title' => 'QR code not found',
        'ui.actions.save' => 'Save',
    ]);
    translationAuditWriteJson($langDir, 'lt', [
        'guest.forms.name' => 'Jūsų vardas',
        'qr.errors.not_found.title' => 'QR kodas nerastas',
        'ui.actions.save' => 'Išsaugoti',
    ]);
    translationAuditWriteJson($langDir, 'ru', [
        'guest.forms.name' => 'Ваше имя',
        'qr.errors.not_found.title' => 'QR-код не найден',
        'ui.actions.save' => 'Сохранить',
    ]);

    File::ensureDirectoryExists($scanDir);
    File::put($scanDir.'/CleanComponent.php', "<?php\n\n__('ui.actions.save');\n");

    $this->artisan('translations:audit', [
        '--lang-dir' => $langDir,
        '--scan-dir' => [$scanDir],
    ])
        ->expectsOutputToContain('Translation audit report')
        ->expectsOutputToContain('Critical issues: 0')
        ->assertSuccessful();
});

test('translation audit fails for phrase keys missing keys empty values and phrase translation calls', function () {
    $langDir = translationAuditFixturePath('broken/lang');
    $scanDir = translationAuditFixturePath('broken/app');

    translationAuditWriteJson($langDir, 'en', [
        'guest.forms.name' => 'Your name',
        'orders.status.pending' => 'Pending',
        'placeholder.value' => 'TODO',
        'qr.errors.not_found.description' => '',
        'QR code not found' => 'QR code not found',
        'Ваше имя' => 'Your name',
    ]);
    translationAuditWriteJson($langDir, 'lt', [
        'guest.forms.name' => 'Jūsų vardas',
        'orders.status.pending' => 'Laukiama',
        'placeholder.value' => 'TODO',
        'qr.errors.not_found.description' => '',
        'QR code not found' => 'QR kodas nerastas',
    ]);
    translationAuditWriteJson($langDir, 'ru', [
        'guest.forms.name' => 'Ваше имя',
        'placeholder.value' => 'TODO',
        'qr.errors.not_found.description' => '',
        'QR code not found' => 'QR-код не найден',
        'Ваше имя' => 'Ваше имя',
    ]);

    File::ensureDirectoryExists($scanDir);
    File::put($scanDir.'/BrokenComponent.php', "<?php\n\n__('Please ask the staff for a fresh QR code.');\n__('ui.actions.save');\n");

    $this->artisan('translations:audit', [
        '--lang-dir' => $langDir,
        '--scan-dir' => [$scanDir],
    ])
        ->expectsOutputToContain('Critical issues:')
        ->expectsOutputToContain('Bad keys')
        ->expectsOutputToContain('QR code not found')
        ->expectsOutputToContain('Ваше имя')
        ->expectsOutputToContain('Missing keys')
        ->expectsOutputToContain('orders.status.pending')
        ->expectsOutputToContain('Empty or placeholder values')
        ->expectsOutputToContain('placeholder.value')
        ->expectsOutputToContain('Potential phrase-style translation calls')
        ->expectsOutputToContain('Please ask the staff for a fresh QR code.')
        ->assertFailed();
});

function translationAuditFixturePath(?string $path = null): string
{
    $basePath = storage_path('framework/testing/translation-audit');

    return $path === null ? $basePath : $basePath.'/'.$path;
}

/**
 * @param  array<string, string>  $lines
 */
function translationAuditWriteJson(string $langDir, string $locale, array $lines): void
{
    File::ensureDirectoryExists($langDir);

    File::put(
        $langDir.'/'.$locale.'.json',
        json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    );
}
