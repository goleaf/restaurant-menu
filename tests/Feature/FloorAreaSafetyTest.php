<?php

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\AreaNodes\DeleteAreaNodeAction;
use App\Actions\AreaNodes\RestoreAreaNodeAction;
use App\Actions\AreaNodes\SetAreaNodeActiveAction;
use App\Actions\AreaNodes\UpdateAreaNodeAction;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use App\Services\Branches\AreaNodeQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\Support\FloorAreaConcurrencyTasks;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->actor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->for($this->actor)->forSystemRole(SystemRole::Owner)->active()->create();
    $this->area = AreaNode::factory()->for($this->branch)->create(['name' => 'Original hall']);
});

function floorAreaPayload(AreaNode $area, array $changes = []): array
{
    return array_replace($area->only(['parent_id', 'name', 'icon', 'sort_order', 'is_active']), ['type' => $area->type->value], $changes);
}

test('area writes reject a current actor without management permission', function (string $operation) {
    $actor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->for($actor)->forSystemRole(SystemRole::Waiter)->active()->create();
    expect(fn () => match ($operation) {
        'create' => app(CreateAreaNodeAction::class)->handle($this->branch, floorAreaPayload($this->area), $actor),
        'update' => app(UpdateAreaNodeAction::class)->handle($this->area, floorAreaPayload($this->area, ['name' => 'Wrong']), $actor, 0),
        'status' => app(SetAreaNodeActiveAction::class)->handle($this->area, false, $actor, 0),
    })->toThrow(AuthorizationException::class);
    expect($this->area->fresh()->name)->toBe('Original hall')->and(AreaNode::query()->count())->toBe(1);
})->with(['create', 'update', 'status']);

test('a rejected area archive rolls back child reparenting', function () {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id]);
    AreaNode::deleting(fn (AreaNode $area): bool => $area->id !== $this->area->id);
    expect(fn () => app(DeleteAreaNodeAction::class)->handle($this->actor, $this->branch, $this->area, 0))->toThrow(RuntimeException::class);
    expect($child->fresh()->parent_id)->toBe($this->area->id)->and($this->area->fresh())->not->toBeNull();
});

test('area archive rejects every nonterminal direct or linked session in its subtree', function (TableSessionStatus $status, bool $linked) {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id]);
    $point = ServicePoint::factory()->for($this->branch)->for($child, 'areaNode')->create();
    $sessionPoint = $linked ? ServicePoint::factory()->for($this->branch)->create() : $point;
    $session = TableSession::factory()->forServicePoint($sessionPoint)->create(['status' => $status]);
    if ($linked) {
        TableSessionServicePoint::factory()->forTableSessionAndServicePoint($session, $point)->create();
    }
    expect(fn () => app(DeleteAreaNodeAction::class)->handle($this->actor, $this->branch, $this->area, 0))->toThrow(ValidationException::class);
    expect($child->fresh()->parent_id)->toBe($this->area->id)->and($this->area->fresh())->not->toBeNull();
})->with([TableSessionStatus::Pending, TableSessionStatus::Active, TableSessionStatus::WaitingWaiterConfirmation, TableSessionStatus::PaymentRequested, TableSessionStatus::Paid])->with([false, true]);

test('area versions reject an ABA editor and an obsolete archive confirmation', function () {
    $action = app(UpdateAreaNodeAction::class);
    $action->handle($this->area, floorAreaPayload($this->area, ['name' => 'Changed']), $this->actor, 0);
    $current = $this->area->fresh();
    $action->handle($current, floorAreaPayload($current, ['name' => 'Original hall']), $this->actor, 1);
    expect(fn () => $action->handle($this->area, floorAreaPayload($this->area), $this->actor, 0))->toThrow(ValidationException::class);
    expect(fn () => app(DeleteAreaNodeAction::class)->handle($this->actor, $this->branch, $this->area, 0))->toThrow(ValidationException::class);
    expect($this->area->fresh()->structure_version)->toBe(2);
});

test('area persistence and its audit roll back together', function () {
    AuditLog::saving(fn (): bool => false);
    expect(fn () => app(UpdateAreaNodeAction::class)->handle($this->area, floorAreaPayload($this->area, ['name' => 'Changed']), $this->actor, 0))->toThrow(RuntimeException::class);
    expect($this->area->fresh()->name)->toBe('Original hall')->and($this->area->fresh()->structure_version)->toBe(0);
});

test('area browser keeps a full parent path and the selected record outside its bounded search page', function () {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id, 'name' => 'Needle child']);
    AreaNode::factory()->for($this->branch)->count(30)->create(['name' => 'Search result']);
    $result = app(AreaNodeQueryService::class)->browser($this->branch, 'Search', $child->id, perPage: 5);
    expect($result['rows'])->toHaveCount(6)->and($result['paginator']->count())->toBe(5);
    $selected = collect($result['rows'])->firstWhere('id', $child->id);
    expect($selected['path'])->toBe('Original hall / Needle child')->and($selected['selected'])->toBeTrue();
});

test('area browser detects malformed cycles without looping or pretending they are roots', function () {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id]);
    $this->area->forceFill(['parent_id' => $child->id])->save();
    $result = app(AreaNodeQueryService::class)->browser($this->branch, '', $this->area->id);
    expect(collect($result['rows'])->firstWhere('id', $this->area->id)['hierarchy_valid'])->toBeFalse();
});

test('area browser preserves type activity sorting and selected context without widening the page', function () {
    $first = AreaNode::factory()->for($this->branch)->create(['type' => 'terrace', 'name' => 'A terrace', 'is_active' => false]);
    $last = AreaNode::factory()->for($this->branch)->create(['type' => 'terrace', 'name' => 'Z terrace', 'is_active' => false]);
    AreaNode::factory()->for($this->branch)->create(['type' => 'terrace', 'name' => 'Active terrace', 'is_active' => true]);
    $result = app(AreaNodeQueryService::class)->browser($this->branch, '', $this->area->id, filters: ['type' => 'terrace', 'active' => 'inactive', 'sort' => 'name_desc']);
    expect(array_column($result['rows'], 'id'))->toBe([$this->area->id, $last->id, $first->id])
        ->and($result['paginator']->getCollection()->modelKeys())->toBe([$last->id, $first->id]);
});

test('area action validates original input before writing', function (string $field, mixed $value) {
    expect(fn () => app(UpdateAreaNodeAction::class)->handle($this->area, floorAreaPayload($this->area, [$field => $value]), $this->actor, 0))->toThrow(ValidationException::class);
    expect($this->area->fresh()->name)->toBe('Original hall')->and($this->area->fresh()->structure_version)->toBe(0);
})->with(['name' => ['name', '   '], 'type' => ['type', ['hall']], 'parent' => ['parent_id', true], 'order' => ['sort_order', false], 'flag' => ['is_active', 'no'], 'icon' => ['icon', 'unknown']]);

test('archiving one idle area retains tables and waiter assignments and advances child revisions', function () {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id]);
    $point = ServicePoint::factory()->for($this->branch)->for($this->area, 'areaNode')->create();
    $assignment = AreaNodeWaiter::factory()->create(['organization_id' => $this->branch->organization_id, 'branch_id' => $this->branch->id, 'area_node_id' => $this->area->id]);
    $preview = app(AreaNodeQueryService::class)->archivePreview($this->branch, $this->area);
    expect($preview)->toMatchArray(['children_count' => 1, 'tables_count' => 1, 'assignments_count' => 1, 'can_archive' => true]);
    app(DeleteAreaNodeAction::class)->handle($this->actor, $this->branch, $this->area, 0);
    expect($point->fresh()->area_node_id)->toBe($this->area->id)->and($assignment->fresh())->not->toBeNull()
        ->and($child->fresh()->parent_id)->toBeNull()->and($child->fresh()->structure_version)->toBe(1)
        ->and(AreaNode::withTrashed()->findOrFail($this->area->id)->structure_version)->toBe(1);
});

test('a rejected area restore reports failure and retains the archive', function () {
    $this->area->delete();
    AreaNode::restoring(fn (): bool => false);
    expect(fn () => app(RestoreAreaNodeAction::class)->handle($this->actor, $this->branch, $this->area, 1))->toThrow(RuntimeException::class);
    expect(AreaNode::withTrashed()->findOrFail($this->area->id)->trashed())->toBeTrue();
});

test('the server cycle guard rejects a cycle even when component validation was bypassed', function () {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id]);
    expect(fn () => app(UpdateAreaNodeAction::class)->handle($this->area, floorAreaPayload($this->area, ['parent_id' => $child->id]), $this->actor, 0))->toThrow(InvalidArgumentException::class);
    expect($this->area->fresh()->parent_id)->toBeNull();
});

test('area browser excludes descendants from parents and never exposes foreign selected identifiers', function () {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id]);
    $foreign = AreaNode::factory()->create(['name' => 'Private foreign area']);
    $result = app(AreaNodeQueryService::class)->browser($this->branch, '', $foreign->id, $this->area->id);
    expect($result['rows'])->toBe([])->and(json_encode($result['rows']))->not->toContain($foreign->name);
});

test('bounded area browser query count and payload do not grow with unrelated areas', function () {
    AreaNode::factory()->for($this->branch)->count(30)->create(['parent_id' => $this->area->id, 'name' => 'Search hall', 'sort_order' => 0]);
    $queries = app(AreaNodeQueryService::class);
    $before = [];
    $firstCount = countDatabaseQueries(function () use ($queries, &$before) {
        $before = $queries->browser($this->branch, 'Search', $this->area->id, perPage: 5);
    });
    AreaNode::factory()->for($this->branch)->count(300)->create(['parent_id' => $this->area->id, 'name' => 'Search hall', 'sort_order' => 0]);
    $after = [];
    $secondCount = countDatabaseQueries(function () use ($queries, &$after) {
        $after = $queries->browser($this->branch, 'Search', $this->area->id, perPage: 5);
    });
    expect($firstCount)->toBe($secondCount)->toBeLessThanOrEqual(3)
        ->and($after['rows'])->toHaveCount(6)
        ->and(array_column($after['rows'], 'id'))->toBe(array_column($before['rows'], 'id'))
        ->and(strlen(json_encode($after['rows'])))->toBeLessThan(5000);
});

test('area no-op writes preserve versions and do not create audit noise', function () {
    app(UpdateAreaNodeAction::class)->handle($this->area, floorAreaPayload($this->area), $this->actor, 0);
    app(SetAreaNodeActiveAction::class)->handle($this->area, true, $this->actor, 0);
    expect($this->area->fresh()->structure_version)->toBe(0)
        ->and(AuditLog::query()->where('entity_type', 'area_node')->count())->toBe(0);
});

test('area writes reauthorize after management access was revoked', function () {
    $member = OrganizationUser::query()->where('user_id', $this->actor->id)->where('organization_id', $this->branch->organization_id)->firstOrFail();
    $member->forceFill(['status' => 'suspended'])->save();
    expect(fn () => app(UpdateAreaNodeAction::class)->handle($this->area, floorAreaPayload($this->area, ['name' => 'Changed']), $this->actor, 0))->toThrow(AuthorizationException::class);
    expect($this->area->fresh()->name)->toBe('Original hall');
});

test('area archive confirmation rejects a changed child dependency even after its value returns', function () {
    $child = AreaNode::factory()->for($this->branch)->create(['parent_id' => $this->area->id, 'name' => 'Original child']);
    $preview = app(AreaNodeQueryService::class)->archivePreview($this->branch, $this->area);
    expect($preview)->toHaveKey('fingerprint');
    $child->update(['name' => 'Changed child']);
    $child->update(['name' => 'Original child']);
    expect(fn () => app(DeleteAreaNodeAction::class)->handle($this->actor, $this->branch, $this->area, 0, $preview['fingerprint']))->toThrow(ValidationException::class);
    expect($this->area->fresh())->not->toBeNull()->and($child->fresh()->parent_id)->toBe($this->area->id);
});

test('independent sqlite writers cannot create a mutual area parent cycle', function () {
    $path = tempnam(sys_get_temp_dir(), 'floor-area-concurrency-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'floor_area_concurrency', 'database.connections.floor_area_concurrency' => $connection]);
        DB::purge('floor_area_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'floor_area_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $branch = Branch::factory()->create();
        $actor = User::factory()->create();
        OrganizationUser::factory()->forOrganization($branch->organization)->for($actor)->forSystemRole(SystemRole::Owner)->active()->create();
        $first = AreaNode::factory()->for($branch)->create();
        $second = AreaNode::factory()->for($branch)->create();
        $tasks = [FloorAreaConcurrencyTasks::move($connection, $actor->id, $first->id, $second->id), FloorAreaConcurrencyTasks::move($connection, $actor->id, $second->id, $first->id)];
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'floor_area_concurrency']);
        DB::purge('floor_area_concurrency');
        $states = array_column($results, 'result');
        sort($states);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)->and($states)->toBe(['cycle_rejected', 'moved'])
            ->and(AreaNode::query()->whereNotNull('parent_id')->count())->toBe(1)
            ->and(AuditLog::query()->where('entity_type', 'area_node')->count())->toBe(1);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('floor_area_concurrency');
        DB::purge('floor_area_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
});
