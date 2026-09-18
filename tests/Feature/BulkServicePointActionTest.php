<?php

declare(strict_types=1);

use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Enums\ServicePointStatus;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FloorOperation;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

/** @return array{Branch, User} */
function authorizedBulkServicePointBranch(): array
{
    $branch = Branch::factory()->create();
    $actor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($branch->organization)->for($actor)->forSystemRole(SystemRole::Owner)->active()->create();

    return [$branch, $actor];
}

/** @return array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool} */
function bulkServicePointActionData(?int $areaNodeId = null): array
{
    return ['area_node_id' => $areaNodeId, 'type' => 'table', 'prefix' => 'T', 'from' => 1, 'to' => 3, 'capacity' => 4, 'icon' => 'squares-2x2', 'is_active' => true];
}

test('bulk action rejects invalid ranges before queries or persistence', function (string $method, int $from, int $to, string $field): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $data = [...bulkServicePointActionData(), 'from' => $from, 'to' => $to];
    $queries = countDatabaseQueries(function () use ($method, $branch, $data, $field, $actor): void {
        try {
            app(BulkCreateServicePointsAction::class)->{$method}($branch, $data, $actor);
            $this->fail('The allocation range should be rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($field);
        }
    });
    expect($queries)->toBe(0)->and(ServicePoint::query()->exists())->toBeFalse();
})->with(['preview', 'handle'])->with([
    'too many' => [1, 201, 'bulkTo'],
    'descending' => [3, 1, 'bulkTo'],
    'zero start' => [0, 2, 'bulkFrom'],
    'negative start' => [-2, 1, 'bulkFrom'],
]);

test('bulk creation retains reserved codes and returns exact persisted results without a second preview', function (bool $withArea): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $area = $withArea ? AreaNode::factory()->for($branch)->create() : null;
    $existing = ServicePoint::factory()->for($branch)->create(['internal_code' => 'T1']);
    $archived = ServicePoint::factory()->for($branch)->create(['internal_code' => 'T2']);
    $archived->delete();
    ServicePoint::factory()->create(['internal_code' => 'T3']);
    $data = bulkServicePointActionData($area?->id);
    $action = app(BulkCreateServicePointsAction::class);
    expect(array_column($action->preview($branch, $data, $actor), 'will_create'))->toBe([false, false, true]);
    $result = [];
    $queries = countDatabaseQueries(function () use ($action, $branch, $data, $actor, &$result): void {
        $result = $action->handle($branch, $data, $actor);
    });
    expect($queries)->toBe($withArea ? 17 : 16)
        ->and($result['created_count'])->toBe(1)
        ->and($result['skipped_count'])->toBe(2)
        ->and($result['created_ids'])->toHaveCount(1)
        ->and(array_column($result['preview'], 'code'))->toBe(['T1', 'T2', 'T3'])
        ->and(array_column($result['preview'], 'exists'))->toBe([true, true, true])
        ->and(array_column($result['preview'], 'will_create'))->toBe([false, false, false]);
    $created = ServicePoint::query()->findOrFail($result['created_ids'][0]);
    expect($created->only(['branch_id', 'area_node_id', 'internal_code', 'name', 'display_number', 'capacity', 'is_active', 'status']))
        ->toBe(['branch_id' => $branch->id, 'area_node_id' => $area?->id, 'internal_code' => 'T3', 'name' => 'T3', 'display_number' => 'T3', 'capacity' => 4, 'is_active' => true, 'status' => ServicePointStatus::Free])
        ->and($archived->fresh()->trashed())->toBeTrue()
        ->and($existing->fresh()->internal_code)->toBe('T1')
        ->and(QrCode::query()->exists())->toBeFalse();
    expect($action->handle($branch, $data, $actor))->toBe([
        'created_count' => 0, 'skipped_count' => 3, 'created_ids' => [], 'preview' => $result['preview'],
    ]);
})->with(['without area' => false, 'with area' => true]);

test('bulk creation rolls back all rows when a model event cancels a required save', function (): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $dispatcher = ServicePoint::getEventDispatcher();
    ServicePoint::setEventDispatcher(clone $dispatcher);
    ServicePoint::creating(fn (ServicePoint $point): bool => $point->internal_code !== 'T2');
    try {
        expect(fn () => app(BulkCreateServicePointsAction::class)->handle($branch, bulkServicePointActionData(), $actor))
            ->toThrow(RuntimeException::class, 'The service point could not be saved.');
    } finally {
        ServicePoint::setEventDispatcher($dispatcher);
    }
    expect(ServicePoint::query()->exists())->toBeFalse();
});

test('bulk action rejects foreign and archived areas', function (string $method, bool $archived): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $area = AreaNode::factory()->for($archived ? $branch : Branch::factory()->create())->create();
    if ($archived) {
        $area->delete();
    }
    expect(fn () => app(BulkCreateServicePointsAction::class)->{$method}($branch, bulkServicePointActionData($area->id), $actor))
        ->toThrow(InvalidArgumentException::class, 'errors.domain.selected_area_unavailable');
    expect(ServicePoint::query()->exists())->toBeFalse();
})->with(['preview', 'handle'])->with(['foreign' => false, 'archived' => true]);

test('bulk previews use the codes accepted by model events', function (): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $dispatcher = ServicePoint::getEventDispatcher();
    ServicePoint::setEventDispatcher(clone $dispatcher);
    ServicePoint::creating(function (ServicePoint $point): void {
        if ($point->internal_code === 'T2') {
            $point->internal_code = 'CUSTOM2';
        }
    });
    try {
        $result = app(BulkCreateServicePointsAction::class)->handle($branch, bulkServicePointActionData(), $actor);
    } finally {
        ServicePoint::setEventDispatcher($dispatcher);
    }
    expect($result['created_count'])->toBe(3)
        ->and(array_column($result['preview'], 'will_create'))->toBe([false, true, false])
        ->and(ServicePoint::query()->orderBy('id')->pluck('internal_code')->all())->toBe(['T1', 'CUSTOM2', 'T3']);
});

test('bulk range guards handle extreme integers without allocating oversized ranges', function (string $method, int $from, int $to): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    expect(fn () => app(BulkCreateServicePointsAction::class)->{$method}($branch, [...bulkServicePointActionData(), 'from' => $from, 'to' => $to], $actor))
        ->toThrow(ValidationException::class);
    expect(ServicePoint::query()->exists())->toBeFalse();
})->with(['preview', 'handle'])->with([
    'largest end' => [1, PHP_INT_MAX],
    'negative overflow boundary' => [PHP_INT_MIN, PHP_INT_MAX],
]);

test('bulk creation accepts exactly two hundred entries and an outer rollback removes them', function (): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $data = [...bulkServicePointActionData(), 'from' => 9800, 'to' => 9999];
    $result = [];
    try {
        DB::transaction(function () use ($branch, $data, $actor, &$result): void {
            $result = app(BulkCreateServicePointsAction::class)->handle($branch, $data, $actor);
            expect($result['created_count'])->toBe(200)
                ->and($result['created_ids'])->toHaveCount(200)
                ->and(ServicePoint::query()->count())->toBe(200);
            throw new RuntimeException('Outer rollback.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Outer rollback.');
    }
    expect(ServicePoint::query()->exists())->toBeFalse();
    $retry = app(BulkCreateServicePointsAction::class)->handle($branch, $data, $actor);
    expect($retry['created_count'])->toBe(200)
        ->and(array_column($retry['preview'], 'code'))->toBe(array_map(fn (int $number): string => 'T'.$number, range(9800, 9999)));
});

test('direct bulk range errors are localized for every supported locale', function (string $locale): void {
    app()->setLocale($locale);
    [$branch, $actor] = authorizedBulkServicePointBranch();
    foreach ([[0, 2, 'bulkFrom', 'errors.domain.bulk_start_positive'], [3, 1, 'bulkTo', 'errors.domain.bulk_end_before_start']] as [$from, $to, $field, $key]) {
        try {
            app(BulkCreateServicePointsAction::class)->preview($branch, [...bulkServicePointActionData(), 'from' => $from, 'to' => $to], $actor);
            $this->fail('The range should be rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors()[$field])->toBe([__($key)])->not->toBe([$key]);
        }
    }
})->with(['en', 'lt', 'ru']);

test('a saved bulk request cannot be reused with different content actor or restaurant', function (string $change): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $action = app(BulkCreateServicePointsAction::class);
    $data = bulkServicePointActionData();
    $preview = $action->previewState($branch, $data, $actor);
    $key = (string) Str::uuid();
    $result = $action->handle($branch, $data, $actor, $key, $preview['fingerprint']);
    $points = ServicePoint::query()->orderBy('id')->get()->map->getRawOriginal()->all();
    $receipt = FloorOperation::query()->sole()->getRawOriginal();
    $auditCount = AuditLog::query()->count();
    $targetBranch = $branch;
    $targetActor = $actor;
    if ($change === 'payload') {
        $data['capacity'] = 9;
    } elseif ($change === 'actor') {
        $targetActor = User::factory()->create();
        OrganizationUser::factory()->forOrganization($branch->organization)->forUser($targetActor)->forSystemRole(SystemRole::Owner)->active()->create();
    } else {
        $targetBranch = Branch::factory()->for($branch->organization)->create();
    }

    try {
        $action->handle($targetBranch, $data, $targetActor, $key, $preview['fingerprint']);
        $this->fail('A saved operation must remain bound to its original command and scope.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['requestId' => [__('floor.errors.command_conflict')]]);
    }

    expect(ServicePoint::query()->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($points)
        ->and(FloorOperation::query()->sole()->getRawOriginal())->toBe($receipt)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and($result['created_ids'])->toBe(array_column($points, 'id'))
        ->and(QrCode::query()->count())->toBe(0);
})->with(['payload', 'actor', 'restaurant']);

test('bulk confirmation and response replay both reauthorize a membership revoked after preview', function (bool $alreadySaved): void {
    [$branch, $actor] = authorizedBulkServicePointBranch();
    $action = app(BulkCreateServicePointsAction::class);
    $data = bulkServicePointActionData();
    $preview = $action->previewState($branch, $data, $actor);
    $key = (string) Str::uuid();
    if ($alreadySaved) {
        $action->handle($branch, $data, $actor, $key, $preview['fingerprint']);
    }
    $points = ServicePoint::query()->orderBy('id')->get()->map->getRawOriginal()->all();
    $receipts = FloorOperation::query()->orderBy('id')->get()->map->getRawOriginal()->all();
    $auditCount = AuditLog::query()->count();
    $branch->organization->memberships()->where('user_id', $actor->id)->update(['status' => 'suspended']);

    expect(fn () => $action->handle($branch, $data, $actor, $key, $preview['fingerprint']))->toThrow(AuthorizationException::class)
        ->and(ServicePoint::query()->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($points)
        ->and(FloorOperation::query()->orderBy('id')->get()->map->getRawOriginal()->all())->toBe($receipts)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(QrCode::query()->count())->toBe(0);
})->with(['before creation' => false, 'lost response replay' => true]);
