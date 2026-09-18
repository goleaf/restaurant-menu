<?php

declare(strict_types=1);

use App\Actions\Brands\DeleteBrandAction;
use App\Actions\Brands\RestoreBrandAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\DeleteOrganizationAction;
use App\Actions\Organizations\RestoreOrganizationAction;
use App\Actions\Staff\SetBranchStaffStatusAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
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
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

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
    $preservedModels = [Menu::class, MenuCategory::class, MenuItem::class, ServicePoint::class, QrCode::class, RestaurantOnboarding::class];
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
            ->and($this->membership->fresh()->status)->toBe(OrganizationUserStatus::Active)
            ->and($this->membership->fresh()->access_version)->toBe(6)
            ->and(BranchUser::query()->where('user_id', $this->staff->id)->sole()->status)->toBe(OrganizationUserStatus::Suspended);
    }
    foreach ($memberships as $membership) {
        if ($membership->id === $this->membership->id) {
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
    if ($failure === 'materialization') {
        OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->create();
    }
    restaurantStructureArchiveParent($kind, $this->owner, $this->organization, $this->brand);
    $models = [Organization::class, Brand::class, Branch::class];
    $before = array_map(fn (string $model): array => $model::withTrashed()->orderBy('id')->get()->map->getAttributes()->all(), $models);
    $memberships = OrganizationUser::query()->orderBy('id')->get()->map->getAttributes()->all();
    $assignments = BranchUser::query()->orderBy('id')->get()->map->getAttributes()->all();
    $audits = AuditLog::query()->count();
    $event = match ($failure) {
        'branch' => 'eloquent.updating: '.Branch::class,
        'membership' => 'eloquent.updating: '.OrganizationUser::class,
        'assignment' => 'eloquent.updating: '.BranchUser::class,
        'materialization' => 'eloquent.creating: '.BranchUser::class,
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
        ->and(BranchUser::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($assignments)
        ->and(AuditLog::query()->count())->toBe($audits);
})->with([
    ['organization', 'branch'], ['organization', 'membership'], ['organization', 'audit'], ['organization', 'parent'],
    ['brand', 'branch'], ['brand', 'membership'], ['brand', 'assignment'], ['brand', 'materialization'], ['brand', 'audit'], ['brand', 'parent'],
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

it('closes operational access to restaurants in an archived brand until explicit parent restoration', function (string $role): void {
    $actor = match ($role) {
        'owner' => $this->owner,
        'staff' => $this->staff,
        'superadmin' => User::factory()->create(),
    };
    if ($role === 'superadmin') {
        $actor->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->sole());
    }
    $resolver = app(ResolveWaiterAccessibleBranchIdsAction::class);
    $menu = Menu::factory()->for($this->branch)->active()->create(['sort_order' => 17]);
    ServicePoint::factory()->for($this->branch)->withQr()->create(['is_active' => false]);
    $contentModels = [Menu::class, ServicePoint::class, QrCode::class];
    $content = array_map(fn (string $model): array => $model::query()->orderBy('id')->get()->map->getAttributes()->all(), $contentModels);
    expect($actor->canAccessBranch($this->branch))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('view', $this->branch))->toBeTrue()
        ->and($resolver->handle($actor, SystemPermission::ViewOrders)->all())->toContain($this->branch->id)
        ->and($resolver->authorizedBranchQuery($actor)->pluck('id')->all())->toContain($this->branch->id);

    app(DeleteBrandAction::class)->handle($this->owner, $this->organization, $this->brand);

    expect($actor->fresh()->canAccessBranch($this->branch))->toBeFalse()
        ->and($actor->fresh()->canAccessBranch($this->branch->id))->toBeFalse()
        ->and(Gate::forUser($actor->fresh())->allows('view', $this->branch))->toBeFalse()
        ->and($resolver->handle($actor, SystemPermission::ViewOrders)->all())->not->toContain($this->branch->id)
        ->and($resolver->authorizedBranchQuery($actor)->pluck('id')->all())->not->toContain($this->branch->id)
        ->and($actor->fresh()->canAccessBranch($this->branch, withTrashed: true))->toBeTrue()
        ->and($resolver->handle($actor, SystemPermission::ViewOrders, includeArchived: true)->all())->toContain($this->branch->id)
        ->and($resolver->authorizedBranchQuery($actor, includeArchived: true)->pluck('id')->all())->toContain($this->branch->id);
    expect(Gate::forUser($this->owner)->allows('restore', $this->archivedBranch))->toBeTrue();

    app(RestoreBrandAction::class)->handle($this->owner, $this->organization, $this->brand);

    expect($actor->fresh()->canAccessBranch($this->branch))->toBe($role !== 'staff')
        ->and(Gate::forUser($actor->fresh())->allows('view', $this->branch))->toBe($role !== 'staff')
        ->and($resolver->handle($actor, SystemPermission::ViewOrders)->contains($this->branch->id))->toBe($role !== 'staff')
        ->and($resolver->authorizedBranchQuery($actor)->pluck('id')->contains($this->branch->id))->toBe($role !== 'staff')
        ->and($this->branch->fresh()->is_active)->toBeFalse()
        ->and(array_map(fn (string $model): array => $model::query()->orderBy('id')->get()->map->getAttributes()->all(), $contentModels))->toBe($content)
        ->and($menu->fresh()->status)->toBe($menu->status);
})->with(['owner', 'staff', 'superadmin']);

it('keeps former brand staff restricted while preserving their existing access to other brands', function (string $mode): void {
    $staff = $mode === 'inherited' ? User::factory()->create() : $this->staff;
    $membership = $mode === 'inherited'
        ? OrganizationUser::factory()->forOrganization($this->organization)->forUser($staff)->forSystemRole(SystemRole::Waiter)->create(['access_version' => 5])
        : $this->membership;
    $outsideAssignment = $mode === 'assigned' ? BranchUser::factory()->forBranch($this->sibling)->forUser($staff)->create() : null;
    $outsideBefore = $outsideAssignment?->fresh()->getAttributes();
    $targetBefore = $mode === 'assigned' ? BranchUser::query()->where('branch_id', $this->branch->id)->where('user_id', $staff->id)->sole()->getAttributes() : null;
    $memberBefore = $membership->fresh()->getAttributes();
    PermissionUserOverride::factory()->forUser($staff)->forOrganization($this->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ViewOrders->value)->sole())->allowed()->create();
    $overrides = PermissionUserOverride::query()->orderBy('id')->get()->map->getAttributes()->all();
    $resolver = app(ResolveWaiterAccessibleBranchIdsAction::class);
    expect($staff->canAccessBranch($this->branch))->toBeTrue()->and($staff->canAccessBranch($this->sibling))->toBeTrue();
    if ($mode === 'assigned') {
        $removed = BranchUser::factory()->forBranch($this->archivedBranch)->forUser($staff)->removed()->create();
        $removedBefore = $removed->fresh()->getAttributes();
        $invitedBranch = Branch::factory()->for($this->organization)->for($this->brand)->create();
        $invited = BranchUser::factory()->forBranch($invitedBranch)->forUser($staff)->invited()->create();
        $invitedBefore = $invited->fresh()->getAttributes();
    }

    app(DeleteBrandAction::class)->handle($this->owner, $this->organization, $this->brand);
    app(RestoreBrandAction::class)->handle($this->owner, $this->organization, $this->brand);

    expect($staff->fresh()->canAccessBranch($this->branch))->toBeFalse()
        ->and(Gate::forUser($staff->fresh())->allows('view', $this->branch))->toBeFalse()
        ->and($resolver->handle($staff)->all())->not->toContain($this->branch->id)
        ->and($resolver->authorizedBranchQuery($staff)->pluck('id')->all())->not->toContain($this->branch->id)
        ->and($staff->fresh()->canAccessBranch($this->sibling))->toBeTrue()
        ->and($resolver->handle($staff)->all())->toContain($this->sibling->id)
        ->and($membership->fresh()->getAttributes())->toMatchArray([...$memberBefore, 'access_version' => 6, 'updated_at' => $membership->fresh()->getRawOriginal('updated_at')])
        ->and(PermissionUserOverride::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($overrides);
    $assignment = BranchUser::query()->where('branch_id', $this->branch->id)->where('user_id', $staff->id)->sole();
    expect($assignment->status)->toBe(OrganizationUserStatus::Suspended)->and($assignment->access_version)->toBe(1)
        ->and(AuditLog::query()->where('entity_type', 'branch_user')->where('entity_id', $assignment->id)->where('action', AuditLogAction::StaffDeactivated)->exists())->toBeTrue();
    if ($mode === 'assigned') {
        expect($outsideAssignment->fresh()->getAttributes())->toBe($outsideBefore)
            ->and($removed->fresh()->getAttributes())->toBe($removedBefore)
            ->and($invited->fresh()->getAttributes())->toBe($invitedBefore)
            ->and($assignment->getAttributes())->toBe([...$targetBefore, 'status' => OrganizationUserStatus::Suspended->value, 'access_version' => 1, 'updated_at' => $assignment->getRawOriginal('updated_at')]);
    } else {
        $outside = BranchUser::query()->where('branch_id', $this->sibling->id)->where('user_id', $staff->id)->sole();
        expect($outside->status)->toBe(OrganizationUserStatus::Active)->and($outside->role_id)->toBe($membership->role_id);
    }
    $after = BranchUser::query()->orderBy('id')->get()->map->getAttributes()->all();
    expect(fn () => app(RestoreBrandAction::class)->handle($this->owner, $this->organization, $this->brand))->toThrow(AuthorizationException::class);
    expect(BranchUser::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($after);
    app(SetBranchStaffStatusAction::class)->activate($assignment, $this->owner, 'Reviewed restored restaurant assignment', $assignment->access_version);
    expect($staff->fresh()->canAccessBranch($this->branch))->toBeTrue()->and($staff->fresh()->canAccessBranch($this->sibling))->toBeTrue();
})->with(['assigned', 'inherited']);

it('preserves explicit owner and superadmin assignments when restoring a brand', function (string $role): void {
    $user = $role === 'owner' ? $this->owner : User::factory()->create();
    if ($role === 'superadmin') {
        $user->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->sole());
        OrganizationUser::factory()->forOrganization($this->organization)->forUser($user)->forSystemRole(SystemRole::Waiter)->create();
    }
    $assignment = BranchUser::factory()->forBranch($this->branch)->forUser($user)->create();
    $before = $assignment->fresh()->getAttributes();
    $member = OrganizationUser::query()->where('organization_id', $this->organization->id)->where('user_id', $user->id)->sole();
    $memberBefore = $member->getAttributes();

    app(DeleteBrandAction::class)->handle($this->owner, $this->organization, $this->brand);
    app(RestoreBrandAction::class)->handle($this->owner, $this->organization, $this->brand);

    expect($assignment->fresh()->getAttributes())->toBe($before)->and($member->fresh()->getAttributes())->toBe($memberBefore)
        ->and($user->fresh()->canAccessBranch($this->branch))->toBeTrue();
})->with(['owner', 'superadmin']);

it('does not replace inherited staff scope when an empty brand is restored', function (): void {
    $empty = Brand::factory()->for($this->organization)->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forSystemRole(SystemRole::Waiter)->create();
    $members = OrganizationUser::query()->orderBy('id')->get()->map->getAttributes()->all();
    $assignments = BranchUser::query()->orderBy('id')->get()->map->getAttributes()->all();

    app(DeleteBrandAction::class)->handle($this->owner, $this->organization, $empty);
    app(RestoreBrandAction::class)->handle($this->owner, $this->organization, $empty);

    expect(OrganizationUser::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($members)
        ->and(BranchUser::query()->orderBy('id')->get()->map->getAttributes()->all())->toBe($assignments);
});

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
