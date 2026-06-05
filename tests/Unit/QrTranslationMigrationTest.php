<?php

test('qr translations use semantic json keys across supported locales', function () {
    $requiredKeys = qrTranslationRequiredKeys();
    $legacyPhraseKeys = qrTranslationLegacyPhraseKeys();

    foreach (qrTranslationLangFiles() as $locale => $path) {
        $translations = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        expect(array_keys($translations))->toContain(...$requiredKeys)
            ->and(array_intersect($legacyPhraseKeys, array_keys($translations)))
            ->toBe([]);

        foreach ($requiredKeys as $key) {
            expect($translations[$key] ?? null)
                ->toBeString()
                ->not->toBe('');
        }
    }
});

test('qr module does not use legacy phrase translation keys', function () {
    $offenders = [];
    $legacyUsedKeys = [
        ...qrTranslationLegacyPhraseKeys(),
        ...qrTranslationLegacyUsedKeys(),
    ];

    foreach (qrTranslationSourceFiles() as $path) {
        $contents = file_get_contents($path);

        foreach ($legacyUsedKeys as $legacyPhraseKey) {
            if (str_contains($contents, "__('".$legacyPhraseKey."'")
                || str_contains($contents, '__("'.$legacyPhraseKey.'"')
                || str_contains($contents, "@lang('".$legacyPhraseKey."'")
                || str_contains($contents, '@lang("'.$legacyPhraseKey.'"')
                || str_contains($contents, "trans('".$legacyPhraseKey."'")
                || str_contains($contents, 'trans("'.$legacyPhraseKey.'"')) {
                $offenders[] = $path.' uses '.$legacyPhraseKey;
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBe([]);
});

/**
 * @return array<string, string>
 */
function qrTranslationLangFiles(): array
{
    return [
        'en' => qrTranslationProjectPath('lang/en.json'),
        'lt' => qrTranslationProjectPath('lang/lt.json'),
        'ru' => qrTranslationProjectPath('lang/ru.json'),
    ];
}

/**
 * @return list<string>
 */
function qrTranslationRequiredKeys(): array
{
    return [
        'qr.errors.not_found.title',
        'qr.errors.not_found.description',
        'qr.errors.disabled.title',
        'qr.errors.disabled.description',
        'qr.errors.revoked.title',
        'qr.errors.revoked.description',
        'qr.errors.service_point_unavailable.title',
        'qr.errors.service_point_unavailable.description',
        'qr.actions.generate',
        'qr.actions.print',
        'qr.actions.download',
        'qr.actions.disable',
        'qr.actions.reissue',
        'qr.labels.short_code',
        'qr.labels.public_url',
        'qr.labels.status',
        'qr.status.active',
        'qr.status.disabled',
        'qr.status.revoked',
        'qr.confirmations.reissue.title',
        'qr.confirmations.reissue.description',
        'qr.confirmations.reissue.confirmation_help',
        'qr.confirmations.disable.title',
        'qr.confirmations.disable.description',
        'qr.labels.current_short_code',
        'qr.labels.disable_reason',
        'qr.placeholders.disable_reason',
        'qr.validation.disable_reason_required',
        'qr.validation.disable_reason_min',
        'qr.validation.reissue_confirmation_required',
        'qr.validation.reissue_confirmation_mismatch',
    ];
}

/**
 * @return list<string>
 */
function qrTranslationLegacyPhraseKeys(): array
{
    return [
        'QR code not found',
        'Please ask the staff for a fresh QR code.',
        'QR code is temporarily disabled',
        'Please ask the staff to help you with this place.',
        'QR code is no longer active',
        'This QR code has been replaced. Please ask the staff for the current code.',
        'This place is temporarily unavailable',
        'Please ask the staff before ordering from this place.',
    ];
}

/**
 * @return list<string>
 */
function qrTranslationLegacyUsedKeys(): array
{
    return [
        'guest.table.qr_not_found_title',
        'guest.table.qr_not_found_message',
        'guest.table.qr_disabled_title',
        'guest.table.qr_disabled_message',
        'guest.table.qr_revoked_title',
        'guest.table.qr_revoked_message',
        'guest.table.place_unavailable_title',
        'guest.table.place_unavailable_message',
        'ui.confirmations.reissue_qr.title',
        'ui.confirmations.reissue_qr.description',
        'ui.confirmations.reissue_qr.confirmation_label',
        'ui.confirmations.reissue_qr.confirmation_help',
        'ui.confirmations.reissue_qr.confirmation_required',
        'ui.confirmations.reissue_qr.confirmation_match',
        'ui.confirmations.disable.title',
        'ui.confirmations.disable.description',
        'ui.confirmations.reason.required',
        'ui.confirmations.reason.min',
    ];
}

/**
 * @return list<string>
 */
function qrTranslationSourceFiles(): array
{
    return [
        qrTranslationProjectPath('app/Livewire/PublicQr/Show.php'),
        qrTranslationProjectPath('app/Livewire/QrCodes/ShortCodeLookup.php'),
        qrTranslationProjectPath('app/Livewire/Organizations/Brands/Branches/Qr/BulkPrint.php'),
        qrTranslationProjectPath('app/Livewire/Organizations/Brands/Branches/ServicePoints/Qr/Show.php'),
        qrTranslationProjectPath('app/Livewire/Organizations/Brands/Branches/ServicePoints/Qr/PrintTemplate.php'),
        qrTranslationProjectPath('resources/views/livewire/qr-codes/short-code-lookup.blade.php'),
        qrTranslationProjectPath('resources/views/livewire/organizations/brands/branches/qr/bulk-print.blade.php'),
        qrTranslationProjectPath('resources/views/livewire/organizations/brands/branches/service-points/qr/show.blade.php'),
        qrTranslationProjectPath('resources/views/livewire/organizations/brands/branches/service-points/qr/print-template.blade.php'),
    ];
}

function qrTranslationProjectPath(string $path): string
{
    return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path;
}
