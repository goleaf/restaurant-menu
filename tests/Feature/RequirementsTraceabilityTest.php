<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('requirements traceability covers every canonical requirement with complete evidence', function (): void {
    $requirementsPath = base_path('docs/requirements.md');
    $traceabilityPath = base_path('docs/REQUIREMENTS_TRACEABILITY.md');

    expect(File::exists($traceabilityPath))->toBeTrue('docs/REQUIREMENTS_TRACEABILITY.md is missing.');

    $requirements = File::get($requirementsPath);
    $traceability = File::get($traceabilityPath);

    preg_match_all('/^\| `(?<id>[a-z0-9-]+)` \| (?<description>.*?) \|/m', $requirements, $requirementMatches, PREG_SET_ORDER);
    preg_match_all('/^\| `(?<id>[a-z0-9-]+)` \| (?<description>.*?) \| (?<source>.*?) \| (?<roles>.*?) \| (?<backend>.*?) \| (?<ui>.*?) \| (?<authorization>.*?) \| (?<tables>.*?) \| (?<tests>.*?) \| (?<status>.*?) \| (?<evidence>.*?) \|$/m', $traceability, $traceMatches, PREG_SET_ORDER);

    $canonical = collect($requirementMatches)
        ->mapWithKeys(fn (array $match): array => [$match['id'] => trim($match['description'])]);
    $traced = collect($traceMatches)
        ->mapWithKeys(fn (array $match): array => [$match['id'] => trim($match['description'])]);

    expect($canonical)->toHaveCount(51)
        ->and($traced->keys()->all())->toBe($canonical->keys()->all())
        ->and($traced->all())->toBe($canonical->all())
        ->and($traceMatches)->toHaveCount($canonical->count());

    foreach ($traceMatches as $trace) {
        expect(trim($trace['source']))->toMatch('/\[docs\/requirements\.md:\d+\]\(requirements\.md#L\d+\)/')
            ->and(trim($trace['roles']))->not->toBe('')
            ->and(trim($trace['backend']))->not->toBe('')
            ->and(trim($trace['ui']))->not->toBe('')
            ->and(trim($trace['authorization']))->not->toBe('')
            ->and(trim($trace['tables']))->not->toBe('')
            ->and(trim($trace['tests']))->toContain('tests/')
            ->and(trim($trace['status']))->toBeIn(['Готово', 'Не применимо — функции отключены конфигурацией'])
            ->and(trim($trace['evidence']))->not->toBe('')
            ->and(strtolower($trace['evidence']))->not->toMatch('/\b(?:todo|tbd|fixme)\b/');

        preg_match_all('/`(?<path>(?:app|bootstrap|config|database|lang|resources|routes|tests)\/[^`]+\.(?:php|blade\.php|json|css))`/', implode(' ', [
            $trace['backend'],
            $trace['ui'],
            $trace['authorization'],
            $trace['tests'],
            $trace['evidence'],
        ]), $pathMatches);

        expect($pathMatches['path'])->not->toBeEmpty();

        foreach (array_unique($pathMatches['path']) as $referencedPath) {
            expect(File::exists(base_path($referencedPath)))
                ->toBeTrue($trace['id'].' references missing path '.$referencedPath.'.');
        }
    }
});
