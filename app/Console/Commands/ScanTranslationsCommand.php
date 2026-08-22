<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use SplFileInfo;

#[Signature('translations:scan
    {--lang-dir= : Directory containing en.json, lt.json, and ru.json}
    {--scan-dir=* : Directory or file to scan for translation key usage}
    {--json : Output the scan report as JSON}')]
#[Description('Extract translation key usage and compare it to flat JSON translation files.')]
class ScanTranslationsCommand extends Command
{
    private const KEY_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+\z/';

    private const LOCALES = ['en', 'lt', 'ru'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $report = $this->buildReport();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->renderReport($report);

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     counts: array<string, int>,
     *     used_keys: list<string>,
     *     semantic_used_keys: list<string>,
     *     phrase_used_keys: list<string>,
     *     missing_keys: list<string>,
     *     unused_json_keys: list<string>,
     *     legacy_phrase_keys: list<string>,
     *     missing_by_locale: array<string, list<string>>,
     *     usages: array<string, list<string>>,
     *     lang_files: array<string, string>
     * }
     */
    private function buildReport(): array
    {
        $jsonKeysByLocale = $this->jsonKeysByLocale($this->langDirectory());
        $allJsonKeys = $this->uniqueSorted(array_merge(...array_values($jsonKeysByLocale)));
        $scanFiles = $this->scanFiles($this->scanPaths());
        $usages = $this->extractUsages($scanFiles);
        $usedKeys = $this->uniqueSorted(array_keys($usages));
        $semanticUsedKeys = $this->filterSemanticKeys($usedKeys);
        $phraseUsedKeys = $this->rejectSemanticKeys($usedKeys);
        $missingKeys = $this->usedKeysMissingFromAnyLocale($usedKeys, $jsonKeysByLocale);
        $unusedJsonKeys = $this->uniqueSorted(array_diff($allJsonKeys, $usedKeys));
        $legacyPhraseKeys = $this->uniqueSorted(array_merge(
            $this->rejectSemanticKeys($allJsonKeys),
            $phraseUsedKeys,
        ));
        $missingByLocale = $this->missingByLocale($usedKeys, $jsonKeysByLocale);

        return [
            'counts' => [
                'files_scanned' => count($scanFiles),
                'used_keys' => count($usedKeys),
                'semantic_used_keys' => count($semanticUsedKeys),
                'phrase_used_keys' => count($phraseUsedKeys),
                'json_keys' => count($allJsonKeys),
                'missing_keys' => count($missingKeys),
                'unused_json_keys' => count($unusedJsonKeys),
                'legacy_phrase_keys' => count($legacyPhraseKeys),
            ],
            'used_keys' => $usedKeys,
            'semantic_used_keys' => $semanticUsedKeys,
            'phrase_used_keys' => $phraseUsedKeys,
            'missing_keys' => $missingKeys,
            'unused_json_keys' => $unusedJsonKeys,
            'legacy_phrase_keys' => $legacyPhraseKeys,
            'missing_by_locale' => $missingByLocale,
            'usages' => $usages,
            'lang_files' => collect(self::LOCALES)
                ->mapWithKeys(fn (string $locale): array => [$locale => $this->langDirectory().'/'.$locale.'.json'])
                ->all(),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function jsonKeysByLocale(string $langDir): array
    {
        $keysByLocale = [];

        foreach (self::LOCALES as $locale) {
            $path = $langDir.'/'.$locale.'.json';

            if (! File::exists($path)) {
                $keysByLocale[$locale] = [];

                continue;
            }

            try {
                $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $keysByLocale[$locale] = [];

                continue;
            }

            if (! is_array($decoded) || array_is_list($decoded)) {
                $keysByLocale[$locale] = [];

                continue;
            }

            $keysByLocale[$locale] = $this->uniqueSorted(array_keys($decoded));
        }

        return $keysByLocale;
    }

    /**
     * @param  list<SplFileInfo>  $files
     * @return array<string, list<string>>
     */
    private function extractUsages(array $files): array
    {
        $usages = [];

        foreach ($files as $file) {
            $contents = File::get($file->getPathname());

            preg_match_all(
                '/(?:__|trans|trans_choice|@lang)\(\s*([\'"])(.*?)\1/su',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[2] as [$key, $offset]) {
                $usages[$key][] = sprintf(
                    '%s:%d',
                    $this->relativePath($file->getPathname()),
                    substr_count(substr($contents, 0, $offset), "\n") + 1,
                );
            }
        }

        ksort($usages);

        foreach ($usages as $key => $locations) {
            $usages[$key] = $this->uniqueSorted($locations);
        }

        return $usages;
    }

    /**
     * @param  list<string>  $paths
     * @return list<SplFileInfo>
     */
    private function scanFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            if (File::isFile($path)) {
                $file = new SplFileInfo($path);

                if ($this->isScannableFile($file)) {
                    $files[] = $file;
                }

                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($this->isScannableFile($file)) {
                    $files[] = $file;
                }
            }
        }

        usort(
            $files,
            fn (SplFileInfo $left, SplFileInfo $right): int => strcmp($left->getPathname(), $right->getPathname()),
        );

        return $files;
    }

    private function isScannableFile(SplFileInfo $file): bool
    {
        $path = $file->getPathname();

        return str_ends_with($path, '.php') || str_ends_with($path, '.blade.php');
    }

    /**
     * @param  list<string>  $usedKeys
     * @param  array<string, list<string>>  $jsonKeysByLocale
     * @return list<string>
     */
    private function usedKeysMissingFromAnyLocale(array $usedKeys, array $jsonKeysByLocale): array
    {
        return collect($usedKeys)
            ->filter(function (string $key) use ($jsonKeysByLocale): bool {
                foreach ($jsonKeysByLocale as $jsonKeys) {
                    if (! in_array($key, $jsonKeys, true)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $usedKeys
     * @param  array<string, list<string>>  $jsonKeysByLocale
     * @return array<string, list<string>>
     */
    private function missingByLocale(array $usedKeys, array $jsonKeysByLocale): array
    {
        $missing = [];

        foreach ($jsonKeysByLocale as $locale => $jsonKeys) {
            $missing[$locale] = $this->uniqueSorted(array_diff($usedKeys, $jsonKeys));
        }

        return $missing;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function filterSemanticKeys(array $keys): array
    {
        return array_values(array_filter(
            $keys,
            fn (string $key): bool => preg_match(self::KEY_PATTERN, $key) === 1,
        ));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function rejectSemanticKeys(array $keys): array
    {
        return array_values(array_filter(
            $keys,
            fn (string $key): bool => preg_match(self::KEY_PATTERN, $key) !== 1,
        ));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueSorted(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @return list<string>
     */
    private function scanPaths(): array
    {
        $option = $this->option('scan-dir');

        if ($option !== []) {
            return array_values(array_filter($option, fn (?string $path): bool => is_string($path) && $path !== ''));
        }

        return [
            app_path(),
            resource_path('views'),
            base_path('routes'),
        ];
    }

    private function langDirectory(): string
    {
        $option = $this->option('lang-dir');

        return is_string($option) && $option !== '' ? rtrim($option, '/\\') : base_path('lang');
    }

    /**
     * @param  array{
     *     counts: array<string, int>,
     *     used_keys: list<string>,
     *     semantic_used_keys: list<string>,
     *     phrase_used_keys: list<string>,
     *     missing_keys: list<string>,
     *     unused_json_keys: list<string>,
     *     legacy_phrase_keys: list<string>,
     *     missing_by_locale: array<string, list<string>>,
     *     usages: array<string, list<string>>,
     *     lang_files: array<string, string>
     * }  $report
     */
    private function renderReport(array $report): void
    {
        $this->line('Translation key scan report');
        $this->line(sprintf(
            'Files scanned: %d',
            $report['counts']['files_scanned'],
        ));
        $this->line(sprintf(
            'Used keys: %d total, %d semantic, %d phrase-style',
            $report['counts']['used_keys'],
            $report['counts']['semantic_used_keys'],
            $report['counts']['phrase_used_keys'],
        ));
        $this->line(sprintf(
            'JSON keys: %d total, %d unused',
            $report['counts']['json_keys'],
            $report['counts']['unused_json_keys'],
        ));
        $this->line(sprintf(
            'Missing keys: %d',
            $report['counts']['missing_keys'],
        ));
        $this->line(sprintf(
            'Legacy phrase keys: %d',
            $report['counts']['legacy_phrase_keys'],
        ));

        $this->renderList('Used keys', $report['used_keys']);
        $this->renderList('Missing keys', $report['missing_keys']);
        $this->renderList('Unused JSON keys', $report['unused_json_keys']);
        $this->renderList('Legacy phrase keys', $report['legacy_phrase_keys']);
    }

    /**
     * @param  list<string>  $items
     */
    private function renderList(string $title, array $items): void
    {
        $this->newLine();
        $this->line($title);

        if ($items === []) {
            $this->line(' - none');

            return;
        }

        foreach ($items as $item) {
            $this->line(' - '.$item);
        }
    }

    private function relativePath(string $path): string
    {
        $basePath = rtrim(base_path(), '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with($path, $basePath)
            ? substr($path, strlen($basePath))
            : $path;
    }
}
