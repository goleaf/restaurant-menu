<?php

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog as MenuCatalog;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments as MenuKitchenDepartments;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('kitchen department schema exposes branch departments and order snapshots', function () {
    expect(Schema::hasTable('kitchen_departments'))->toBeTrue()
        ->and(Schema::hasColumns('kitchen_departments', [
            'id',
            'branch_id',
            'type',
            'name',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('menu_items', [
            'kitchen_department_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('order_items', [
            'kitchen_department_id',
            'kitchen_department_type',
            'kitchen_department_name',
        ]))->toBeTrue()
        ->and(KitchenDepartmentType::values())->toBe([
            'kitchen',
            'bar',
            'dessert',
            'hookah',
            'custom',
        ]);
});

test('branch kitchen department seeder creates standard departments once', function () {
    $branch = Branch::factory()->create();
    $seedDepartments = app(SeedKitchenDepartmentsForBranchAction::class);

    $seedDepartments->handle($branch);
    $seedDepartments->handle($branch->fresh());

    $departments = $branch->fresh()
        ->kitchenDepartments()
        ->select(['id', 'branch_id', 'type', 'name', 'sort_order', 'is_active'])
        ->get();

    expect($departments)->toHaveCount(4)
        ->and($departments->pluck('type')->map(
            fn (KitchenDepartmentType|string $type): string => $type instanceof KitchenDepartmentType ? $type->value : (string) $type,
        )->all())->toBe([
            'kitchen',
            'bar',
            'dessert',
            'hookah',
        ])
        ->and($departments->every(fn (KitchenDepartment $department): bool => $department->is_active))->toBeTrue();
});

test('manager can manage kitchen departments and assign a dish department', function () {
    [$organization, $brand, $branch, $manager] = createPrompt58MenuBranch();
    grantPrompt58MenuPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    $menu = Menu::factory()->for($branch)->create([
        'name' => 'Prompt 58 Menu',
        'status' => MenuStatus::Active,
    ]);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Main']);

    Livewire::actingAs($manager)
        ->test(MenuKitchenDepartments::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('departmentName', 'Hot kitchen')
        ->set('departmentType', KitchenDepartmentType::Kitchen->value)
        ->set('departmentSortOrder', 10)
        ->set('departmentIsActive', true)
        ->call('createKitchenDepartment')
        ->assertHasNoErrors()
        ->assertSee('Hot kitchen');

    $department = KitchenDepartment::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Hot kitchen')
        ->firstOrFail();

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('itemMenuId', (string) $menu->id)
        ->set('itemCategoryId', (string) $category->id)
        ->set('itemKitchenDepartmentId', (string) $department->id)
        ->set('itemName', 'Grilled salmon')
        ->set('itemTranslations.en.name', 'Grilled salmon')
        ->set('itemTranslations.lt.name', 'Kepta lašiša')
        ->set('itemTranslations.ru.name', 'Лосось на гриле')
        ->set('itemSortOrder', 20)
        ->call('createItem')
        ->assertHasNoErrors()
        ->assertSee('Grilled salmon')
        ->assertSee('Hot kitchen');

    $item = MenuItem::query()
        ->where('menu_id', $menu->id)
        ->where('name', 'Grilled salmon')
        ->firstOrFail();

    expect($item->kitchen_department_id)->toBe($department->id);

    Livewire::actingAs($manager)
        ->test(MenuKitchenDepartments::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingKitchenDepartment', $department->id)
        ->set('editingDepartmentName', 'Main kitchen')
        ->set('editingDepartmentType', KitchenDepartmentType::Custom->value)
        ->set('editingDepartmentSortOrder', 30)
        ->call('updateKitchenDepartment')
        ->assertHasNoErrors()
        ->assertSee('Main kitchen')
        ->call('setKitchenDepartmentActive', $department->id, false)
        ->assertHasNoErrors()
        ->assertSee('Inactive')
        ->call('deleteKitchenDepartment', $department->id)
        ->assertHasNoErrors();

    expect($department->fresh())->toBeNull()
        ->and($item->fresh()->kitchen_department_id)->toBeNull();
});

test('blank dish department uses default kitchen and department changes clear menu cache', function () {
    [$organization, $brand, $branch, $manager] = createPrompt58MenuBranch();
    grantPrompt58MenuPermissions($manager, $organization, [SystemPermission::ManageMenu]);
    app(SeedKitchenDepartmentsForBranchAction::class)->handle($branch);

    $kitchen = KitchenDepartment::query()
        ->select(['id', 'branch_id', 'type', 'name'])
        ->where('branch_id', $branch->id)
        ->where('type', KitchenDepartmentType::Kitchen->value)
        ->firstOrFail();
    $bar = KitchenDepartment::query()
        ->select(['id', 'branch_id', 'type', 'name'])
        ->where('branch_id', $branch->id)
        ->where('type', KitchenDepartmentType::Bar->value)
        ->firstOrFail();
    $menu = Menu::factory()->for($branch)->create([
        'name' => 'Prompt 59 Menu',
        'status' => MenuStatus::Active,
    ]);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Pizza']);
    $cache = Cache::store(GetGuestMenuForBranchAction::cacheStore());
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'en');

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    expect($cache->has($cacheKey))->toBeTrue();

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->set('itemMenuId', (string) $menu->id)
        ->set('itemCategoryId', (string) $category->id)
        ->set('itemKitchenDepartmentId', '')
        ->set('itemName', 'Prompt 59 Pizza')
        ->set('itemTranslations.en.name', 'Prompt 59 Pizza')
        ->set('itemTranslations.lt.name', 'Prompt 59 Pica')
        ->set('itemTranslations.ru.name', 'Пицца Prompt 59')
        ->call('createItem')
        ->assertHasNoErrors();

    $item = MenuItem::query()
        ->select(['id', 'menu_id', 'category_id', 'kitchen_department_id', 'name'])
        ->where('menu_id', $menu->id)
        ->where('name', 'Prompt 59 Pizza')
        ->firstOrFail();

    expect($item->kitchen_department_id)->toBe($kitchen->id)
        ->and($cache->has($cacheKey))->toBeFalse();

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    expect($cache->has($cacheKey))->toBeTrue();

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingItem', $item->id)
        ->set('editingItemKitchenDepartmentId', (string) $bar->id)
        ->call('updateItem')
        ->assertHasNoErrors();

    expect($item->fresh()->kitchen_department_id)->toBe($bar->id)
        ->and($cache->has($cacheKey))->toBeFalse();

    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    expect($cache->has($cacheKey))->toBeTrue();

    $bar->update(['name' => 'Prompt 59 Coffee bar']);

    expect($cache->has($cacheKey))->toBeFalse();
});

test('waiter confirmation stores kitchen department snapshots on order items', function () {
    [$organization, $servicePoint, $draftOrder, $department] = createPrompt58SentDraftScenario();
    $waiter = User::factory()->create();

    attachPrompt58Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);
    $orderItem = $order->items()
        ->with(['kitchenDepartment:id,name,type'])
        ->firstOrFail();

    expect($orderItem->kitchen_department_id)->toBe($department->id)
        ->and($orderItem->kitchen_department_type)->toBe(KitchenDepartmentType::Bar->value)
        ->and($orderItem->kitchen_department_name)->toBe('Main bar')
        ->and($orderItem->kitchenDepartment?->name)->toBe('Main bar')
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);

    $department->update([
        'name' => 'Renamed bar',
        'type' => KitchenDepartmentType::Custom,
    ]);

    $orderItem = $orderItem->fresh();

    expect($orderItem->kitchen_department_type)->toBe(KitchenDepartmentType::Bar->value)
        ->and($orderItem->kitchen_department_name)->toBe('Main bar');
});

function createPrompt58MenuBranch(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Prompt 58 Menu Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 58 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Prompt 58 Branch']);

    return [$organization, $brand, $branch, $manager->fresh()];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function grantPrompt58MenuPermissions(User $user, Organization $organization, array $permissions): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();

    $permissionRows = Permission::query()
        ->whereIn('code', array_map(
            fn (SystemPermission $permission): string => $permission->value,
            $permissions,
        ))
        ->get();

    foreach ($permissionRows as $permission) {
        $membership->role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
    }
}

function createPrompt58SentDraftScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 58 Orders Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 58 Orders Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 58 Orders Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 58 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 58 Table',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 58 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Drinks']);
    $department = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Bar,
            'name' => 'Main bar',
            'sort_order' => 10,
        ]);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($department, 'kitchenDepartment')
        ->create([
            'name' => 'House lemonade',
            'price_cents' => 550,
        ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $guest->id,
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'item_name' => 'House lemonade',
            'quantity' => 1,
            'unit_price_cents' => 550,
            'modifier_total_cents' => 0,
            'total_price_cents' => 550,
            'selected_modifiers' => [],
        ]);

    return [$organization, $servicePoint, $draftOrder, $department];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt58Staff(User $user, Organization $organization, array $permissions): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    foreach ($permissions as $permission) {
        $permissionModel = Permission::query()
            ->where('code', $permission->value)
            ->firstOrFail();

        $role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
    }

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $role;
}
