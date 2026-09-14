<?php

declare(strict_types=1);

use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Enums\ServicePointStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @return array{area_node_id: int|null, type: string, prefix: string, from: int, to: int, capacity: int, icon: string|null, is_active: bool} */
function bulkServicePointActionData(?int $areaNodeId = null): array
{
    return ['area_node_id' => $areaNodeId, 'type' => 'table', 'prefix' => 'T', 'from' => 1, 'to' => 3, 'capacity' => 4, 'icon' => 'squares-2x2', 'is_active' => true];
}

test('bulk action rejects invalid ranges before queries or persistence', function (string $method, int $from, int $to, string $field): void {
    $branch = Branch::factory()->create();
    $data = [...bulkServicePointActionData(), 'from' => $from, 'to' => $to];
    $queries = countDatabaseQueries(function () use ($method, $branch, $data, $field): void {
        try {
            app(BulkCreateServicePointsAction::class)->{$method}($branch, $data);
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
    $branch = Branch::factory()->create();
    $area = $withArea ? AreaNode::factory()->for($branch)->create() : null;
    $existing = ServicePoint::factory()->for($branch)->create(['internal_code' => 'T1']);
    $archived = ServicePoint::factory()->for($branch)->create(['internal_code' => 'T2']);
    $archived->delete();
    ServicePoint::factory()->create(['internal_code' => 'T3']);
    $data = bulkServicePointActionData($area?->id);
    $action = app(BulkCreateServicePointsAction::class);
    expect(array_column($action->preview($branch, $data), 'will_create'))->toBe([false, false, true]);
    $result = [];
    $queries = countDatabaseQueries(function () use ($action, $branch, $data, &$result): void {
        $result = $action->handle($branch, $data);
    });
    expect($queries)->toBe($withArea ? 3 : 2)
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
    expect($action->handle($branch, $data))->toBe([
        'created_count' => 0, 'skipped_count' => 3, 'created_ids' => [], 'preview' => $result['preview'],
    ]);
})->with(['without area' => false, 'with area' => true]);

test('bulk creation rolls back all rows when a model event cancels a required save', function (): void {
    $branch = Branch::factory()->create();
    $dispatcher = ServicePoint::getEventDispatcher();
    ServicePoint::setEventDispatcher(clone $dispatcher);
    ServicePoint::creating(fn (ServicePoint $point): bool => $point->internal_code !== 'T2');
    try {
        expect(fn () => app(BulkCreateServicePointsAction::class)->handle($branch, bulkServicePointActionData()))
            ->toThrow(RuntimeException::class, 'The service point could not be saved.');
    } finally {
        ServicePoint::setEventDispatcher($dispatcher);
    }
    expect(ServicePoint::query()->exists())->toBeFalse();
});

test('bulk action rejects foreign and archived areas', function (string $method, bool $archived): void {
    $branch = Branch::factory()->create();
    $area = AreaNode::factory()->for($archived ? $branch : Branch::factory()->create())->create();
    if ($archived) {
        $area->delete();
    }
    expect(fn () => app(BulkCreateServicePointsAction::class)->{$method}($branch, bulkServicePointActionData($area->id)))
        ->toThrow(InvalidArgumentException::class, 'errors.domain.selected_area_unavailable');
    expect(ServicePoint::query()->exists())->toBeFalse();
})->with(['preview', 'handle'])->with(['foreign' => false, 'archived' => true]);

test('bulk previews use the codes accepted by model events', function (): void {
    $branch = Branch::factory()->create();
    $dispatcher = ServicePoint::getEventDispatcher();
    ServicePoint::setEventDispatcher(clone $dispatcher);
    ServicePoint::creating(function (ServicePoint $point): void {
        if ($point->internal_code === 'T2') {
            $point->internal_code = 'CUSTOM2';
        }
    });
    try {
        $result = app(BulkCreateServicePointsAction::class)->handle($branch, bulkServicePointActionData());
    } finally {
        ServicePoint::setEventDispatcher($dispatcher);
    }
    expect($result['created_count'])->toBe(3)
        ->and(array_column($result['preview'], 'will_create'))->toBe([false, true, false])
        ->and(ServicePoint::query()->orderBy('id')->pluck('internal_code')->all())->toBe(['T1', 'CUSTOM2', 'T3']);
});

test('bulk range guards handle extreme integers without allocating oversized ranges', function (string $method, int $from, int $to): void {
    $branch = Branch::factory()->create();
    expect(fn () => app(BulkCreateServicePointsAction::class)->{$method}($branch, [...bulkServicePointActionData(), 'from' => $from, 'to' => $to]))
        ->toThrow(ValidationException::class);
    expect(ServicePoint::query()->exists())->toBeFalse();
})->with(['preview', 'handle'])->with([
    'largest end' => [1, PHP_INT_MAX],
    'negative overflow boundary' => [PHP_INT_MIN, PHP_INT_MAX],
]);

test('bulk creation accepts exactly two hundred entries and an outer rollback removes them', function (): void {
    $branch = Branch::factory()->create();
    $data = [...bulkServicePointActionData(), 'from' => 9800, 'to' => 9999];
    $result = [];
    try {
        DB::transaction(function () use ($branch, $data, &$result): void {
            $result = app(BulkCreateServicePointsAction::class)->handle($branch, $data);
            expect($result['created_count'])->toBe(200)
                ->and($result['created_ids'])->toHaveCount(200)
                ->and(ServicePoint::query()->count())->toBe(200);
            throw new RuntimeException('Outer rollback.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Outer rollback.');
    }
    expect(ServicePoint::query()->exists())->toBeFalse();
    $retry = app(BulkCreateServicePointsAction::class)->handle($branch, $data);
    expect($retry['created_count'])->toBe(200)
        ->and(array_column($retry['preview'], 'code'))->toBe(array_map(fn (int $number): string => 'T'.$number, range(9800, 9999)));
});

test('direct bulk range errors are localized for every supported locale', function (string $locale): void {
    app()->setLocale($locale);
    $branch = Branch::factory()->create();
    foreach ([[0, 2, 'bulkFrom', 'errors.domain.bulk_start_positive'], [3, 1, 'bulkTo', 'errors.domain.bulk_end_before_start']] as [$from, $to, $field, $key]) {
        try {
            app(BulkCreateServicePointsAction::class)->preview($branch, [...bulkServicePointActionData(), 'from' => $from, 'to' => $to]);
            $this->fail('The range should be rejected.');
        } catch (ValidationException $exception) {
            expect($exception->errors()[$field])->toBe([__($key)])->not->toBe([$key]);
        }
    }
})->with(['en', 'lt', 'ru']);
