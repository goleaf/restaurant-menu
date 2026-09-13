<?php

declare(strict_types=1);

use App\Actions\Departments\BuildDepartmentTicketDelayTimerAction;
use Carbon\CarbonImmutable;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('ticket delay states preserve exact threshold boundaries', function (?int $ageSeconds, string $state, string $elapsedLabel, ?string $delayLabel): void {
    $referenceTime = CarbonImmutable::parse('2026-09-14 12:00:00', 'UTC');
    $startedAt = $ageSeconds === null ? null : $referenceTime->subSeconds($ageSeconds);
    $timer = (new BuildDepartmentTicketDelayTimerAction)->handle($startedAt, $referenceTime);

    expect($timer['delay_state'])->toBe($state)
        ->and($timer['elapsed_label'])->toBe($elapsedLabel)
        ->and($timer['delay_label'])->toBe($delayLabel);
})->with([
    'missing start' => [null, 'on-track', '00:00', null],
    'future start' => [-1, 'on-track', '00:00', null],
    'just started' => [0, 'on-track', '00:00', null],
    'before attention' => [599, 'on-track', '09:59', null],
    'attention threshold' => [600, 'attention', '10:00', null],
    'before delay' => [899, 'attention', '14:59', null],
    'delay threshold' => [900, 'delayed', '15:00', '00:00'],
    'after delay' => [901, 'delayed', '15:01', '00:01'],
    'over one hour' => [3601, 'delayed', '1:00:01', '45:01'],
]);
