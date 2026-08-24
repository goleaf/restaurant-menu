<?php

test('interface translation json files exist, are flat, and share keys', function () {
    $translations = collect(translationStandardFiles())
        ->mapWithKeys(fn (string $path, string $locale): array => [$locale => translationStandardDecode($path)]);

    $translations->each(function (array $lines, string $locale): void {
        expect(file_exists(translationStandardFiles()[$locale]))->toBeTrue()
            ->and($lines)->toBeArray();

        foreach ($lines as $key => $value) {
            expect($key)->toBeString()
                ->and($value)->toBeString();
        }
    });

    $keySets = $translations
        ->map(fn (array $lines): array => translationStandardSortedKeys($lines));

    expect($keySets['lt'])->toBe($keySets['en'])
        ->and($keySets['ru'])->toBe($keySets['en']);
});

test('new interface translation keys use semantic dotted names', function () {
    $legacyKeys = translationStandardLegacyPhraseKeys();

    foreach (translationStandardFiles() as $path) {
        $nonSemanticKeys = collect(array_keys(translationStandardDecode($path)))
            ->reject(fn (string $key): bool => preg_match(translationStandardSemanticKeyPattern(), $key) === 1)
            ->sort()
            ->values()
            ->all();

        expect($nonSemanticKeys)->toBe($legacyKeys);
    }
});

test('translation key namespace map defines required namespaces', function () {
    $mappedNamespaces = translationStandardMappedNamespaces();

    expect($mappedNamespaces)->toBe(translationStandardRequiredNamespaces());
});

test('translation values are non-empty and preserve placeholders and plural contracts', function () {
    $translations = collect(translationStandardFiles())
        ->mapWithKeys(fn (string $path, string $locale): array => [$locale => translationStandardDecode($path)]);

    foreach ($translations['en'] as $key => $englishValue) {
        $placeholderSets = $translations
            ->map(fn (array $lines): array => translationStandardPlaceholders($lines[$key]));
        $pluralUsage = $translations
            ->map(fn (array $lines): bool => str_contains($lines[$key], '|'));

        expect(trim($englishValue))->not->toBe('')
            ->and(trim($translations['lt'][$key]))->not->toBe('')
            ->and(trim($translations['ru'][$key]))->not->toBe('')
            ->and($placeholderSets['lt'])->toBe($placeholderSets['en'], "Lithuanian placeholders differ for [$key].")
            ->and($placeholderSets['ru'])->toBe($placeholderSets['en'], "Russian placeholders differ for [$key].")
            ->and($pluralUsage['lt'])->toBe($pluralUsage['en'], "Lithuanian plural structure differs for [$key].")
            ->and($pluralUsage['ru'])->toBe($pluralUsage['en'], "Russian plural structure differs for [$key].");
    }
});

test('localized catalogs do not silently retain english prose or foreign-script copy', function () {
    $translations = collect(translationStandardFiles())
        ->mapWithKeys(fn (string $path, string $locale): array => [$locale => translationStandardDecode($path)]);

    foreach (translationStandardEnglishIdentityAllowlist() as $locale => $allowedKeys) {
        $identicalKeys = collect($translations['en'])
            ->filter(fn (string $value, string $key): bool => $translations[$locale][$key] === $value)
            ->keys()
            ->sort()
            ->values()
            ->all();

        sort($allowedKeys);

        expect($identicalKeys)->toBe($allowedKeys, "Unexpected English fallback values found in [$locale].");
    }

    expect(implode("\n", $translations['en']))->not->toMatch('/[А-Яа-яЁё]/u')
        ->and(implode("\n", $translations['lt']))->not->toMatch('/[А-Яа-яЁё]/u');
});

/**
 * Values that are language-neutral examples, symbols, units, product names, or interpolation-only templates.
 *
 * @return array<string, list<string>>
 */
function translationStandardEnglishIdentityAllowlist(): array
{
    $shared = [
        'fields.placeholders.branch_email_example',
        'fields.placeholders.email_example',
        'fields.placeholders.phone_example',
        'fields.placeholders.service_point_prefix_example',
        'fields.placeholders.website_url_example',
        'guest.cart.separator',
        'menu.guest.unit_grams',
        'menu.guest.unit_liters',
        'menu.modifiers.price_delta',
        'permissions.groups.qr',
        'qr.labels.qr',
        'qr.placeholders.short_code_example',
        'ui.actions.draftorders.addguestdraftorderitemaction.message',
        'ui.actions.waiter.buildwaitertabledetailaction.message',
        'ui.livewire.organizations.brands.branches.areas.vip',
        'ui.onboarding.restaurant_setup.brand_name_placeholder',
        'ui.onboarding.restaurant_setup.organization_name_placeholder',
    ];

    return [
        'lt' => [
            ...$shared,
            'menu.dietary_labels.options.halal',
            'menu.guest.unit_kcal',
            'qr.print.presets.premium.label',
        ],
        'ru' => [
            ...$shared,
            'fields.placeholders.facebook_url_example',
            'fields.placeholders.instagram_url_example',
            'fields.placeholders.tiktok_url_example',
        ],
    ];
}

/**
 * @return array<string, string>
 */
function translationStandardFiles(): array
{
    return [
        'en' => translationStandardProjectPath('lang/en.json'),
        'lt' => translationStandardProjectPath('lang/lt.json'),
        'ru' => translationStandardProjectPath('lang/ru.json'),
    ];
}

/**
 * @return array<string, string>
 */
function translationStandardDecode(string $path): array
{
    expect(file_exists($path))->toBeTrue();

    $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray();

    return $decoded;
}

/**
 * @param  array<string, string>  $lines
 * @return list<string>
 */
function translationStandardSortedKeys(array $lines): array
{
    $keys = array_keys($lines);
    sort($keys);

    return $keys;
}

/**
 * @return list<string>
 */
function translationStandardLegacyPhraseKeys(): array
{
    $standard = file_get_contents(translationStandardProjectPath('docs/TRANSLATION_STANDARD.md'));

    preg_match(
        '/<!-- legacy-translation-keys:start -->(.*?)<!-- legacy-translation-keys:end -->/s',
        $standard,
        $matches,
    );

    expect($matches[1] ?? null)->toBeString();

    preg_match_all('/^- `(.+)`$/m', $matches[1], $legacyMatches);

    $keys = $legacyMatches[1];
    sort($keys);

    return array_values($keys);
}

function translationStandardSemanticKeyPattern(): string
{
    return '/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+\z/';
}

function translationStandardProjectPath(string $path): string
{
    return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path;
}

/**
 * @return list<string>
 */
function translationStandardPlaceholders(string $value): array
{
    preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);

    $placeholders = array_values(array_unique($matches[0]));
    sort($placeholders);

    return $placeholders;
}

/**
 * @return list<string>
 */
function translationStandardMappedNamespaces(): array
{
    $map = file_get_contents(translationStandardProjectPath('docs/TRANSLATION_KEY_MAP.md'));

    preg_match(
        '/<!-- translation-key-namespaces:start -->(.*?)<!-- translation-key-namespaces:end -->/s',
        $map,
        $matches,
    );

    expect($matches[1] ?? null)->toBeString();

    preg_match_all('/^- `([a-z_]+(?:\.[a-z_]+)?\.\*)`/m', $matches[1], $namespaceMatches);

    return $namespaceMatches[1];
}

/**
 * @return list<string>
 */
function translationStandardRequiredNamespaces(): array
{
    return [
        'ui.*',
        'ui.confirmations.*',
        'auth.*',
        'navigation.*',
        'organizations.*',
        'brands.*',
        'branches.*',
        'areas.*',
        'service_points.*',
        'qr.*',
        'guest.*',
        'menu.*',
        'waiter.*',
        'departments.*',
        'orders.*',
        'payments.*',
        'reports.*',
        'staff.*',
        'permissions.*',
        'statuses.*',
        'fields.*',
        'validation.*',
        'errors.*',
        'notifications.*',
        'activity.*',
        'superadmin.*',
    ];
}
