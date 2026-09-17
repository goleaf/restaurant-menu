<?php

declare(strict_types=1);

use App\Actions\Brands\DeleteBrandAction;
use App\Actions\Brands\RestoreBrandAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\DeleteOrganizationAction;
use App\Actions\Organizations\RestoreOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Restored organization']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create(['is_active' => true]);
    $this->archivedBranch = Branch::factory()->for($this->organization)->for($this->brand)->archived()->create(['is_active' => true]);
    $otherBrand = Brand::factory()->for($this->organization)->create();
    $this->sibling = Branch::factory()->for($this->organization)->for($otherBrand)->create(['is_active' => true]);
    $this->staff = User::factory()->create();
    $this->membership = OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->staff)
        ->forSystemRole(SystemRole::Waiter)->create(['access_version' => 5]);
    BranchUser::factory()->forBranch($this->branch)->forUser($this->staff)->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->suspended()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->removed()->create();
});

it('restores only the parent while preserving content history owner access and explicit staff restrictions', function (string $kind): void {
    $point = ServicePoint::factory()->for($this->branch)->withQr()->create(['is_active' => false]);
    $menu = Menu::factory()->for($this->branch)->active()->create(['sort_order' => 17]);
    $category = MenuCategory::factory()->for($menu)->withTranslations()->create(['sort_order' => 9]);
    MenuItem::factory()->for($menu)->for($category, 'category')->withTranslations()->create(['is_available' => false, 'image' => 'fixtures/preserved.png']);
    RestaurantOnboarding::factory()->for($this->owner, 'user')->for($this->organization)->for($this->brand)->for($this->branch)
        ->create(['completed_at' => now()->subMonth()]);
    $foreign = Branch::factory()->create(['is_active' => true]);
    $preservedModels = [Menu::class, MenuCategory::class, MenuItem::class, ServicePoint::class, QrCode::class, RestaurantOnboarding::class, BranchUser::class];
    $preserved = array_map(fn (string $model): array => $model::query()->orderBy('id')->get()->map->getAttributes()->all(), $preservedModels);
    $memberships = OrganizationUser::query()->where('organization_id', $this->organization->id)->orderBy('id')->get()->keyBy('id');
    $foreignBefore = $foreign->fresh()->getAttributes();
    $siblingBefore = $this->sibling->fresh()->getAttributes();

    restaurantStructureArchiveParent($kind, $this->owner, $this->organization, $this->brand);
    restaurantStructureRestoreParent($kind, $this->owner, $this->organization, $this->brand);

    expect($this->organization->fresh()->trashed())->toBeFalse()->and($this->brand->fresh()->trashed())->toBeFalse()
        ->and($this->branch->fresh()->is_active)->toBeFalse()->and($this->archivedBranch->fresh()->is_active)->toBeFalse()
        ->and($this->archivedBranch->fresh()->trashed())->toBeTrue()
        ->and($this->owner->fresh()->canAccessOrganization($this->organization->fresh()))->toBeTrue()
        ->and($foreign->fresh()->getAttributes())->toBe($foreignBefore)
        ->and(array_map(fn (string $model): array => $model::query()->orderBy('id')->get()->map->getAttributes()->all(), $preservedModels))->toBe($preserved);
    if ($kind === 'organization') {
        expect($this->sibling->fresh()->is_active)->toBeFalse()
            ->and($this->membership->fresh()->status)->toBe(OrganizationUserStatus::Suspended)
            ->and($this->membership->fresh()->access_version)->toBe(6)
            ->and($this->staff->fresh()->canAccessOrganization($this->organization->fresh()))->toBeFalse()
            ->and(AuditLog::query()->where('action', AuditLogAction::StaffDeactivated)->where('entity_id', $this->membership->id)->count())->toBe(1);
    } else {
        expect($this->sibling->fresh()->getAttributes())->toBe($siblingBefore)
            ->and($this->membership->fresh()->getAttributes())->toBe($memberships[$this->membership->id]->getAttributes());
    }
    foreach ($memberships as $membership) {
        if ($kind === 'organization' && $membership->id === $this->membership->id) {
            continue;
        }
        expect($membership->fresh()->getAttributes())->toBe($membership->getAttributes());
    }
    $auditCount = AuditLog::query()->count();
    expect(AuditLog::query()->where('action', AuditLogAction::BranchSuspended)->count())->toBe($kind === 'organization' ? 3 : 2);
    expect(fn () => restaurantStructureRestoreParent($kind, $this->owner, $this->organization, $this->brand))->toThrow(AuthorizationException::class);
    expect(AuditLog::query()->count())->toBe($auditCount)->and($point->fresh()->is_active)->toBeFalse();
})->with(['organization', 'brand']);

it('atomically rolls back restoration and child protections when a required write is refused', function (string $kind, string $failure): void {
    restaurantStructureArchiveParent($kind, $this->owner, $this->organization, $this->brand);
    $models = [Organization::class, Brand::class, Branch::class];
    $before = array_map(fn (string $model): array => $model::withTrashed()->orderBy('id')->get()->map->getAttributes()->all(), $models);
    $memberships = OrganizationUser::query()->orderBy('id')->get()->map->getAttributes()->all();
    $audits = AuditLog::query()->count();
    $event = match ($failure) {
        'branch' => 'eloquent.updating: '.Branch::class,
        'membership' => 'eloquent.updating: '.OrganizationUser::class,
        'audit' => 'eloquent.creating: '.AuditLog::class,
        'parent' => 'eloquent.restoring: '.($kind === 'organization' ? Organization::class : Brand::class),
    };
    Event::listen($event, fn (): bool => false);
    try {
        expect(fn () => restaurantStructureRestoreParent($kind, $this->owner, $this->organization, $this->brand))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }
    expect(array_map(fn (string $model): array => $model::withTrashed()->orderBy('id')->get()->map->getAttributes()->all(), $models))->toBe($before)
        ->and(OrganizationUser::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($memberships)
        ->and(AuditLog::query()->count())->toBe($audits);
})->with([
    ['organization', 'branch'], ['organization', 'membership'], ['organization', 'audit'], ['organization', 'parent'],
    ['brand', 'branch'], ['brand', 'audit'], ['brand', 'parent'],
]);

it('reauthorizes restoration against current membership subscription and actor', function (string $kind, string $denial): void {
    restaurantStructureArchiveParent($kind, $this->owner, $this->organization, $this->brand);
    if ($denial === 'membership') {
        $this->organization->memberships()->where('user_id', $this->owner->id)->update(['status' => OrganizationUserStatus::Suspended]);
    } elseif ($denial === 'subscription') {
        $this->organization->subscription()->update(['status' => 'inactive']);
    }
    $actor = $denial === 'actor' ? User::factory()->create() : $this->owner;
    $before = $this->branch->fresh()->getAttributes();
    $memberships = OrganizationUser::query()->orderBy('id')->get()->map->getAttributes()->all();
    $audits = AuditLog::query()->count();
    expect(fn () => restaurantStructureRestoreParent($kind, $actor, $this->organization, $this->brand))->toThrow(AuthorizationException::class);
    expect(($kind === 'organization' ? $this->organization : $this->brand)->fresh()->trashed())->toBeTrue()
        ->and($this->branch->fresh()->getAttributes())->toBe($before)
        ->and(OrganizationUser::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($memberships)
        ->and(AuditLog::query()->count())->toBe($audits);
})->with(['organization', 'brand'])->with(['membership', 'subscription', 'actor']);

function restaurantStructureArchiveParent(string $kind, User $actor, Organization $organization, Brand $brand): void
{
    if ($kind === 'organization') {
        app(DeleteOrganizationAction::class)->handle($actor, $organization);
    } else {
        app(DeleteBrandAction::class)->handle($actor, $organization, $brand);
    }
}

function restaurantStructureRestoreParent(string $kind, User $actor, Organization $organization, Brand $brand): void
{
    if ($kind === 'organization') {
        app(RestoreOrganizationAction::class)->handle($actor, $organization);
    } else {
        app(RestoreBrandAction::class)->handle($actor, $organization, $brand);
    }
}
