<?php

declare(strict_types=1);

use App\Actions\Onboarding\ContinueRestaurantPreparationAction;
use App\Actions\Onboarding\CreateRestaurantSetupAction;
use App\Actions\Onboarding\UseExistingSetupSpaceAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\ServicePoints\BulkCreateServicePointsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\ServicePointType;
use App\Models\AreaNode;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Onboarding\RestaurantSetupQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Preserved business']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->withDefaultSettings()->withDepartments(2)->create();
    $this->setup = app(ContinueRestaurantPreparationAction::class)->handle($this->actor, $this->branch);
});

it('rolls back the whole creation graph when a required child refuses persistence and safely retries its receipt', function (string $child, bool $first): void {
    $actor = $first ? User::factory()->create() : $this->actor;
    $models = [Organization::class, OrganizationUser::class, OrganizationSubscription::class, Brand::class, Branch::class, BranchSetting::class, KitchenDepartment::class, RestaurantOnboarding::class, AuditLog::class];
    $before = array_map(fn (string $model): array => $model::query()->orderBy('id')->get()->map->getAttributes()->all(), $models);
    $rolesBefore = $actor->roles()->pluck('roles.id')->all();
    $data = [
        'organizationId' => $first ? null : $this->organization->id,
        'brandId' => $first ? null : $this->brand->id,
        'organizationName' => 'New business', 'brandName' => 'New brand', 'branchName' => 'New restaurant',
        'branchAddress' => 'Example 12', 'branchCity' => 'Vilnius', 'branchCountryCode' => 'LT',
        'branchTimezone' => 'Europe/Vilnius', 'branchCurrency' => 'EUR',
    ];
    $key = (string) Str::uuid();
    $veto = true;
    if ($child === 'settings') {
        BranchSetting::creating(function () use (&$veto): ?bool {
            return $veto ? false : null;
        });
    } else {
        KitchenDepartment::creating(function (KitchenDepartment $department) use (&$veto, $child): ?bool {
            return $veto && $department->type->value === $child ? false : null;
        });
    }
    $action = app(CreateRestaurantSetupAction::class);
    expect(fn () => $action->handle($actor, $data, $key))->toThrow(RuntimeException::class);
    expect(array_map(fn (string $model): array => $model::query()->orderBy('id')->get()->map->getAttributes()->all(), $models))->toBe($before)
        ->and($actor->roles()->pluck('roles.id')->all())->toBe($rolesBefore);
    $veto = false;
    $created = $action->handle($actor, $data, $key);
    expect($action->handle($actor, $data, $key)->id)->toBe($created->id)
        ->and($created->branch->settings()->count())->toBe(1)
        ->and($created->branch->kitchenDepartments()->orderBy('sort_order')->pluck('type')->all())->toBe([
            KitchenDepartmentType::Kitchen, KitchenDepartmentType::Bar, KitchenDepartmentType::Dessert, KitchenDepartmentType::Hookah,
        ])
        ->and($created->branch->is_active)->toBeFalse()
        ->and($created->completed_at)->toBeNull()
        ->and($this->branch->fresh()->getAttributes())->toBe($before[4][0])
        ->and($this->setup->fresh()->getAttributes())->toBe($before[7][0]);
})->with(['settings', 'kitchen', 'bar'])->with([true, false]);

it('does not create an inaccessible restaurant or grant branch access to an assigned creator', function (): void {
    $assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->create();
    $assignmentBefore = $assignment->fresh()->getAttributes();
    $rolesBefore = $this->actor->roles()->pluck('roles.id')->all();
    $before = [Branch::query()->count(), Brand::query()->count(), RestaurantOnboarding::query()->count(), AuditLog::query()->count()];
    $data = ['organizationId' => $this->organization->id, 'brandId' => null,
        'organizationName' => '', 'brandName' => 'New brand', 'branchName' => 'Inaccessible restaurant',
        'branchAddress' => 'Example 12', 'branchCity' => 'Vilnius', 'branchCountryCode' => 'LT',
        'branchTimezone' => 'Europe/Vilnius', 'branchCurrency' => 'EUR'];
    expect(fn () => app(CreateRestaurantSetupAction::class)->handle($this->actor, $data, (string) Str::uuid()))->toThrow(AuthorizationException::class);
    expect([Branch::query()->count(), Brand::query()->count(), RestaurantOnboarding::query()->count(), AuditLog::query()->count()])->toBe($before)
        ->and($assignment->fresh()->getAttributes())->toBe($assignmentBefore)
        ->and(BranchUser::query()->count())->toBe(1)
        ->and($this->actor->roles()->pluck('roles.id')->all())->toBe($rolesBefore)
        ->and(app(ContinueRestaurantPreparationAction::class)->handle($this->actor, $this->branch)->id)->toBe($this->setup->id);
});

it('connects only current tables from a mixed room without changing room service point or QR data', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create(['is_active' => false]);
    $tables = ServicePoint::factory()->count(2)->for($this->branch)->withQr()->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table, 'is_active' => false]);
    ServicePoint::factory()->count(21)->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::BarSeat]);
    ServicePoint::factory()->for($this->branch)->archived()->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    ServicePoint::factory()->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    $before = ServicePoint::withTrashed()->orderBy('id')->get()->map->getAttributes()->all();
    $qrBefore = $tables->map(fn (ServicePoint $point): array => $point->qrCodes()->firstOrFail()->getAttributes())->all();
    $areaBefore = $area->fresh()->getAttributes();
    $setup = app(UseExistingSetupSpaceAction::class)->handle($this->actor, $this->setup->id, $area->id, 0);
    $points = $setup->servicePoints()->get();
    expect($points->modelKeys())->toBe($tables->modelKeys())
        ->and($setup->expected_service_point_count)->toBe(2)
        ->and($setup->hasCompleteServicePointSet($points, $this->branch->id, $area->id, 2))->toBeTrue()
        ->and(ServicePoint::withTrashed()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before)
        ->and($tables->map(fn (ServicePoint $point): array => $point->qrCodes()->firstOrFail()->getAttributes())->all())->toBe($qrBefore)
        ->and($area->fresh()->getAttributes())->toBe($areaBefore);
});

it('explicitly reconnects an initially empty room using its current version and makes identical replay a no-op', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $action = app(UseExistingSetupSpaceAction::class);
    $empty = $action->handle($this->actor, $this->setup->id, $area->id, 0);
    $table = ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    expect(fn () => $action->handle($this->actor, $this->setup->id, $area->id, 0))->toThrow(ValidationException::class);
    expect($empty->fresh()->setup_version)->toBe(1)->and($empty->servicePoints()->count())->toBe(0);
    $connected = $action->handle($this->actor, $this->setup->id, $area->id, 1);
    $before = $connected->fresh()->getAttributes();
    $links = $connected->servicePoints()->get()->map(fn (ServicePoint $point): array => $point->pivot->getAttributes())->all();
    $audits = AuditLog::query()->count();
    $this->travel(1)->minutes();
    $replayed = $action->handle($this->actor, $this->setup->id, $area->id, 1);
    expect($connected->setup_version)->toBe(2)->and($connected->expected_service_point_count)->toBe(1)
        ->and($connected->servicePoints()->pluck('service_points.id')->all())->toBe([$table->id])
        ->and($replayed->getAttributes())->toBe($before)
        ->and($replayed->servicePoints()->get()->map(fn (ServicePoint $point): array => $point->pivot->getAttributes())->all())->toBe($links)
        ->and(AuditLog::query()->count())->toBe($audits);
});

it('repairs only checkpoint links after tables are archived moved or changed to another type', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $otherArea = AreaNode::factory()->for($this->branch)->create();
    $tables = ServicePoint::factory()->count(4)->for($this->branch)->withQr()->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    $action = app(UseExistingSetupSpaceAction::class);
    $action->handle($this->actor, $this->setup->id, $area->id, 0);
    $this->setup->forceFill(['completed_at' => now()->subMonth()])->save();
    $completedAt = $this->setup->fresh()->completed_at;
    $tables[0]->delete();
    $tables[1]->update(['area_node_id' => $otherArea->id]);
    $tables[2]->update(['type' => ServicePointType::VipTable]);
    $before = ServicePoint::withTrashed()->orderBy('id')->get()->map->getAttributes()->all();
    $qrBefore = $tables->map(fn (ServicePoint $point): array => $point->qrCodes()->get()->map->getAttributes()->all())->all();
    $setup = $action->handle($this->actor, $this->setup->id, $area->id, 1);
    $points = $setup->servicePoints()->get();
    expect($points->modelKeys())->toBe([$tables[3]->id])
        ->and($setup->setup_version)->toBe(2)->and($setup->expected_service_point_count)->toBe(1)
        ->and($setup->completed_at->equalTo($completedAt))->toBeTrue()
        ->and($setup->hasCompleteServicePointSet($points, $this->branch->id, $area->id, 1))->toBeTrue()
        ->and(ServicePoint::withTrashed()->orderBy('id')->get()->map->getAttributes()->all())->toBe($before)
        ->and($tables->map(fn (ServicePoint $point): array => $point->qrCodes()->get()->map->getAttributes()->all())->all())->toBe($qrBefore);
});

it('repairs a legacy mixed checkpoint without restoring archived resources', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $seat = ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::BarSeat]);
    $table = ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    $this->setup->forceFill(['area_node_id' => $area->id, 'expected_service_point_count' => 2])->save();
    $this->setup->servicePoints()->attach([$seat->id => ['position' => 1], $table->id => ['position' => 2]]);
    $setup = app(UseExistingSetupSpaceAction::class)->handle($this->actor, $this->setup->id, $area->id, 0);
    expect($setup->servicePoints()->pluck('service_points.id')->all())->toBe([$table->id])
        ->and($setup->servicePoints()->firstOrFail()->pivot->position)->toBe(1)
        ->and($setup->expected_service_point_count)->toBe(1)->and($setup->setup_version)->toBe(1);
});

it('bounds table refresh before changing an already connected checkpoint', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $action = app(UseExistingSetupSpaceAction::class);
    $action->handle($this->actor, $this->setup->id, $area->id, 0);
    ServicePoint::factory()->count(BulkCreateServicePointsAction::MAX_RANGE_SIZE + 1)->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    $before = $this->setup->fresh()->getAttributes();
    expect(fn () => $action->handle($this->actor, $this->setup->id, $area->id, 1))->toThrow(ValidationException::class);
    expect($this->setup->fresh()->getAttributes())->toBe($before)->and($this->setup->servicePoints()->count())->toBe(0);
});

it('rolls back link replacement if the checkpoint write is vetoed', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $original = ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    $action = app(UseExistingSetupSpaceAction::class);
    $action->handle($this->actor, $this->setup->id, $area->id, 0);
    $original->delete();
    ServicePoint::factory()->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    $before = $this->setup->fresh()->getAttributes();
    $links = $this->setup->servicePoints()->withTrashed()->get()->map(fn (ServicePoint $point): array => $point->pivot->getAttributes())->all();
    RestaurantOnboarding::updating(fn (): bool => false);
    expect(fn () => $action->handle($this->actor, $this->setup->id, $area->id, 1))->toThrow(RuntimeException::class);
    expect($this->setup->fresh()->getAttributes())->toBe($before)
        ->and($this->setup->servicePoints()->withTrashed()->get()->map(fn (ServicePoint $point): array => $point->pivot->getAttributes())->all())->toBe($links);
});

it('presents a connected room at the existing allocation bound without truncating its progress', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    ServicePoint::factory()->count(BulkCreateServicePointsAction::MAX_RANGE_SIZE)->for($this->branch)->create(['area_node_id' => $area->id, 'type' => ServicePointType::Table]);
    $setup = app(UseExistingSetupSpaceAction::class)->handle($this->actor, $this->setup->id, $area->id, 0);
    $presentation = app(RestaurantSetupQueryService::class)->presentation($this->actor, $setup->id);
    expect($setup->fresh()->expected_service_point_count)->toBe(BulkCreateServicePointsAction::MAX_RANGE_SIZE)
        ->and($presentation['done'][5])->toBeTrue()
        ->and($presentation['summary']['service_points'])->toBe(BulkCreateServicePointsAction::MAX_RANGE_SIZE)
        ->and($presentation['form']['tableCount'])->toBe(BulkCreateServicePointsAction::MAX_RANGE_SIZE);
});

it('reauthorizes an identical room replay after membership revocation', function (): void {
    $area = AreaNode::factory()->for($this->branch)->create();
    $action = app(UseExistingSetupSpaceAction::class);
    $action->handle($this->actor, $this->setup->id, $area->id, 0);
    $before = $this->setup->fresh()->getAttributes();
    $this->organization->memberships()->where('user_id', $this->actor->id)->update(['status' => 'suspended']);
    expect(fn () => $action->handle($this->actor, $this->setup->id, $area->id, 0))->toThrow(AuthorizationException::class);
    expect($this->setup->fresh()->getAttributes())->toBe($before);
});

it('rejects an archived parent brand before creating or replaying a private checkpoint', function (bool $existing): void {
    $branch = $existing ? $this->branch : Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->brand->delete();
    $before = RestaurantOnboarding::query()->get()->map->getAttributes()->all();
    $branchBefore = $branch->fresh()->getAttributes();
    expect(fn () => app(ContinueRestaurantPreparationAction::class)->handle($this->actor, $branch))->toThrow(ModelNotFoundException::class);
    expect(RestaurantOnboarding::query()->get()->map->getAttributes()->all())->toBe($before)
        ->and($branch->fresh()->getAttributes())->toBe($branchBefore);
})->with([true, false]);
