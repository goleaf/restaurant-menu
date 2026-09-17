<?php

declare(strict_types=1);

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Branches\SaveBranchScheduleExceptionsAction;
use App\Actions\Branches\UpdateBranchOpeningHoursAction;
use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\Menus\SaveMenuAvailabilityScheduleAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\OrganizationUserStatus;
use App\Models\AuditLog;
use App\Models\AvailabilityCommand;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Support\Availability\AvailabilityDependencyFingerprint;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Fictional temporal tests']);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($organization)->for($brand)->create(['timezone' => 'Europe/Vilnius']);
});

test('a retried duration pause does not extend its deadline and stale versions cannot overwrite it', function (): void {
    $this->travelTo(now()->setDate(2026, 6, 1)->setTime(9, 0));
    $action = app(UpdateBranchTemporaryClosureAction::class);
    $request = (string) Str::uuid();
    $first = $action->handle($this->actor, $this->branch, true, 'Maintenance', null, 0, 'Europe/Vilnius', $request, 30);
    $until = $first->getRawOriginal('temporary_closed_until');
    $this->travel(5)->minutes();
    $repeat = $action->handle($this->actor, $this->branch, true, 'Maintenance', null, 0, 'Europe/Vilnius', $request, 30);
    expect($repeat->getRawOriginal('temporary_closed_until'))->toBe($until)
        ->and($repeat->pause_version)->toBe(1)->and(AvailabilityCommand::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('branch_id', $this->branch->id)->where('new_values->scope', 'pause')->count())->toBe(1);
    expect(fn () => $action->handle($this->actor, $this->branch, false, null, null, 0, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(ValidationException::class);
});

test('local deadline gaps and stale timezones fail without changing branch access', function (): void {
    $this->travelTo(now()->setDate(2026, 1, 1)->setTime(9, 0));
    $action = app(UpdateBranchTemporaryClosureAction::class);
    foreach ([['2026-03-29T03:30', 'Europe/Vilnius'], ['2026-06-01T12:00', 'UTC']] as [$deadline, $timezone]) {
        expect(fn () => $action->handle($this->actor, $this->branch, true, 'Maintenance', $deadline, 0, $timezone, (string) Str::uuid()))->toThrow(ValidationException::class);
    }
    expect($this->branch->fresh()->pause_version)->toBe(0)->and(AvailabilityCommand::query()->count())->toBe(0);
});

test('menu weekly replacement supports overnight intervals and rejects cyclic overlap atomically', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $action = app(SaveMenuAvailabilityScheduleAction::class);
    $weekly = [['day_of_week' => 7, 'starts_at' => '22:00', 'ends_at' => '02:00']];
    $saved = $action->handle($this->actor, $this->branch, $menu, $weekly, false, 0, 'Europe/Vilnius', (string) Str::uuid());
    expect($saved->schedule_version)->toBe(1)->and($saved->availabilitySchedules()->count())->toBe(1);
    $overlapping = [...$weekly, ['day_of_week' => 1, 'starts_at' => '01:00', 'ends_at' => '03:00']];
    expect(fn () => $action->handle($this->actor, $this->branch, $menu, $overlapping, false, 1, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($menu->fresh()->schedule_version)->toBe(1)->and($menu->availabilitySchedules()->count())->toBe(1);
});

test('a closed menu schedule is distinct from an empty unrestricted schedule', function (): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $action = app(SaveMenuAvailabilityScheduleAction::class);
    $saved = $action->handle($this->actor, $this->branch, $menu, [], true, 0, 'Europe/Vilnius', (string) Str::uuid());
    expect($saved->schedule_is_closed)->toBeTrue();
    $saved = $action->handle($this->actor, $this->branch, $menu, [], false, 1, 'Europe/Vilnius', (string) Str::uuid());
    expect($saved->schedule_is_closed)->toBeFalse()->and($saved->schedule_version)->toBe(2);
});

test('date exceptions share the weekly schedule revision and preserve data on stale submissions', function (): void {
    $action = app(SaveBranchScheduleExceptionsAction::class);
    $exceptions = [['local_date' => '2026-12-25', 'is_closed' => true, 'intervals' => []]];
    $saved = $action->handle($this->actor, $this->branch, $exceptions, 0, 'Europe/Vilnius', (string) Str::uuid());
    expect($saved->opening_hours_version)->toBe(1)->and($saved->scheduleExceptions()->count())->toBe(1);
    expect(fn () => $action->handle($this->actor, $this->branch, [], 0, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($this->branch->scheduleExceptions()->count())->toBe(1);
});

test('audit failure rolls back temporal writes revisions and the command receipt', function (): void {
    $this->mock(RecordAuditLogAction::class)->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit write veto.'));
    expect(fn () => app(UpdateBranchTemporaryClosureAction::class)->handle($this->actor, $this->branch, true, 'Maintenance', null, 0, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(RuntimeException::class);
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse()
        ->and($this->branch->fresh()->pause_version)->toBe(0)
        ->and(AvailabilityCommand::query()->count())->toBe(0);
});

test('a successful pause receipt still rejects replay after authority is revoked', function (): void {
    $action = app(UpdateBranchTemporaryClosureAction::class);
    $request = (string) Str::uuid();
    $action->handle($this->actor, $this->branch, true, 'Maintenance', null, 0, 'Europe/Vilnius', $request);
    OrganizationUser::query()->where('organization_id', $this->branch->organization_id)->where('user_id', $this->actor->id)->update(['status' => OrganizationUserStatus::Suspended]);
    expect(fn () => $action->handle($this->actor, $this->branch, true, 'Maintenance', null, 0, 'Europe/Vilnius', $request))->toThrow(AuthorizationException::class);
    expect(AvailabilityCommand::query()->count())->toBe(1);
});

test('a weekly update cannot overwrite a newer date exception revision', function (): void {
    app(SaveBranchScheduleExceptionsAction::class)->handle($this->actor, $this->branch,
        [['local_date' => '2026-12-25', 'is_closed' => true, 'intervals' => []]], 0, 'Europe/Vilnius', (string) Str::uuid());
    expect(fn () => app(UpdateBranchOpeningHoursAction::class)->handle($this->actor, $this->branch,
        [['day_of_week' => 1, 'is_closed' => false, 'intervals' => [['opens_at' => '09:00', 'closes_at' => '18:00']]]], true, 0, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($this->branch->openingHours()->count())->toBe(0)->and($this->branch->scheduleExceptions()->count())->toBe(1);
});

test('one off date exceptions reject nonexistent and ambiguous local interval boundaries', function (string $date): void {
    $exceptions = [['local_date' => $date, 'is_closed' => false, 'intervals' => [['opens_at' => '03:30', 'closes_at' => '05:00']]]];
    try {
        app(SaveBranchScheduleExceptionsAction::class)->handle($this->actor, $this->branch, $exceptions, 0, 'Europe/Vilnius', (string) Str::uuid());
        $this->fail('A non-unique local boundary must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('exceptions.0.intervals.0.opens_at');
    }
    expect($this->branch->fresh()->opening_hours_version)->toBe(0)->and($this->branch->scheduleExceptions()->count())->toBe(0);
})->with(['2026-03-29', '2026-10-25']);

test('temporal mutations reject preview dependencies changed in another availability section', function (string $operation): void {
    $menu = Menu::factory()->for($this->branch)->create();
    $fingerprint = $operation === 'menu'
        ? AvailabilityDependencyFingerprint::menu($menu)
        : AvailabilityDependencyFingerprint::branch($this->branch);
    if ($operation === 'pause') {
        app(UpdateBranchOpeningHoursAction::class)->handle($this->actor, $this->branch, [], false, 0, 'Europe/Vilnius', (string) Str::uuid());
    } else {
        app(UpdateBranchTemporaryClosureAction::class)->handle($this->actor, $this->branch, true, 'Changed after preview', null, 0, 'Europe/Vilnius', (string) Str::uuid());
    }
    $change = match ($operation) {
        'pause' => fn () => app(UpdateBranchTemporaryClosureAction::class)->handle($this->actor, $this->branch, true, 'Stale preview', null, 0, 'Europe/Vilnius', (string) Str::uuid(), expectedContext: $fingerprint),
        'hours' => fn () => app(UpdateBranchOpeningHoursAction::class)->handle($this->actor, $this->branch, [], false, 0, 'Europe/Vilnius', (string) Str::uuid(), $fingerprint),
        'exceptions' => fn () => app(SaveBranchScheduleExceptionsAction::class)->handle($this->actor, $this->branch, [], 0, 'Europe/Vilnius', (string) Str::uuid(), $fingerprint),
        'menu' => fn () => app(SaveMenuAvailabilityScheduleAction::class)->handle($this->actor, $this->branch, $menu, [], true, 0, 'Europe/Vilnius', (string) Str::uuid(), $fingerprint),
    };
    expect($change)->toThrow(ValidationException::class);
    expect(AvailabilityCommand::query()->count())->toBe(1)->and($menu->fresh()->schedule_version)->toBe(0);
})->with(['pause', 'hours', 'exceptions', 'menu']);
