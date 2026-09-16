<?php

use App\Http\Requests\Restaurant\DownloadBranchReportRequest;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

test('report input validates exactly its query source and ignores body context', function (): void {
    $request = reportInputRequest(['date_from' => '2026-03-29', 'date_to' => '2026-03-29'], [
        'date_from' => 'bad', 'date_to' => 'bad', 'branch_id' => 999,
    ]);
    $request->validateResolved();

    expect($request->validated())->toBe(['date_from' => '2026-03-29', 'date_to' => '2026-03-29'])
        ->and($request->period()->startedAt->toIso8601String())->toBe('2026-03-28T22:00:00+00:00')
        ->and($request->period()->endedAt->toIso8601String())->toBe('2026-03-29T20:59:59+00:00');
});

test('report input rejects malformed query even with valid body', function (mixed $value): void {
    $request = reportInputRequest(['date_from' => $value], ['date_from' => '2026-01-01']);
    try {
        $request->validateResolved();
        $this->fail('Invalid query was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('date_from');
    }
})->with(['text' => 'bad', 'impossible date' => '2026-02-30', 'array' => [['date' => '2026-01-01']], 'zero' => 0, 'false' => false]);

test('report period errors have localized labels and units', function (string $locale): void {
    app()->setLocale($locale);
    foreach ([['2026-06-02', '2026-06-01', 'validation.rules.report_period_order'], ['2026-03-01', '2026-04-01', 'validation.rules.report_period_too_long']] as [$from, $to, $key]) {
        $request = reportInputRequest(['date_from' => $from, 'date_to' => $to]);
        try {
            $request->validateResolved();
            $this->fail('Invalid period was accepted.');
        } catch (ValidationException $exception) {
            $expected = __($key, ['attribute' => __('validation.attributes.date_to'), 'other' => __('validation.attributes.date_from'), 'max' => 31]);
            expect($exception->errors())->toBe(['date_to' => [$expected]])
                ->and($expected)->not->toBe($key)->not->toContain(':attribute', ':other', ':max', 'date to', 'date from');
        }
    }
})->with(['en', 'lt', 'ru']);

test('report optional dates resolve once in the trusted branch calendar', function (array $query, string $from, string $to): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 22:30:00', 'UTC'));
    $request = reportInputRequest($query);
    $request->validateResolved();
    $period = $request->period();
    expect($period->dateFrom)->toBe($from)->and($period->dateTo)->toBe($to)
        ->and($request->period())->toBe($period);
})->with([
    [[], '2026-05-03', '2026-06-02'],
    [['date_from' => null, 'date_to' => ''], '2026-05-03', '2026-06-02'],
    [['date_from' => ' 2026-05-01 '], '2026-05-01', '2026-05-31'],
    [['date_to' => '2026-05-31'], '2026-05-01', '2026-05-31'],
    [['date_from' => '2026-10-01', 'date_to' => '2026-10-31'], '2026-10-01', '2026-10-31'],
]);

function reportInputRequest(array $query, array $body = []): DownloadBranchReportRequest
{
    $branch = Branch::factory()->make(['id' => 12, 'timezone' => 'Europe/Vilnius']);
    $user = User::factory()->make();
    Gate::shouldReceive('forUser')->with($user)->andReturnSelf();
    Gate::shouldReceive('allows')->with('export', $branch)->andReturnTrue();
    $request = DownloadBranchReportRequest::create('/exports?'.http_build_query($query), 'GET');
    $request->query->replace($query);
    $request->request->replace($body);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $user);
    $route = new Route('GET', 'exports', fn () => null);
    $route->bind($request)->setParameter('branch', $branch);
    $request->setRouteResolver(fn () => $route);

    return $request;
}
