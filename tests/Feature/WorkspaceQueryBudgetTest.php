<?php

declare(strict_types=1);

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Services\Navigation\WorkspaceAccessQuery;
use Database\Seeders\SystemPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_user_id' => $this->actor->id]);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->otherBranch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    foreach ([$this->branch, $this->otherBranch] as $branch) {
        foreach (KitchenDepartmentType::cases() as $type) {
            KitchenDepartment::factory()->for($branch)->forType($type)->create();
        }
    }
});

test('workspace destinations share one permission batch within their query budget', function (): void {
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $access = [];
    $queries = countDatabaseQueries(function () use (&$access): void {
        $access = app(WorkspaceAccessQuery::class)->destinations($this->actor);
    });

    expect($access['kitchen'])->toBe([$this->branch->id, $this->otherBranch->id])
        ->and($access['bar'])->toBe([$this->branch->id, $this->otherBranch->id])
        ->and($queries)->toBeLessThanOrEqual(20);
});

test('workspace production destinations retain canonical role and branch assignment decisions', function (SystemRole $role): void {
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)
        ->forSystemRole($role)->active()->create();
    BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->active()
        ->create(['assigned_by_user_id' => $this->actor->id]);
    $access = app(WorkspaceAccessQuery::class)->destinations($this->actor);

    foreach (['kitchen' => ResolveKitchenAccessibleDepartmentIdsAction::class, 'bar' => ResolveBarAccessibleDepartmentIdsAction::class] as $destination => $action) {
        $expected = KitchenDepartment::query()->whereIn('id', app($action)->handle($this->actor))
            ->distinct()->pluck('branch_id')->all();

        expect($access[$destination])->toBe($expected)->not->toContain($this->otherBranch->id);
    }
})->with([SystemRole::Owner, SystemRole::Waiter, SystemRole::HeadChef, SystemRole::Cook, SystemRole::Bartender]);

test('workspace permission batches immediately reflect grants denials and membership revocation', function (): void {
    $membership = OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)
        ->forSystemRole(SystemRole::Waiter)->active()->create();
    $query = app(WorkspaceAccessQuery::class);
    expect($query->destinations($this->actor)['bar'])->toContain($this->branch->id);

    foreach ([SystemPermission::ViewOrders, SystemPermission::SendToKitchen] as $code) {
        PermissionUserOverride::factory()->forUser($this->actor)->forOrganization($this->organization)
            ->forPermission(Permission::query()->where('code', $code->value)->sole())->denied()->create();
    }
    $grant = PermissionUserOverride::factory()->forUser($this->actor)->forOrganization($this->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ViewKitchen->value)->sole())
        ->allowed()->create();
    $access = $query->destinations($this->actor);
    expect($access['bar'])->toBe([])->and($access['kitchen'])->toContain($this->branch->id);

    $grant->update(['enabled' => false]);
    expect($query->destinations($this->actor)['kitchen'])->toBe([]);

    $grant->update(['enabled' => true]);
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $access = $query->destinations($this->actor);
    expect($access['kitchen'])->toBe([])->and($access['bar'])->toBe([]);
});

test('workspace department access immediately reflects disabled departments and suspended assignments', function (): void {
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)
        ->forSystemRole(SystemRole::HeadChef)->active()->create();
    $assignment = BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->active()
        ->create(['assigned_by_user_id' => $this->actor->id]);
    $query = app(WorkspaceAccessQuery::class);
    $access = $query->destinations($this->actor);
    expect($access['kitchen'])->toBe([$this->branch->id])->and($access['bar'])->toBe([$this->branch->id]);

    KitchenDepartment::query()->where('branch_id', $this->branch->id)->where('type', KitchenDepartmentType::Bar)->update(['is_active' => false]);
    expect($query->destinations($this->actor)['bar'])->toBe([]);

    $assignment->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $access = $query->destinations($this->actor);
    expect($access['kitchen'])->toBe([])->and($access['bar'])->toBe([]);
});

test('workspace permission batches retain superadmin access without a tenant membership', function (): void {
    $this->actor->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->sole());
    $access = app(WorkspaceAccessQuery::class)->destinations($this->actor);

    expect($access['kitchen'])->toBe([$this->branch->id, $this->otherBranch->id])
        ->and($access['bar'])->toBe([$this->branch->id, $this->otherBranch->id]);
});

test('workspace role fallback respects subscription changes and a different actor', function (): void {
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)
        ->forSystemRole(SystemRole::HeadChef)->active()->create();
    $subscription = OrganizationSubscription::factory()->for($this->organization)->active()->create();
    $query = app(WorkspaceAccessQuery::class);
    expect($query->destinations($this->actor)['kitchen'])->toContain($this->branch->id);

    $other = $query->destinations(User::factory()->create());
    expect($other['kitchen'])->toBe([])->and($other['bar'])->toBe([]);

    $subscription->forceFill(['status' => OrganizationSubscriptionStatus::Inactive])->save();
    $access = $query->destinations($this->actor);
    expect($access['kitchen'])->toBe([])->and($access['bar'])->toBe([]);
});

test('department actions resolve missing permission decisions through their original contract', function (): void {
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    foreach ([ResolveKitchenAccessibleDepartmentIdsAction::class, ResolveBarAccessibleDepartmentIdsAction::class] as $class) {
        $action = app($class);
        expect($action->handle($this->actor, [])->all())->toBe($action->handle($this->actor)->all())->not->toBeEmpty();
    }
});

test('workspace report viewing never grants data export', function (): void {
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($this->actor)
        ->forSystemRole(SystemRole::Waiter)->active()->create();
    foreach ([[SystemPermission::ViewReports, true], [SystemPermission::ExportData, false]] as [$code, $enabled]) {
        PermissionUserOverride::factory()->forUser($this->actor)->forOrganization($this->organization)
            ->forPermission(Permission::query()->where('code', $code->value)->sole())->create(['enabled' => $enabled]);
    }

    $access = app(WorkspaceAccessQuery::class)->destinations($this->actor);
    expect($access['report_view'])->toContain($this->branch->id)->and($access['reports'])->toBe([]);
});
