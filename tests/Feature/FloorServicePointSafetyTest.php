<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Actions\ServicePoints\DeleteServicePointAction;
use App\Actions\ServicePoints\MoveServicePointsAction;
use App\Actions\ServicePoints\RestoreServicePointAction;
use App\Actions\ServicePoints\SetServicePointActiveAction;
use App\Actions\ServicePoints\UpdateServicePointAction;
use App\Enums\AuditLogAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FloorOperation;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use App\Services\Branches\ServicePointQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\FloorServicePointConcurrencyTasks;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->floorActor = User::factory()->create();
    $this->floorOrganization = app(CreateOrganizationAction::class)->handle($this->floorActor, ['name' => 'Floor safety organization']);
    $this->floorBranch = Branch::factory()->for($this->floorOrganization)->create();
    $this->actingAs($this->floorActor);
});

/** @return array{area_node_id: int|null, type: string, name: string, display_number: string, capacity: int, icon: string, is_active: bool} */
function floorPointPayload(?int $areaId = null): array
{
    return ['area_node_id' => $areaId, 'type' => 'table', 'name' => 'Physical table', 'display_number' => '12', 'capacity' => 4, 'icon' => 'squares-2x2', 'is_active' => true];
}

test('physical restructuring rejects every unfinished direct or linked session', function (string $operation, TableSessionStatus $status, bool $linked): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    $primary = $linked ? ServicePoint::factory()->for($this->floorBranch)->create() : $point;
    $session = TableSession::factory()->forServicePoint($primary)->create(['status' => $status]);
    if ($linked) {
        TableSessionServicePoint::factory()->forTableSessionAndServicePoint($session, $point)->linked()->create();
    }
    $area = AreaNode::factory()->for($this->floorBranch)->create();
    $before = $point->fresh()->getRawOriginal();
    $mutation = fn () => match ($operation) {
        'move' => app(UpdateServicePointAction::class)->handle($point, floorPointPayload($area->id), $this->floorActor),
        'edit-deactivate' => app(UpdateServicePointAction::class)->handle($point, [...floorPointPayload(), 'is_active' => false], $this->floorActor),
        'deactivate' => app(SetServicePointActiveAction::class)->handle($point, false, $this->floorActor),
        'archive' => app(DeleteServicePointAction::class)->handle($this->floorActor, $this->floorBranch, $point),
    };
    expect($mutation)->toThrow(ValidationException::class)
        ->and($point->fresh()->getRawOriginal())->toBe($before)
        ->and($session->fresh()->status)->toBe($status);
})->with(['move', 'edit-deactivate', 'deactivate', 'archive'])->with([
    TableSessionStatus::Pending, TableSessionStatus::Active, TableSessionStatus::WaitingWaiterConfirmation,
    TableSessionStatus::PaymentRequested, TableSessionStatus::Paid,
])->with(['direct' => false, 'linked' => true]);

test('physical restructuring rejects outstanding orders even after a session has closed', function (string $operation): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    $session = TableSession::factory()->forServicePoint($point)->closed()->create();
    $order = Order::factory()->forTableSession($session)->paymentRequested()->create();
    $area = AreaNode::factory()->for($this->floorBranch)->create();
    expect(fn () => match ($operation) {
        'move' => app(UpdateServicePointAction::class)->handle($point, floorPointPayload($area->id), $this->floorActor),
        'deactivate' => app(SetServicePointActiveAction::class)->handle($point, false, $this->floorActor),
        'archive' => app(DeleteServicePointAction::class)->handle($this->floorActor, $this->floorBranch, $point),
    })->toThrow(ValidationException::class);
    expect($point->fresh()->is_active)->toBeTrue()->and($point->fresh()->area_node_id)->toBeNull()
        ->and($order->fresh()->service_point_id)->toBe($point->id);
})->with(['move', 'deactivate', 'archive']);

test('moving a physical table rolls back when its audit cannot be recorded', function (): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    $area = AreaNode::factory()->for($this->floorBranch)->create();
    $dispatcher = AuditLog::getEventDispatcher();
    AuditLog::setEventDispatcher(clone $dispatcher);
    AuditLog::creating(fn (AuditLog $log): bool => $log->action !== AuditLogAction::ServicePointMoved);
    try {
        expect(fn () => app(UpdateServicePointAction::class)->handle($point, floorPointPayload($area->id), $this->floorActor))
            ->toThrow(RuntimeException::class);
    } finally {
        AuditLog::setEventDispatcher($dispatcher);
    }
    expect($point->fresh()->area_node_id)->toBeNull();
});

test('archive save and delete vetoes roll back qr disabling and audit', function (string $event): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $auditCount = AuditLog::query()->count();
    $dispatcher = ServicePoint::getEventDispatcher();
    ServicePoint::setEventDispatcher(clone $dispatcher);
    ServicePoint::{$event}(fn (ServicePoint $candidate): bool => $candidate->id !== $point->id);
    try {
        expect(fn () => app(DeleteServicePointAction::class)->handle($this->floorActor, $this->floorBranch, $point))
            ->toThrow(RuntimeException::class);
    } finally {
        ServicePoint::setEventDispatcher($dispatcher);
    }
    expect($point->fresh()->is_active)->toBeTrue()->and($qr->fresh()->status)->toBe(QrCodeStatus::Active)
        ->and(AuditLog::query()->count())->toBe($auditCount);
})->with(['saving', 'deleting']);

test('create and restore refuse to report success after a required model write is vetoed', function (string $operation): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    $point->delete();
    $dispatcher = ServicePoint::getEventDispatcher();
    ServicePoint::setEventDispatcher(clone $dispatcher);
    ServicePoint::saving(fn (): bool => false);
    try {
        expect(fn () => $operation === 'create'
            ? app(CreateServicePointAction::class)->handle($this->floorBranch, floorPointPayload(), $this->floorActor)
            : app(RestoreServicePointAction::class)->handle($this->floorActor, $this->floorBranch, $point))
            ->toThrow(RuntimeException::class);
    } finally {
        ServicePoint::setEventDispatcher($dispatcher);
    }
    expect(ServicePoint::query()->count())->toBe(0);
})->with(['create', 'restore']);

test('direct physical table mutations reauthorize the supplied actor', function (string $operation): void {
    $stranger = User::factory()->create();
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    expect(fn () => match ($operation) {
        'create' => app(CreateServicePointAction::class)->handle($this->floorBranch, floorPointPayload(), $stranger),
        'update' => app(UpdateServicePointAction::class)->handle($point, floorPointPayload(), $stranger),
        'deactivate' => app(SetServicePointActiveAction::class)->handle($point, false, $stranger),
    })->toThrow(AuthorizationException::class);
    expect(ServicePoint::query()->count())->toBe(1)->and($point->fresh()->is_active)->toBeTrue();
})->with(['create', 'update', 'deactivate']);

test('physical table versions reject stale equal values after an intervening round trip', function (string $operation): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create(floorPointPayload());
    $version = $point->structure_version;
    $point->update(['name' => 'Intervening name']);
    $point->update(['name' => 'Physical table']);
    $point->refresh();
    expect(fn () => $operation === 'update'
        ? app(UpdateServicePointAction::class)->handle($point, floorPointPayload(), $this->floorActor, $version)
        : app(SetServicePointActiveAction::class)->handle($point, true, $this->floorActor, $version))
        ->toThrow(ValidationException::class);
    expect($point->fresh()->name)->toBe('Physical table')->and($point->fresh()->structure_version)->toBeGreaterThan($version);
})->with(['update', 'activity']);

test('physical creation replays the exact result but reauthorizes a lost response', function (): void {
    $requestId = (string) Str::uuid();
    $action = app(CreateServicePointAction::class);
    $point = $action->handle($this->floorBranch, floorPointPayload(), $this->floorActor, $requestId);
    $auditCount = AuditLog::query()->count();
    expect($action->handle($this->floorBranch, floorPointPayload(), $this->floorActor, $requestId)->id)->toBe($point->id)
        ->and(ServicePoint::query()->count())->toBe(1)->and(FloorOperation::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe($auditCount);
    expect(fn () => $action->handle($this->floorBranch, [...floorPointPayload(), 'name' => 'Changed intention'], $this->floorActor, $requestId))
        ->toThrow(ValidationException::class);
    $this->floorOrganization->memberships()->where('user_id', $this->floorActor->id)->update(['status' => 'suspended']);
    expect(fn () => $action->handle($this->floorBranch, floorPointPayload(), $this->floorActor, $requestId))
        ->toThrow(AuthorizationException::class);
    expect(ServicePoint::query()->count())->toBe(1);
});

/** @return array{area_node_id:null,type:string,prefix:string,from:int,to:int,capacity:int,icon:string,is_active:bool} */
function floorBulkPayload(): array
{
    return ['area_node_id' => null, 'type' => 'table', 'prefix' => 'F', 'from' => 1, 'to' => 3, 'capacity' => 4, 'icon' => 'squares-2x2', 'is_active' => true];
}

test('bulk confirmation binds the exact input and archived reservations', function (string $change): void {
    $action = app(BulkCreateServicePointsAction::class);
    $payload = floorBulkPayload();
    $preview = $action->previewState($this->floorBranch, $payload, $this->floorActor);
    if ($change === 'reserved') {
        $reserved = ServicePoint::factory()->for($this->floorBranch)->create(['internal_code' => 'F2']);
        $reserved->delete();
    } else {
        $payload['capacity'] = 6;
    }
    expect(fn () => $action->handle($this->floorBranch, $payload, $this->floorActor, (string) Str::uuid(), $preview['fingerprint']))
        ->toThrow(ValidationException::class);
    expect(ServicePoint::query()->count())->toBe(0)->and(FloorOperation::query()->count())->toBe(0);
})->with(['reserved', 'payload']);

test('bulk creation replays saved counts ids and audits after response loss', function (): void {
    $action = app(BulkCreateServicePointsAction::class);
    $preview = $action->previewState($this->floorBranch, floorBulkPayload(), $this->floorActor);
    $requestId = (string) Str::uuid();
    $result = $action->handle($this->floorBranch, floorBulkPayload(), $this->floorActor, $requestId, $preview['fingerprint']);
    $auditCount = AuditLog::query()->count();
    expect($action->handle($this->floorBranch, floorBulkPayload(), $this->floorActor, $requestId, $preview['fingerprint']))->toBe($result)
        ->and($result['created_count'])->toBe(3)->and(ServicePoint::query()->count())->toBe(3)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

test('bounded selected table reads include every unfinished session and share the filtered count', function (): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create(['name' => 'Find this table']);
    TableSession::factory()->forServicePoint($point)->create(['status' => TableSessionStatus::Paid]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    ServicePoint::factory()->for($this->floorBranch)->create(['name' => 'Other table']);
    $queries = app(ServicePointQueryService::class);
    $filters = ['search' => 'Find this', 'area_node_id' => 'all', 'type' => 'all', 'status' => 'all', 'active' => 'all', 'qr' => 'all'];
    expect($queries->count($this->floorBranch, $filters))->toBe(1);
    $selected = $queries->selected($this->floorBranch, [$point->id])->sole();
    expect($selected->activeTableSession->status)->toBe(TableSessionStatus::Paid)
        ->and($selected->activeQrCode->id)->toBe($qr->id)->and($selected->structure_version)->toBeInt();
    $other = ServicePoint::factory()->create();
    expect(fn () => $queries->selected($this->floorBranch, [$point->id, $other->id]))->toThrow(ValidationException::class);
});

test('selected table read rejects duplicate empty and oversized identities', function (array $ids): void {
    expect(fn () => app(ServicePointQueryService::class)->selected($this->floorBranch, $ids))->toThrow(ValidationException::class);
})->with([[[]], [[1, 1]], [range(1, 101)]]);

test('multiple table movement is atomic preserves qr identity and replays one saved result', function (): void {
    $points = ServicePoint::factory()->count(2)->for($this->floorBranch)->create();
    $area = AreaNode::factory()->for($this->floorBranch)->create();
    $qr = QrCode::factory()->forServicePoint($points->first())->active()->create();
    $token = $qr->public_token;
    $action = app(MoveServicePointsAction::class);
    $preview = $action->preview($this->floorActor, $this->floorBranch, $points->modelKeys(), $area->id);
    $requestId = (string) Str::uuid();
    $result = $action->handle($this->floorActor, $this->floorBranch, $points->modelKeys(), $area->id, $preview['versions'], $preview['fingerprint'], $requestId);
    expect($result['moved_count'])->toBe(2)->and($points->first()->fresh()->area_node_id)->toBe($area->id)
        ->and($points->last()->fresh()->area_node_id)->toBe($area->id)->and($qr->fresh()->public_token)->toBe($token);
    $auditCount = AuditLog::query()->count();
    expect($action->handle($this->floorActor, $this->floorBranch, $points->modelKeys(), $area->id, $preview['versions'], $preview['fingerprint'], $requestId))->toBe($result)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

test('multiple table movement refuses stale or busy selection without changing its other tables', function (string $change): void {
    $points = ServicePoint::factory()->count(2)->for($this->floorBranch)->create();
    $area = AreaNode::factory()->for($this->floorBranch)->create();
    $action = app(MoveServicePointsAction::class);
    $preview = $action->preview($this->floorActor, $this->floorBranch, $points->modelKeys(), $area->id);
    if ($change === 'busy') {
        TableSession::factory()->forServicePoint($points->last())->active()->create();
    } elseif ($change === 'area') {
        $area->update(['name' => 'Changed target area']);
    } else {
        $points->last()->update(['capacity' => 99]);
    }
    expect(fn () => $action->handle($this->floorActor, $this->floorBranch, $points->modelKeys(), $area->id, $preview['versions'], $preview['fingerprint'], (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($points->first()->fresh()->area_node_id)->toBeNull()->and($points->last()->fresh()->area_node_id)->toBeNull();
})->with(['busy', 'version', 'area']);

test('bulk and multiple movement roll back every mutation and receipt when audit is rejected', function (string $operation): void {
    $points = ServicePoint::factory()->for($this->floorBranch)->count(2)->create(['area_node_id' => null]);
    $target = AreaNode::factory()->for($this->floorBranch)->create();
    $move = app(MoveServicePointsAction::class);
    $preview = $move->preview($this->floorActor, $this->floorBranch, $points->modelKeys(), $target->id);
    AuditLog::saving(fn (): bool => false);
    expect(fn () => $operation === 'bulk'
        ? app(BulkCreateServicePointsAction::class)->handle($this->floorBranch, floorBulkPayload(), $this->floorActor, (string) Str::uuid())
        : $move->handle($this->floorActor, $this->floorBranch, $points->modelKeys(), $target->id, $preview['versions'], $preview['fingerprint'], (string) Str::uuid()))->toThrow(RuntimeException::class);
    expect(ServicePoint::query()->count())->toBe(2)->and(ServicePoint::query()->whereNotNull('area_node_id')->count())->toBe(0)
        ->and(FloorOperation::query()->count())->toBe(0)
        ->and(ServicePoint::query()->pluck('structure_version')->all())->toBe([0, 0]);
})->with(['bulk', 'move']);

test('multiple movement rejects foreign and missing identities and an archived destination', function (string $change): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    $area = AreaNode::factory()->for($this->floorBranch)->create();
    $ids = [$point->id];
    if ($change === 'foreign point') {
        $ids[] = ServicePoint::factory()->create()->id;
    }
    if ($change === 'missing point') {
        $ids[] = $point->id + 999;
    }
    if ($change === 'foreign area') {
        $area = AreaNode::factory()->create();
    }
    if ($change === 'archived area') {
        $area->delete();
    }
    expect(fn () => app(MoveServicePointsAction::class)->preview($this->floorActor, $this->floorBranch, $ids, $area->id))->toThrow(ValidationException::class);
    expect($point->fresh()->area_node_id)->toBeNull()->and(FloorOperation::query()->count())->toBe(0);
})->with(['foreign point', 'missing point', 'foreign area', 'archived area']);

test('a linked active order blocks restructuring even after its primary session closed', function (): void {
    $primary = ServicePoint::factory()->for($this->floorBranch)->create();
    $linked = ServicePoint::factory()->for($this->floorBranch)->create();
    $session = TableSession::factory()->forServicePoint($primary)->closed()->create();
    TableSessionServicePoint::factory()->forTableSessionAndServicePoint($session, $linked)->create();
    $order = Order::factory()->forTableSession($session)->paymentRequested()->create();
    try {
        app(DeleteServicePointAction::class)->handle($this->floorActor, $this->floorBranch, $linked, 0);
        $this->fail('The unfinished linked order must block archiving.');
    } catch (\App\Exceptions\BusinessRuleViolation $exception) {
        expect($exception->businessRule())->toBe(\App\Enums\BusinessRuleCode::StructureHasActiveOrder);
    }
    expect($linked->fresh())->not->toBeNull()->and($order->fresh()->status)->toBe($order->status);
});

test('restoring a legacy active archive cannot reopen the table or its qr', function (): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create(['is_active' => true]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $token = $qr->public_token;
    $point->delete();
    app(RestoreServicePointAction::class)->handle($this->floorActor, $this->floorBranch, $point, $point->structure_version);
    expect($point->fresh()->is_active)->toBeFalse()->and($point->fresh()->status)->toBe(ServicePointStatus::Closed)
        ->and($qr->fresh()->status)->toBe(QrCodeStatus::Disabled)->and($qr->fresh()->public_token)->toBe($token)
        ->and(QrCode::query()->count())->toBe(1);
});

test('independent sqlite table writers preserve one receipt or reject the obsolete preview', function (string $kind): void {
    $path = tempnam(sys_get_temp_dir(), 'floor-point-concurrency-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'floor_point_concurrency', 'database.connections.floor_point_concurrency' => $connection]);
        DB::purge('floor_point_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'floor_point_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $actor = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Concurrent tables']);
        $branch = Branch::factory()->for($organization)->create();
        $commands = [];
        if ($kind === 'same request' || $kind === 'overlapping bulk') {
            $preview = app(BulkCreateServicePointsAction::class)->previewState($branch, floorBulkPayload(), $actor);
            $command = ['data' => floorBulkPayload(), 'request_id' => (string) Str::uuid(), 'fingerprint' => $preview['fingerprint']];
            $commands = [$command, $kind === 'same request' ? $command : [...$command, 'request_id' => (string) Str::uuid()]];
        } else {
            $point = ServicePoint::factory()->for($branch)->create();
            $areas = AreaNode::factory()->for($branch)->count(2)->create();
            foreach ($areas as $area) {
                $preview = app(MoveServicePointsAction::class)->preview($actor, $branch, [$point->id], $area->id);
                $commands[] = ['ids' => [$point->id], 'target' => $area->id, 'versions' => $preview['versions'], 'fingerprint' => $preview['fingerprint'], 'request_id' => (string) Str::uuid()];
            }
        }
        $tasks = array_map(fn (array $command) => FloorServicePointConcurrencyTasks::write($connection, $actor->id, $branch->id, $kind === 'move' ? 'move' : 'bulk', $command), $commands);
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'floor_point_concurrency']);
        DB::purge('floor_point_concurrency');
        $states = array_column($results, 'result');
        sort($states);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and($states)->toBe($kind === 'same request' ? ['saved', 'saved'] : ['conflict', 'saved'])
            ->and(FloorOperation::query()->count())->toBe(1)
            ->and(ServicePoint::query()->count())->toBe($kind === 'move' ? 1 : 3)
            ->and(AuditLog::query()->where('entity_type', 'service_point')->count())->toBe($kind === 'move' ? 1 : 3);
        if ($kind === 'same request') {
            expect($results[0]['value'])->toBe($results[1]['value']);
        }
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('floor_point_concurrency');
        DB::purge('floor_point_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
})->with(['same request', 'overlapping bulk', 'move']);

test('bounded table selection and pagination do not load unrelated restaurant tables', function (): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create(['name' => 'Needle']);
    QrCode::factory()->forServicePoint($point)->active()->create();
    TableSession::factory()->forServicePoint($point)->paymentRequested()->create();
    $queries = app(ServicePointQueryService::class);
    $filters = ['search' => 'Needle', 'area_node_id' => 'all', 'type' => 'all', 'status' => 'all', 'active' => 'all', 'qr' => 'all'];
    $before = countDatabaseQueries(function () use ($queries, $filters, $point): void {
        expect($queries->selected($this->floorBranch, [$point->id]))->toHaveCount(1);
        expect($queries->paginate($this->floorBranch, $filters, 20)->items())->toHaveCount(1);
    });
    ServicePoint::factory()->for($this->floorBranch)->count(250)->create(['name' => 'Unrelated']);
    $after = countDatabaseQueries(function () use ($queries, $filters, $point): void {
        expect($queries->selected($this->floorBranch, [$point->id]))->toHaveCount(1);
        expect($queries->paginate($this->floorBranch, $filters, 20)->items())->toHaveCount(1);
    });
    expect($after)->toBe($before)->toBeLessThanOrEqual(12);
});

test('table safety errors use the active language without English fallback', function (string $locale, string $operation): void {
    app()->setLocale($locale);
    $point = ServicePoint::factory()->for($this->floorBranch)->create();
    if ($operation === 'occupied') {
        TableSession::factory()->forServicePoint($point)->active()->create();
    }
    if ($operation === 'version') {
        $point->update(['name' => 'Intervening']);
    }
    $key = $operation === 'occupied' ? 'floor.errors.active_session' : 'floor.errors.structure_changed';
    try {
        app(SetServicePointActiveAction::class)->handle($point, false, $this->floorActor, 0);
        $this->fail('The unsafe write must be rejected.');
    } catch (ValidationException $exception) {
        $message = array_values($exception->errors())[0][0];
        expect($message)->toBe(__($key))->not->toBe($key);
        if ($locale !== 'en') {
            expect($message)->not->toBe(Lang::get($key, [], 'en'));
        }
    }
})->with(['en', 'lt', 'ru'])->with(['occupied', 'version']);

test('table actions reject malformed original input before persistence', function (string $operation, string $field, mixed $value): void {
    $point = ServicePoint::factory()->for($this->floorBranch)->create(floorPointPayload());
    $data = [...floorPointPayload(), $field => $value];
    expect(fn () => $operation === 'create'
        ? app(CreateServicePointAction::class)->handle($this->floorBranch, $data, $this->floorActor)
        : app(UpdateServicePointAction::class)->handle($point, $data, $this->floorActor, 0))->toThrow(ValidationException::class);
    expect(ServicePoint::query()->count())->toBe(1)->and($point->fresh()->name)->toBe('Physical table')
        ->and($point->fresh()->structure_version)->toBe(0);
})->with(['create', 'update'])->with([
    'blank name' => ['name', '   '], 'enum' => ['type', ['table']], 'float' => ['capacity', 3.5],
    'boolean id' => ['area_node_id', false], 'flag' => ['is_active', 'no'],
]);

test('table rows retain their archived area identity and label', function (): void {
    $area = AreaNode::factory()->for($this->floorBranch)->create(['name' => 'Archived terrace']);
    $point = ServicePoint::factory()->for($this->floorBranch)->for($area, 'areaNode')->create();
    $area->delete();
    $selected = app(ServicePointQueryService::class)->selected($this->floorBranch, [$point->id])->sole();
    expect($selected->area_node_id)->toBe($area->id)->and($selected->areaNode)->not->toBeNull()
        ->and($selected->areaNode->name)->toBe('Archived terrace')->and($selected->areaNode->trashed())->toBeTrue();
});

test('bulk actions reject malformed original input before generating ranges or saving', function (string $field, mixed $value): void {
    expect(fn () => app(BulkCreateServicePointsAction::class)->handle($this->floorBranch, [...floorBulkPayload(), $field => $value], $this->floorActor))->toThrow(ValidationException::class);
    expect(ServicePoint::query()->count())->toBe(0)->and(FloorOperation::query()->count())->toBe(0);
})->with(['prefix' => ['prefix', ['F']], 'enum' => ['type', 'foreign'], 'capacity' => ['capacity', 3.5], 'start' => ['from', []], 'flag' => ['is_active', 'no']]);
