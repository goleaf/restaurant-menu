<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::deleteDirectory(translationAuditFixturePath());
});

afterEach(function () {
    File::deleteDirectory(translationAuditFixturePath());
});

test('default translation audit includes the maintained Pro template boundary without unused keys', function (): void {
    $this->artisan('translations:audit')
        ->expectsOutputToContain('Critical issues: 0')
        ->assertSuccessful();
});

test('translation audit fails when a used semantic key is missing from every locale', function (): void {
    $langDir = translationAuditFixturePath('absent/lang');
    $scanDir = translationAuditFixturePath('absent/app');
    foreach (['en', 'lt', 'ru'] as $locale) {
        translationAuditWriteJson($langDir, $locale, ['ui.actions.save' => 'Save']);
    }
    File::ensureDirectoryExists($scanDir);
    File::put($scanDir.'/Example.php', "<?php\n__('ui.actions.save');\n__('ui.actions.missing');\n");

    $this->artisan('translations:audit', ['--lang-dir' => $langDir, '--scan-dir' => [$scanDir]])
        ->expectsOutputToContain('ui.actions.missing')->assertFailed();
});

test('translation audit recognizes concatenated semantic prefixes without accepting standalone prefixes', function (): void {
    $langDir = translationAuditFixturePath('prefix/lang');
    $scanDir = translationAuditFixturePath('prefix/app');
    foreach (['en', 'lt', 'ru'] as $locale) {
        translationAuditWriteJson($langDir, $locale, ['ui.timer.started' => 'Started']);
    }
    File::ensureDirectoryExists($scanDir);
    File::put($scanDir.'/Example.php', <<<'PHP'
<?php
__('ui.timer.'.$basis);
PHP);
    $this->artisan('translations:audit', ['--lang-dir' => $langDir, '--scan-dir' => [$scanDir]])->assertSuccessful();
    File::put($scanDir.'/Example.php', "<?php\n__('ui.timer.');\n");
    $this->artisan('translations:audit', ['--lang-dir' => $langDir, '--scan-dir' => [$scanDir]])->assertFailed();
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
    File::put($scanDir.'/CleanComponent.php', <<<'PHP'
<?php

__('guest.forms.name');
__('qr.errors.not_found.title');
__('ui.actions.save');
PHP);

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
        'ui.actions.unused' => 'Unused',
        'ui.messages.saved' => 'Saved :name',
        'QR code not found' => 'QR code not found',
        'Ваше имя' => 'Your name',
    ]);
    translationAuditWriteJson($langDir, 'lt', [
        'guest.forms.name' => 'Jūsų vardas',
        'orders.status.pending' => 'Laukiama',
        'placeholder.value' => 'TODO',
        'qr.errors.not_found.description' => '',
        'ui.actions.unused' => 'Nenaudojama',
        'ui.messages.saved' => 'Išsaugota',
        'QR code not found' => 'QR kodas nerastas',
    ]);
    translationAuditWriteJson($langDir, 'ru', [
        'guest.forms.name' => 'Ваше имя',
        'placeholder.value' => 'TODO',
        'qr.errors.not_found.description' => '',
        'ui.actions.unused' => 'Не используется',
        'ui.messages.saved' => 'Сохранено :name',
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
        ->expectsOutputToContain('Unused keys')
        ->expectsOutputToContain('ui.actions.unused')
        ->expectsOutputToContain('Placeholder mismatches')
        ->expectsOutputToContain('ui.messages.saved')
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
