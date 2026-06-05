<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use SplFileInfo;

#[Signature('translations:audit
    {--lang-dir= : Directory containing en.json, lt.json, and ru.json}
    {--scan-dir=* : Directory or file to scan for phrase-style translation calls}
    {--no-code-scan : Skip scanning Blade and PHP files for phrase-style translation calls}')]
#[Description('Audit flat JSON translations and phrase-style translation calls.')]
class AuditTranslationsCommand extends Command
{
    private const KEY_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+\z/';

    private const LOCALES = ['en', 'lt', 'ru'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $report = $this->buildReport();

        $this->renderReport($report);

        return $report['critical_issues'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{
     *     lang_dir: string,
     *     files_checked: int,
     *     total_keys: int,
     *     semantic_keys: int,
     *     scanned_files: int,
     *     critical_issues: int,
     *     missing_files: list<string>,
     *     invalid_json_files: list<string>,
     *     nested_values: list<string>,
     *     non_string_values: list<string>,
     *     bad_keys: list<string>,
     *     missing_keys: list<string>,
     *     empty_values: list<string>,
     *     phrase_calls: list<string>
     * }
     */
    private function buildReport(): array
    {
        $langDir = $this->langDirectory();
        $translations = [];
        $validLocaleKeys = [];
        $missingFiles = [];
        $invalidJsonFiles = [];
        $nestedValues = [];
        $nonStringValues = [];
        $badKeys = [];
        $emptyValues = [];
        $totalKeys = 0;
        $semanticKeys = 0;

        foreach (self::LOCALES as $locale) {
            $path = $langDir.'/'.$locale.'.json';
            $relativePath = $this->relativePath($path);

            if (! File::exists($path)) {
                $missingFiles[] = $relativePath;

                continue;
            }

            $decoded = $this->decodeJsonFile($path, $invalidJsonFiles);

            if (! is_array($decoded) || array_is_list($decoded)) {
                $invalidJsonFiles[] = $relativePath.' must contain a flat JSON object.';

                continue;
            }

            $translations[$locale] = $decoded;
            $validLocaleKeys[$locale] = array_keys($decoded);

            foreach ($decoded as $key => $value) {
                $totalKeys++;

                if (preg_match(self::KEY_PATTERN, (string) $key) === 1) {
                    $semanticKeys++;
                }

                $keyReasons = $this->badKeyReasons((string) $key);

                if ($keyReasons !== []) {
                    $badKeys[] = sprintf('%s: %s (%s)', $relativePath, $key, implode('; ', $keyReasons));
                }

                if (is_array($value)) {
                    $nestedValues[] = sprintf('%s: %s contains a nested JSON value.', $relativePath, $key);

                    continue;
                }

                if (! is_string($value)) {
                    $nonStringValues[] = sprintf('%s: %s must have a string value.', $relativePath, $key);

                    continue;
                }

                $emptyReason = $this->emptyValueReason($value);

                if ($emptyReason !== null) {
                    $emptyValues[] = sprintf('%s: %s (%s)', $relativePath, $key, $emptyReason);
                }
            }
        }

        $missingKeys = $this->missingKeys($validLocaleKeys);
        $codeScan = $this->option('no-code-scan')
            ? ['files' => 0, 'findings' => []]
            : $this->scanTranslationCalls($this->scanPaths());

        $criticalIssues = count($missingFiles)
            + count($invalidJsonFiles)
            + count($nestedValues)
            + count($nonStringValues)
            + count($badKeys)
            + count($missingKeys)
            + count($emptyValues)
            + count($codeScan['findings']);

        return [
            'lang_dir' => $langDir,
            'files_checked' => count($translations),
            'total_keys' => $totalKeys,
            'semantic_keys' => $semanticKeys,
            'scanned_files' => $codeScan['files'],
            'critical_issues' => $criticalIssues,
            'missing_files' => $missingFiles,
            'invalid_json_files' => $invalidJsonFiles,
            'nested_values' => $nestedValues,
            'non_string_values' => $nonStringValues,
            'bad_keys' => $badKeys,
            'missing_keys' => $missingKeys,
            'empty_values' => $emptyValues,
            'phrase_calls' => $codeScan['findings'],
        ];
    }

    /**
     * @param  list<string>  $invalidJsonFiles
     * @return array<string, mixed>|null
     */
    private function decodeJsonFile(string $path, array &$invalidJsonFiles): ?array
    {
        try {
            $decoded = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $invalidJsonFiles[] = sprintf(
                '%s is invalid JSON: %s',
                $this->relativePath($path),
                $exception->getMessage(),
            );

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, list<string>>  $validLocaleKeys
     * @return list<string>
     */
    private function missingKeys(array $validLocaleKeys): array
    {
        $allKeys = [];

        foreach ($validLocaleKeys as $keys) {
            $allKeys = array_merge($allKeys, $keys);
        }

        $allKeys = array_values(array_unique($allKeys));
        sort($allKeys);

        $missing = [];

        foreach ($allKeys as $key) {
            $presentLocales = [];
            $missingLocales = [];

            foreach ($validLocaleKeys as $locale => $keys) {
                if (in_array($key, $keys, true)) {
                    $presentLocales[] = $locale;

                    continue;
                }

                $missingLocales[] = $locale;
            }

            foreach ($missingLocales as $locale) {
                $missing[] = sprintf('%s missing %s (present in %s)', $locale, $key, implode(', ', $presentLocales));
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function badKeyReasons(string $key): array
    {
        $reasons = [];

        if (str_contains($key, ' ')) {
            $reasons[] = 'contains spaces';
        }

        if (preg_match('/\p{Cyrillic}/u', $key) === 1) {
            $reasons[] = 'contains Cyrillic letters';
        }

        if (preg_match('/\A[A-Z][A-Za-z]*(?:\s|$)/u', $key) === 1) {
            $reasons[] = 'starts with uppercase text';
        }

        if (preg_match('/[?!,;:]|\.\z/u', $key) === 1) {
            $reasons[] = 'contains sentence punctuation';
        }

        if (mb_strlen($key) > 80) {
            $reasons[] = 'is very long';
        }

        if (! str_contains($key, '.')) {
            $reasons[] = 'does not contain a namespace dot';
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            $reasons[] = 'does not match semantic dotted key pattern';
        }

        return array_values(array_unique($reasons));
    }

    private function emptyValueReason(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return 'empty value';
        }

        if (preg_match('/\A(?:todo|tbd|fixme|placeholder|xxx)(?:\z|[\s:_-])/i', $trimmed) === 1) {
            return 'placeholder value';
        }

        if (in_array(strtolower($trimmed), ['...', '-', '--', 'n/a', 'missing', 'translate me'], true)) {
            return 'placeholder value';
        }

        return null;
    }

    /**
     * @param  list<string>  $paths
     * @return array{files: int, findings: list<string>}
     */
    private function scanTranslationCalls(array $paths): array
    {
        $findings = [];
        $scannedFiles = 0;

        foreach ($this->scanFiles($paths) as $file) {
            $scannedFiles++;
            $contents = File::get($file->getPathname());

            preg_match_all(
                '/(?:__|trans|trans_choice|@lang)\(\s*([\'"])(.*?)\1/su',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[2] as [$key, $offset]) {
                if ($this->badKeyReasons($key) === []) {
                    continue;
                }

                $findings[] = sprintf(
                    '%s:%d uses phrase-style translation key "%s"',
                    $this->relativePath($file->getPathname()),
                    substr_count(substr($contents, 0, $offset), "\n") + 1,
                    $key,
                );
            }
        }

        return ['files' => $scannedFiles, 'findings' => $findings];
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

        return $files;
    }

    private function isScannableFile(SplFileInfo $file): bool
    {
        $path = $file->getPathname();

        return str_ends_with($path, '.php') || str_ends_with($path, '.blade.php');
    }

    /**
     * @return list<string>
     */
    private function scanPaths(): array
    {
        $option = $this->option('scan-dir');

        if (is_array($option) && $option !== []) {
            return array_values(array_filter($option, fn (string $path): bool => $path !== ''));
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
     *     lang_dir: string,
     *     files_checked: int,
     *     total_keys: int,
     *     semantic_keys: int,
     *     scanned_files: int,
     *     critical_issues: int,
     *     missing_files: list<string>,
     *     invalid_json_files: list<string>,
     *     nested_values: list<string>,
     *     non_string_values: list<string>,
     *     bad_keys: list<string>,
     *     missing_keys: list<string>,
     *     empty_values: list<string>,
     *     phrase_calls: list<string>
     * }  $report
     */
    private function renderReport(array $report): void
    {
        $this->line('Translation audit report');
        $this->line('Language directory: '.$this->relativePath($report['lang_dir']));
        $this->line(sprintf(
            'JSON files: checked %d, missing %d, invalid %d',
            $report['files_checked'],
            count($report['missing_files']),
            count($report['invalid_json_files']),
        ));
        $this->line(sprintf(
            'Keys: total %d, semantic %d, bad %d, missing links %d',
            $report['total_keys'],
            $report['semantic_keys'],
            count($report['bad_keys']),
            count($report['missing_keys']),
        ));
        $this->line(sprintf(
            'Values: nested %d, non-string %d, empty/placeholders %d',
            count($report['nested_values']),
            count($report['non_string_values']),
            count($report['empty_values']),
        ));
        $this->line(sprintf(
            'Code scan: files %d, phrase-style calls %d',
            $report['scanned_files'],
            count($report['phrase_calls']),
        ));
        $this->line('Critical issues: '.$report['critical_issues']);

        $this->renderSection('Missing files', $report['missing_files']);
        $this->renderSection('Invalid JSON files', $report['invalid_json_files']);
        $this->renderSection('Nested JSON values', $report['nested_values']);
        $this->renderSection('Non-string values', $report['non_string_values']);
        $this->renderSection('Bad keys', $report['bad_keys']);
        $this->renderSection('Missing keys', $report['missing_keys']);
        $this->renderSection('Empty or placeholder values', $report['empty_values']);
        $this->renderSection('Potential phrase-style translation calls', $report['phrase_calls']);
    }

    /**
     * @param  list<string>  $items
     */
    private function renderSection(string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->newLine();
        $this->line($title);

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
