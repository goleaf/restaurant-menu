<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\TableSessions\ConfirmBranchSessionCleanupAction;
use App\Actions\TableSessions\PreviewBranchSessionCleanupAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\TableSessionStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingsChange;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\OrganizationUser;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\SessionCleanupConcurrencyTasks;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->freezeTime();
});

test('cleanup preview is bounded read only and does not initialize missing settings', function (): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $before = $session->fresh()->getRawOriginal();

    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);

    expect($preview['summary']['pending_candidates'])->toBe(1)
        ->and($preview['has_more'])->toBeFalse()
        ->and($session->fresh()->getRawOriginal())->toBe($before)
        ->and(BranchSetting::query()->where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(BranchSettingsChange::query()->count())->toBe(0);
});

test('confirmed cleanup replays the original result without duplicate cancellation audit or timestamps', function (): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    $request = (string) Str::uuid();
    $result = app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, $request);
    $endedAt = $session->fresh()->ended_at->toISOString();
    $this->travel(5)->minutes();

    $replay = app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, $request);

    expect($result['pending_cancelled'])->toBe(1)
        ->and($replay)->toBe($result)
        ->and($session->fresh()->ended_at->toISOString())->toBe($endedAt)
        ->and(BranchSettingsChange::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', AuditLogAction::TableSessionInactivityCleanup)->count())->toBe(1);
});

test('confirmed cleanup skips a candidate after new guest activity', function (string $activity): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $guest = TableSessionGuest::factory()->for($session)->create(['joined_at' => now()->subHour()]);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    if ($activity === 'guest_join') {
        TableSessionGuest::factory()->for($session)->create();
    } elseif ($activity === 'waiter_call') {
        WaiterCall::factory()->forTableSession($session)->create(['requested_by_guest_id' => $guest->id]);
    } else {
        $session->forceFill(['updated_at' => now()])->save();
    }

    $result = app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid());

    expect($result['pending_cancelled'])->toBe(0)
        ->and($result['skipped_recent'])->toBe(1)
        ->and($session->fresh()->status)->toBe(TableSessionStatus::Pending)
        ->and($guest->fresh()->left_at)->toBeNull();
})->with(['guest_join', 'waiter_call', 'session_change']);

test('passive guest locale updates do not extend the inactivity deadline', function (): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $guest = TableSessionGuest::factory()->for($session)->create(['joined_at' => now()->subHour()]);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    $guest->update(['locale' => 'lt']);

    $result = app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid());

    expect($result['pending_cancelled'])->toBe(1);
});

test('cleanup refuses an altered threshold and does not cancel its previous candidates', function (): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $settings = BranchSetting::factory()->for($branch)->create();
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    $settings->update(['pending_session_expire_minutes' => 120]);

    expect(fn () => app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($session->fresh()->status)->toBe(TableSessionStatus::Pending)
        ->and(BranchSettingsChange::query()->count())->toBe(0);
});

test('cleanup rolls back a rejected transition or audit instead of reporting success', function (string $failure): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    if ($failure === 'transition') {
        TableSession::saving(fn (TableSession $candidate): bool => $candidate->status !== TableSessionStatus::Cancelled || ! $candidate->isDirty('status'));
    } else {
        AuditLog::saving(fn (AuditLog $audit): bool => $audit->action !== AuditLogAction::TableSessionInactivityCleanup);
    }

    expect(fn () => app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid()))
        ->toThrow(RuntimeException::class);
    expect($session->fresh()->status)->toBe(TableSessionStatus::Pending)
        ->and($session->fresh()->ended_at)->toBeNull()
        ->and(BranchSettingsChange::query()->count())->toBe(0);
})->with(['transition', 'audit']);

test('cleanup skips a changed visit even when its newer activity is already beyond the threshold', function (): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    $session->forceFill(['updated_at' => now()->subMinutes(40)])->save();

    $result = app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid());

    expect($result['skipped_changed'])->toBe(1)->and($session->fresh()->status)->toBe(TableSessionStatus::Pending);
});

test('cleanup ignores unrelated settings writes including first initialization at identical thresholds', function (): void {
    [$actor, $branch] = branchCleanupContext();
    branchCleanupSession($branch);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    BranchSetting::factory()->for($branch)->create(['polling_interval_seconds' => 10]);

    expect(app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid())['pending_cancelled'])->toBe(1);
});

test('manual cleanup refuses damaged stored thresholds without normalizing persistence', function (string $field, mixed $value): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $settings = BranchSetting::factory()->for($branch)->create([$field => $value]);
    $before = $settings->fresh()->getRawOriginal($field);

    expect(fn () => app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch))->toThrow(ValidationException::class);
    expect($settings->fresh()->getRawOriginal($field))->toBe($before)
        ->and($session->fresh()->status)->toBe(TableSessionStatus::Pending);
})->with([
    ['pending_session_expire_minutes', 0],
    ['inactivity_warning_minutes', 1441],
    ['pending_session_expire_minutes', 'damaged'],
]);

test('cleanup skips new drafts and never includes visits absent from its preview', function (): void {
    [$actor, $branch] = branchCleanupContext();
    $original = branchCleanupSession($branch);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    DraftOrder::factory()->for($original)->create();
    $later = branchCleanupSession($branch);

    $result = app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid());

    expect($result['pending_cancelled'])->toBe(0)
        ->and($result['skipped_existing_drafts'])->toBe(1)
        ->and($original->fresh()->status)->toBe(TableSessionStatus::Pending)
        ->and($later->fresh()->status)->toBe(TableSessionStatus::Pending);
});

test('cleanup rejects tampering and stale account access at confirmation', function (string $change): void {
    [$actor, $branch] = branchCleanupContext();
    $session = branchCleanupSession($branch);
    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    if ($change === 'tamper') {
        $preview['candidates'][0]['id']++;
    } else {
        OrganizationUser::query()->where('user_id', $actor->id)->where('organization_id', $branch->organization_id)
            ->update(['status' => OrganizationUserStatus::Suspended]);
    }

    expect(fn () => app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid()))
        ->toThrow($change === 'tamper' ? ValidationException::class : AuthorizationException::class);
    expect($session->fresh()->status)->toBe(TableSessionStatus::Pending);
})->with(['tamper', 'revoked']);

test('cleanup preview reports unexamined remainder and keeps active sessions as warnings', function (): void {
    [$actor, $branch] = branchCleanupContext();
    $active = branchCleanupSession($branch, TableSessionStatus::Active);
    for ($index = 0; $index < PreviewBranchSessionCleanupAction::LIMIT; $index++) {
        branchCleanupSession($branch);
    }

    $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
    $result = app(ConfirmBranchSessionCleanupAction::class)->handle($actor, $branch, $preview, (string) Str::uuid());

    expect($preview['summary']['checked'])->toBe(100)
        ->and($preview['summary']['active_warnings'])->toBe(1)
        ->and($preview['has_more'])->toBeTrue()
        ->and($result['pending_cancelled'])->toBe(99)
        ->and($active->fresh()->status)->toBe(TableSessionStatus::Active)
        ->and(TableSession::query()->where('status', TableSessionStatus::Pending)->count())->toBe(1);
});

test('independent SQLite cleanup processes preserve new activity and converge double submit', function (string $race): void {
    $path = tempnam(sys_get_temp_dir(), 'session-cleanup-concurrency-');
    expect($path)->toBeString();
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'session_cleanup_concurrency', 'database.connections.session_cleanup_concurrency' => $connection]);
        DB::purge('session_cleanup_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'session_cleanup_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        [$actor, $branch] = branchCleanupContext();
        $session = branchCleanupSession($branch);
        $preview = app(PreviewBranchSessionCleanupAction::class)->handle($actor, $branch);
        $request = (string) Str::uuid();
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run([
            SessionCleanupConcurrencyTasks::run($connection, $branch->id, $actor->id, $session->id, $preview, $request, $race === 'activity' ? 'after_activity' : 'confirm'),
            SessionCleanupConcurrencyTasks::run($connection, $branch->id, $actor->id, $session->id, $preview, $request, $race === 'activity' ? 'activity' : 'confirm'),
        ], 25);
        config(['database.default' => 'session_cleanup_concurrency']);
        DB::purge('session_cleanup_concurrency');
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'pid'))->not->toContain(getmypid())
            ->and(BranchSettingsChange::query()->count())->toBe(1)
            ->and($session->fresh()->status)->toBe($race === 'activity' ? TableSessionStatus::Pending : TableSessionStatus::Cancelled)
            ->and(AuditLog::query()->where('action', AuditLogAction::TableSessionInactivityCleanup)->count())->toBe($race === 'activity' ? 0 : 1);
        if ($race === 'activity') {
            expect($results[0]['result']['skipped_recent'])->toBe(1);
        } else {
            expect($results[0]['result'])->toBe($results[1]['result']);
        }
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('session_cleanup_concurrency');
        DB::purge('session_cleanup_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm', $path.'.activity', ...glob($path.'.ready.*')]);
    }
})->with(['activity', 'double submit']);

/** @return array{User, Branch} */
function branchCleanupContext(): array
{
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Cleanup restaurant']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();

    return [$actor->fresh(), $branch];
}

function branchCleanupSession(Branch $branch, TableSessionStatus $status = TableSessionStatus::Pending): TableSession
{
    $point = ServicePoint::factory()->for($branch)->create();

    return TableSession::factory()->forServicePoint($point)->create([
        'status' => $status, 'started_at' => now()->subHour(), 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
    ]);
}
