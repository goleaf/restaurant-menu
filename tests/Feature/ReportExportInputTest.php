<?php

use App\Livewire\Forms\Exports\ReportDownloadForm;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

test('report input validates exactly its query source and ignores body context', function (): void {
    $request = reportInputForm(['date_from' => '2026-03-29', 'date_to' => '2026-03-29'], [
        'date_from' => 'bad', 'date_to' => 'bad', 'branch_id' => 999,
    ]);
    $request->period('Europe/Vilnius');

    expect($request->all())->toBe(['date_from' => '2026-03-29', 'date_to' => '2026-03-29'])
        ->and($request->period('Europe/Vilnius')->startedAt->toIso8601String())->toBe('2026-03-28T22:00:00+00:00')
        ->and($request->period('Europe/Vilnius')->endedAt->toIso8601String())->toBe('2026-03-29T20:59:59+00:00');
});

test('report input rejects malformed query even with valid body', function (mixed $value): void {
    $request = reportInputForm(['date_from' => $value], ['date_from' => '2026-01-01']);
    try {
        $request->period('Europe/Vilnius');
        $this->fail('Invalid query was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('period.date_from');
    }
})->with(['text' => 'bad', 'impossible date' => '2026-02-30', 'array' => [['date' => '2026-01-01']], 'zero' => 0, 'false' => false]);

test('report period errors have localized labels and units', function (string $locale): void {
    app()->setLocale($locale);
    foreach ([['2026-06-02', '2026-06-01', 'validation.rules.report_period_order'], ['2026-03-01', '2026-04-01', 'validation.rules.report_period_too_long']] as [$from, $to, $key]) {
        $request = reportInputForm(['date_from' => $from, 'date_to' => $to]);
        try {
            $request->period('Europe/Vilnius');
            $this->fail('Invalid period was accepted.');
        } catch (ValidationException $exception) {
            $expected = __($key, ['attribute' => __('validation.attributes.date_to'), 'other' => __('validation.attributes.date_from'), 'max' => 31]);
            expect($exception->errors())->toBe(['period.date_to' => [$expected]])
                ->and($expected)->not->toBe($key)->not->toContain(':attribute', ':other', ':max', 'date to', 'date from');
        }
    }
})->with(['en', 'lt', 'ru']);

test('report optional dates resolve once in the trusted branch calendar', function (array $query, string $from, string $to): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 22:30:00', 'UTC'));
    $request = reportInputForm($query);
    $request->period('Europe/Vilnius');
    $period = $request->period('Europe/Vilnius');
    expect($period->dateFrom)->toBe($from)->and($period->dateTo)->toBe($to)
        ->and($period->timezone)->toBe('Europe/Vilnius');
})->with([
    [[], '2026-05-03', '2026-06-02'],
    [['date_from' => null, 'date_to' => ''], '2026-05-03', '2026-06-02'],
    [['date_from' => ' 2026-05-01 '], '2026-05-01', '2026-05-31'],
    [['date_to' => '2026-05-31'], '2026-05-01', '2026-05-31'],
    [['date_from' => '2026-10-01', 'date_to' => '2026-10-31'], '2026-10-01', '2026-10-31'],
]);

function reportInputForm(array $query, array $body = []): ReportDownloadForm
{
    $request = Request::create('/exports?'.http_build_query($query), 'GET');
    $request->query->replace($query);
    $request->request->replace($body);
    $form = new ReportDownloadForm(new class extends Component {}, 'period');
    $form->fillFromQuery($request);

    return $form;
}
