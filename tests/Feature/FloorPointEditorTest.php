<?php

declare(strict_types=1);

use App\Actions\ServicePoints\MoveServicePointsAction;
use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\BulkCreate;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PointEditor;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FloorOperation;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->pointBranch = Branch::factory()->create();
    $this->pointActor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->pointBranch->organization)
        ->forUser($this->pointActor)->forSystemRole(SystemRole::Owner)->active()->create();
});

test('the property editor rejects a hidden area change without discarding the typed properties', function (string $locale, bool $removeArea): void {
    app()->setLocale($locale);
    $area = AreaNode::factory()->for($this->pointBranch)->create();
    $otherArea = AreaNode::factory()->for($this->pointBranch)->create();
    $point = ServicePoint::factory()->for($this->pointBranch)->for($area)->create();
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $before = $point->fresh()->getRawOriginal();
    $qrBefore = $qr->fresh()->getRawOriginal();
    $auditCount = AuditLog::query()->count();

    Livewire::actingAs($this->pointActor)->test(PointEditor::class, ['branchId' => $this->pointBranch->id, 'pointId' => $point->id])
        ->update(calls: [['method' => 'save', 'params' => [], 'path' => '']], updates: [
            'form.areaNodeId' => $removeArea ? '' : (string) $otherArea->id,
            'form.name' => 'Unsaved terrace table', 'form.displayNumber' => '01', 'form.capacity' => '7',
        ])
        ->assertHasErrors(['form.areaNodeId' => __('floor.move_separately')])
        ->assertSet('form.name', 'Unsaved terrace table')
        ->assertSet('form.displayNumber', '01')->assertSet('form.capacity', '7')
        ->assertNotDispatched('floor-saved');

    expect($point->fresh()->getRawOriginal())->toBe($before)
        ->and($qr->fresh()->getRawOriginal())->toBe($qrBefore)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(FloorOperation::query()->count())->toBe(0);
})->with(['en', 'lt', 'ru'])->with(['different area' => false, 'no area' => true]);

test('an entirely reserved bulk range reports zero creation without replacing the current selection', function (): void {
    $existing = ServicePoint::factory()->for($this->pointBranch)->create(['internal_code' => 'T1']);
    $archived = ServicePoint::factory()->for($this->pointBranch)->create(['internal_code' => 'T2', 'deleted_at' => now()]);
    $before = [$existing->fresh()->getRawOriginal(), $archived->fresh()->getRawOriginal()];
    $auditCount = AuditLog::query()->count();
    $component = Livewire::actingAs($this->pointActor)->test(BulkCreate::class, ['branchId' => $this->pointBranch->id])
        ->set('form.bulkPrefix', 'T')->set('form.bulkFrom', '1')->set('form.bulkTo', '2')
        ->call('review')->assertHasNoErrors()->assertOk()
        ->call('apply')->assertHasNoErrors()->assertOk()
        ->assertSet('result.created_count', 0)->assertSet('result.skipped_count', 2)->assertSet('result.created_ids', [])
        ->assertSee(__('floor.bulk_no_new_tables'))->assertDontSee(__('floor.bulk_next'))
        ->assertNotDispatched('floor-bulk-created')->assertDispatched('floor-saved');

    $firstResult = $component->get('result');
    $component->call('apply')->assertHasNoErrors()->assertSet('result', $firstResult)
        ->assertNotDispatched('floor-bulk-created');

    expect([$existing->fresh()->getRawOriginal(), $archived->fresh()->getRawOriginal()])->toBe($before)
        ->and(ServicePoint::withTrashed()->where('branch_id', $this->pointBranch->id)->count())->toBe(2)
        ->and(FloorOperation::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(QrCode::query()->count())->toBe(0);
});

test('the property editor preserves the physical identity and text number when saving another property', function (ServicePointType $type, string $number): void {
    $area = AreaNode::factory()->for($this->pointBranch)->create();
    $point = ServicePoint::factory()->for($this->pointBranch)->for($area)->create(['type' => $type, 'display_number' => $number, 'capacity' => 8]);
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $identity = $point->only(['id', 'internal_code', 'area_node_id', 'type', 'display_number', 'capacity']);
    $qrBefore = $qr->fresh()->getRawOriginal();

    Livewire::actingAs($this->pointActor)->test(PointEditor::class, ['branchId' => $this->pointBranch->id, 'pointId' => $point->id])
        ->set('form.name', 'Updated physical name')->call('save')->assertHasNoErrors()->assertDispatched('floor-saved');

    expect($point->fresh()->only(array_keys($identity)))->toBe($identity)
        ->and($point->fresh()->name)->toBe('Updated physical name')
        ->and($qr->fresh()->getRawOriginal())->toBe($qrBefore);
})->with([[ServicePointType::HotelRoom, '01'], [ServicePointType::BarSeat, 'A-4']]);

test('a concurrent move keeps its version conflict and the previous editor retains unsaved input', function (): void {
    $area = AreaNode::factory()->for($this->pointBranch)->create();
    $target = AreaNode::factory()->for($this->pointBranch)->create();
    $point = ServicePoint::factory()->for($this->pointBranch)->for($area)->create();
    $component = Livewire::actingAs($this->pointActor)->test(PointEditor::class, ['branchId' => $this->pointBranch->id, 'pointId' => $point->id]);
    $move = app(MoveServicePointsAction::class);
    $preview = $move->preview($this->pointActor, $this->pointBranch, [$point->id], $target->id);
    $move->handle($this->pointActor, $this->pointBranch, [$point->id], $target->id, $preview['versions'], $preview['fingerprint'], (string) Str::uuid());
    $before = $point->fresh()->getRawOriginal();
    $auditCount = AuditLog::query()->count();

    $component->set('form.name', 'Still unsaved')->call('save')
        ->assertHasErrors(['expectedVersion' => __('floor.errors.structure_changed')])
        ->assertHasNoErrors(['form.areaNodeId'])->assertSet('form.name', 'Still unsaved')->assertNotDispatched('floor-saved');

    expect($point->fresh()->getRawOriginal())->toBe($before)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

test('a real floor editor snapshot rejects another authorized account before mutation', function (string $method): void {
    $point = ServicePoint::factory()->for($this->pointBranch)->create();
    $secondActor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->pointBranch->organization)
        ->forUser($secondActor)->forSystemRole(SystemRole::Owner)->active()->create();
    $parameters = ['organization' => $this->pointBranch->organization_id, 'brand' => $this->pointBranch->brand_id,
        'branch' => $this->pointBranch->id, 'point' => (string) $point->id];
    $response = $this->actingAs($this->pointActor)->get(route('organizations.brands.branches.service-points.index', $parameters))->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
    $name = app('livewire.factory')->resolveComponentName(PointEditor::class);
    $snapshot = collect($matches[1])->map(fn (string $encoded): string => html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5))
        ->first(fn (string $value): bool => json_decode($value, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $name);
    expect($snapshot)->toBeString()->not->toBeEmpty();
    expect(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['memo']['workspaceActor'])->toBe($this->pointActor->id);
    $before = $point->fresh()->getRawOriginal();
    $auditCount = AuditLog::query()->count();

    $this->actingAs($secondActor)->postJson(route('default-livewire.update'), ['components' => [[
        'snapshot' => $snapshot, 'updates' => ['form.name' => 'Different account write', 'confirmArchive' => true],
        'calls' => [['method' => $method, 'params' => []]],
    ]]], ['X-Livewire' => ''])->assertStatus(409);

    expect($point->fresh()->getRawOriginal())->toBe($before)->and(AuditLog::query()->count())->toBe($auditCount);
})->with(['save', 'archive']);

test('table management can archive and restore safely without room QR or session opening permissions', function (): void {
    $staff = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->pointBranch->organization)
        ->forUser($staff)->forSystemRole(SystemRole::Marketer)->active()->create();
    PermissionUserOverride::factory()->forUser($staff)->forOrganization($this->pointBranch->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ManageServicePoints->value)->firstOrFail())->allowed()->create();
    $point = ServicePoint::factory()->for($this->pointBranch)->create();
    $qr = QrCode::factory()->forServicePoint($point)->active()->create();
    $identity = $point->only(['id', 'internal_code', 'area_node_id']);
    $qrIdentity = $qr->only(['id', 'public_token', 'short_code']);
    expect(Gate::forUser($staff)->allows('manageServicePoints', $this->pointBranch))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('manageZones', $this->pointBranch))->toBeFalse()
        ->and(Gate::forUser($staff)->allows('generateQr', $point))->toBeFalse()
        ->and(Gate::forUser($staff)->allows('openTable', $point))->toBeFalse();
    expect(fn () => app(OpenTableSessionForServicePointAction::class)->handle($point, $staff))->toThrow(AuthorizationException::class);

    $editor = Livewire::actingAs($staff)->test(PointEditor::class, ['branchId' => $this->pointBranch->id, 'pointId' => $point->id])
        ->set('confirmArchive', true)->call('archive')->assertHasNoErrors();
    expect($point->fresh()->trashed())->toBeTrue()->and($qr->fresh()->status)->toBe(QrCodeStatus::Disabled);
    $editor->call('restore')->assertHasNoErrors();
    expect($point->fresh()->trashed())->toBeFalse()->and($point->fresh()->is_active)->toBeFalse()
        ->and($point->fresh()->status)->toBe(ServicePointStatus::Closed)
        ->and($point->fresh()->only(array_keys($identity)))->toBe($identity)
        ->and($qr->fresh()->status)->toBe(QrCodeStatus::Disabled)
        ->and($qr->fresh()->only(array_keys($qrIdentity)))->toBe($qrIdentity);
});

test('changing bulk input in the confirmation request invalidates the preview and retains that input', function (): void {
    $component = Livewire::actingAs($this->pointActor)->test(BulkCreate::class, ['branchId' => $this->pointBranch->id])
        ->set('form.bulkPrefix', 'T')->set('form.bulkFrom', '1')->set('form.bulkTo', '2')
        ->call('review')->assertHasNoErrors()->assertOk();
    $requestId = $component->get('requestId');

    $component->update(calls: [['method' => 'apply', 'params' => [], 'path' => '']], updates: ['form.bulkCapacity' => '8'])
        ->assertHasErrors(['form.bulkPrefix' => __('floor.review_required')])
        ->assertSet('form.bulkCapacity', '8')->assertSet('preview', [])->assertSet('fingerprint', '')->assertSet('result', null)
        ->assertNotDispatched('floor-bulk-created')->assertNotDispatched('floor-saved');

    expect($component->get('requestId'))->not->toBe($requestId)
        ->and(ServicePoint::query()->count())->toBe(0)->and(FloorOperation::query()->count())->toBe(0);
});
