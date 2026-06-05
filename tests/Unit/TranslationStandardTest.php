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
