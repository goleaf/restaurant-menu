<?php

declare(strict_types=1);

use App\Actions\Departments\ResolvePreparationAccessibleDepartmentIdsAction;
use App\Actions\Departments\ResolvePreparationTicketContextAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('preparation access unions separately authorized families in the selected restaurant', function (SystemRole $role, array $expectedTypes): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->for($branch->organization)->for($branch->brand)->create();
    $user = User::factory()->create();
    OrganizationUser::factory()->forUser($user)->forOrganization($branch->organization)->forSystemRole($role)->create();
    $departments = collect(KitchenDepartmentType::cases())->map(fn (KitchenDepartmentType $type): KitchenDepartment => KitchenDepartment::factory()->for($branch)->forType($type)->create());
    $foreign = KitchenDepartment::factory()->for($otherBranch)->create();
    $unrelated = KitchenDepartment::factory()->create();

    $ids = app(ResolvePreparationAccessibleDepartmentIdsAction::class)->handle($user, $branch->id);

    expect($ids->all())->toEqualCanonicalizing($departments
        ->filter(fn (KitchenDepartment $department): bool => in_array($department->type, $expectedTypes, true))->pluck('id')->all())
        ->not->toContain($foreign->id, $unrelated->id);
})->with([
    'cook' => [SystemRole::Cook, KitchenDepartmentType::kitchenProductionTypes()],
    'bartender' => [SystemRole::Bartender, KitchenDepartmentType::barProductionTypes()],
    'head chef' => [SystemRole::HeadChef, KitchenDepartmentType::cases()],
]);

test('preparation rights do not follow restaurant administration when production permissions are denied', function (): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    OrganizationUser::factory()->forUser($user)->forOrganization($branch->organization)->forSystemRole(SystemRole::RestaurantAdmin)->create();
    KitchenDepartment::factory()->for($branch)->create();
    KitchenDepartment::factory()->for($branch)->forType(KitchenDepartmentType::Bar)->create();
    foreach ([SystemPermission::ViewKitchen, SystemPermission::ViewOrders, SystemPermission::SendToKitchen] as $permission) {
        PermissionUserOverride::factory()->forUser($user)->forOrganization($branch->organization)
            ->forPermission(Permission::query()->where('code', $permission->value)->sole())->denied()->create();
    }

    expect(app(ResolvePreparationAccessibleDepartmentIdsAction::class)->handle($user, $branch->id))->toBeEmpty();
});

test('kitchen rights in one organization never become bar rights in another or vice versa', function (): void {
    $kitchenBranch = Branch::factory()->create();
    $barBranch = Branch::factory()->create();
    $user = User::factory()->create();
    OrganizationUser::factory()->forUser($user)->forOrganization($kitchenBranch->organization)->forSystemRole(SystemRole::Cook)->create();
    OrganizationUser::factory()->forUser($user)->forOrganization($barBranch->organization)->forSystemRole(SystemRole::Bartender)->create();
    $allowedKitchen = KitchenDepartment::factory()->for($kitchenBranch)->create();
    $forbiddenBar = KitchenDepartment::factory()->for($kitchenBranch)->forType(KitchenDepartmentType::Bar)->create();
    $allowedBar = KitchenDepartment::factory()->for($barBranch)->forType(KitchenDepartmentType::Bar)->create();
    $forbiddenKitchen = KitchenDepartment::factory()->for($barBranch)->create();

    expect(app(ResolvePreparationAccessibleDepartmentIdsAction::class)->handle($user)->all())
        ->toEqualCanonicalizing([$allowedKitchen->id, $allowedBar->id])
        ->not->toContain($forbiddenBar->id, $forbiddenKitchen->id);
});

test('preparation rights recheck restaurant assignments and membership revocation', function (): void {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->for($branch->organization)->for($branch->brand)->create();
    $user = User::factory()->create();
    $membership = OrganizationUser::factory()->forUser($user)->forOrganization($branch->organization)->forSystemRole(SystemRole::HeadChef)->create();
    $assignment = BranchUser::factory()->forUser($user)->forBranch($branch)->active()->create();
    $kitchen = KitchenDepartment::factory()->for($branch)->create();
    KitchenDepartment::factory()->for($otherBranch)->create();
    $resolver = app(ResolvePreparationAccessibleDepartmentIdsAction::class);

    expect($resolver->handle($user, $branch->id)->all())->toBe([$kitchen->id])
        ->and($resolver->handle($user, $otherBranch->id))->toBeEmpty();
    $assignment->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect($resolver->handle($user, $branch->id))->toBeEmpty();
    $assignment->forceFill(['status' => OrganizationUserStatus::Active])->save();
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect($resolver->handle($user, $branch->id))->toBeEmpty();
});

test('ticket view mutation and print retain identical family and restaurant authorization', function (): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    OrganizationUser::factory()->forUser($user)->forOrganization($branch->organization)->forSystemRole(SystemRole::Cook)->create();
    $kitchen = KitchenDepartment::factory()->for($branch)->create();
    $bar = KitchenDepartment::factory()->for($branch)->forType(KitchenDepartmentType::Bar)->create();
    $order = Order::factory()->recycle($branch)->sentToDepartments()->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->for($kitchen, 'kitchenDepartment')->create();
    $barTicket = KitchenTicket::factory()->forOrder($order)->for($bar, 'kitchenDepartment')->create(['department_name' => 'Bar', 'department_type' => 'bar']);
    $foreignBranch = Branch::factory()->create();
    $corrupted = clone $ticket;
    $corrupted->branch_id = $foreignBranch->id;

    foreach (['view', 'print', 'updateStatus'] as $ability) {
        expect(Gate::forUser($user)->allows($ability, $ticket))->toBeTrue()
            ->and(Gate::forUser($user)->allows($ability, $barTicket))->toBeFalse()
            ->and(Gate::forUser($user)->allows($ability, $corrupted))->toBeFalse();
    }
});

test('inactive preparation history remains reachable only through its original family rights', function (): void {
    $branch = Branch::factory()->create();
    $cook = User::factory()->create();
    $bartender = User::factory()->create();
    OrganizationUser::factory()->forUser($cook)->forOrganization($branch->organization)->forSystemRole(SystemRole::Cook)->create();
    OrganizationUser::factory()->forUser($bartender)->forOrganization($branch->organization)->forSystemRole(SystemRole::Bartender)->create();
    $withHistory = KitchenDepartment::factory()->for($branch)->inactive()->create();
    $withoutHistory = KitchenDepartment::factory()->for($branch)->inactive()->create();
    $order = Order::factory()->recycle($branch)->sentToDepartments()->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->for($withHistory, 'kitchenDepartment')->create();
    $resolver = app(ResolvePreparationAccessibleDepartmentIdsAction::class);

    expect($resolver->handle($cook, $branch->id)->all())->toBe([$withHistory->id])->not->toContain($withoutHistory->id)
        ->and($resolver->handle($bartender, $branch->id))->toBeEmpty()
        ->and(Gate::forUser($cook)->allows('print', $ticket))->toBeTrue()
        ->and($withHistory->fresh()->is_active)->toBeFalse();
});

test('ticket deep links resolve only the exact authorized restaurant and department', function (): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    $membership = OrganizationUser::factory()->forUser($user)->forOrganization($branch->organization)->forSystemRole(SystemRole::Cook)->create();
    $department = KitchenDepartment::factory()->for($branch)->create();
    $order = Order::factory()->recycle($branch)->sentToDepartments()->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->for($department, 'kitchenDepartment')->create();
    $resolver = app(ResolvePreparationTicketContextAction::class);

    expect($resolver->handle($user, $ticket->id, $branch->id, [$department->id]))
        ->toBe(['department_id' => $department->id, 'ticket_id' => $ticket->id])
        ->and(fn () => $resolver->handle($user, $ticket->id, $branch->id, []))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $resolver->handle($user, $ticket->id, Branch::factory()->create()->id, [$department->id]))->toThrow(ModelNotFoundException::class);

    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect(fn () => $resolver->handle($user, $ticket->id, $branch->id, [$department->id]))->toThrow(AuthorizationException::class);
});

test('ticket deep links never replace missing historical routing with another department', function (): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    OrganizationUser::factory()->forUser($user)->forOrganization($branch->organization)->forSystemRole(SystemRole::Cook)->create();
    $department = KitchenDepartment::factory()->for($branch)->create();
    $ticket = KitchenTicket::factory()->forOrder(Order::factory()->recycle($branch)->create())->create();

    expect(fn () => app(ResolvePreparationTicketContextAction::class)->handle($user, $ticket->id, $branch->id, [$department->id]))
        ->toThrow(ModelNotFoundException::class)
        ->and($ticket->fresh()->kitchen_department_id)->toBeNull();
});
