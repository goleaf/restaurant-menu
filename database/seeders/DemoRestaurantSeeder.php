<?php

namespace Database\Seeders;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\Brands\CreateBrandAction;
use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Enums\AreaNodeType;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointType;
use App\Enums\SupportedLocale;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoRestaurantSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    private const ORGANIZATION_NAME = 'Demo Food Group';

    private const BRAND_NAME = 'Bella Pizza';

    private const BRANCH_NAME = 'Demo Old Town';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(SystemPermissionsSeeder::class);

        DB::transaction(function (): void {
            $owner = $this->demoUser('Demo Owner', 'demo.owner@example.com', SystemRole::Owner);
            $organization = $this->demoOrganization($owner);
            $brand = $this->demoBrand($organization);
            $branch = $this->demoBranch($brand);

            app(EnsureBranchSettingsAction::class)->handle($branch);
            app(SeedKitchenDepartmentsForBranchAction::class)->handle($branch);

            $this->seedStaff($organization, $branch, $owner);

            $areas = $this->seedAreas($branch);
            $servicePoints = $this->seedServicePoints($branch, $areas);
            $this->seedQrCodes($servicePoints, $owner);
            $this->seedMenu($branch);
        });
    }

    private function demoOrganization(User $owner): Organization
    {
        $organization = Organization::query()
            ->where('name', self::ORGANIZATION_NAME)
            ->first();

        if (! $organization instanceof Organization) {
            return app(CreateOrganizationAction::class)->handle($owner, [
                'name' => self::ORGANIZATION_NAME,
            ]);
        }

        $organization->update([
            'owner_user_id' => $owner->id,
        ]);

        $this->ensureOrganizationMembership($organization, $owner, SystemRole::Owner, null);

        return $organization;
    }

    private function demoBrand(Organization $organization): Brand
    {
        $brand = Brand::query()
            ->where('organization_id', $organization->id)
            ->where('name', self::BRAND_NAME)
            ->first();

        if ($brand instanceof Brand) {
            return $brand;
        }

        return app(CreateBrandAction::class)->handle($organization, [
            'name' => self::BRAND_NAME,
        ]);
    }

    private function demoBranch(Brand $brand): Branch
    {
        $branch = Branch::query()
            ->where('brand_id', $brand->id)
            ->where('name', self::BRANCH_NAME)
            ->first();

        $data = [
            'name' => self::BRANCH_NAME,
            'address' => 'Pilies g. 10',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'is_active' => true,
        ];

        if ($branch instanceof Branch) {
            $branch->update($data);

            return $branch;
        }

        return app(CreateBranchAction::class)->handle($brand, $data);
    }

    private function demoUser(string $name, string $email, SystemRole $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::DEMO_PASSWORD,
                'locale' => SupportedLocale::English->value,
            ],
        );

        $roleModel = $this->role($role);
        $user->roles()->syncWithoutDetachingOrFail([$roleModel->id]);

        return $user;
    }

    private function seedStaff(Organization $organization, Branch $branch, User $owner): void
    {
        $this->ensureOrganizationMembership($organization, $owner, SystemRole::Owner, null);
        $this->grantPermissions($owner, SystemPermission::cases());

        $manager = $this->demoUser('Demo Restaurant Admin', 'demo.admin@example.com', SystemRole::RestaurantAdmin);
        $waiter = $this->demoUser('Demo Waiter', 'demo.waiter@example.com', SystemRole::Waiter);
        $chef = $this->demoUser('Demo Chef', 'demo.chef@example.com', SystemRole::HeadChef);
        $bartender = $this->demoUser('Demo Bartender', 'demo.bartender@example.com', SystemRole::Bartender);
        $cashier = $this->demoUser('Demo Cashier', 'demo.cashier@example.com', SystemRole::Cashier);

        $this->ensureOrganizationMembership($organization, $manager, SystemRole::RestaurantAdmin, $owner);
        $this->ensureOrganizationMembership($organization, $waiter, SystemRole::Waiter, $manager);
        $this->ensureOrganizationMembership($organization, $chef, SystemRole::HeadChef, $manager);
        $this->ensureOrganizationMembership($organization, $bartender, SystemRole::Bartender, $manager);
        $this->ensureOrganizationMembership($organization, $cashier, SystemRole::Cashier, $manager);

        $this->ensureBranchAssignment($organization, $branch, $manager, SystemRole::RestaurantAdmin, $owner);
        $this->ensureBranchAssignment($organization, $branch, $waiter, SystemRole::Waiter, $manager);
        $this->ensureBranchAssignment($organization, $branch, $chef, SystemRole::HeadChef, $manager);
        $this->ensureBranchAssignment($organization, $branch, $bartender, SystemRole::Bartender, $manager);
        $this->ensureBranchAssignment($organization, $branch, $cashier, SystemRole::Cashier, $manager);

        $this->grantPermissions($manager, SystemPermission::cases());
        $this->grantPermissions($waiter, [
            SystemPermission::ViewRestaurant,
            SystemPermission::ViewOrders,
            SystemPermission::ConfirmOrders,
            SystemPermission::EditPendingOrders,
            SystemPermission::SendToKitchen,
            SystemPermission::ViewPayments,
            SystemPermission::CloseTableSessions,
        ]);
        $this->grantPermissions($chef, [
            SystemPermission::ViewRestaurant,
            SystemPermission::ViewKitchen,
            SystemPermission::ChangeAvailability,
        ]);
        $this->grantPermissions($bartender, [
            SystemPermission::ViewRestaurant,
            SystemPermission::ViewOrders,
            SystemPermission::SendToKitchen,
        ]);
        $this->grantPermissions($cashier, [
            SystemPermission::ViewRestaurant,
            SystemPermission::ViewOrders,
            SystemPermission::ViewPayments,
            SystemPermission::ManagePayments,
            SystemPermission::CloseTableSessions,
        ]);
    }

    /**
     * @return array<string, AreaNode>
     */
    private function seedAreas(Branch $branch): array
    {
        return [
            'main' => $this->area($branch, 'Главный зал', AreaNodeType::Hall, 'layout-grid', 10),
            'terrace' => $this->area($branch, 'Терраса', AreaNodeType::Terrace, 'sun', 20),
            'bar' => $this->area($branch, 'Бар', AreaNodeType::BarArea, 'martini', 30),
        ];
    }

    private function area(Branch $branch, string $name, AreaNodeType $type, string $icon, int $sortOrder): AreaNode
    {
        $area = AreaNode::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('name', $name)
            ->first();

        if ($area instanceof AreaNode) {
            if ($area->trashed()) {
                $area->restore();
            }

            $area->update([
                'parent_id' => null,
                'type' => $type,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'metadata' => [],
            ]);

            return $area;
        }

        return app(CreateAreaNodeAction::class)->handle($branch, [
            'parent_id' => null,
            'type' => $type->value,
            'name' => $name,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, AreaNode>  $areas
     * @return list<ServicePoint>
     */
    private function seedServicePoints(Branch $branch, array $areas): array
    {
        $rows = [
            [
                'area' => 'main',
                'type' => ServicePointType::Table,
                'name' => 'Стол 1',
                'display_number' => '1',
                'internal_code' => 'DEMO-OLD-TOWN-TABLE-1',
                'capacity' => 4,
                'icon' => 'square',
                'position_x' => 20,
                'position_y' => 20,
            ],
            [
                'area' => 'main',
                'type' => ServicePointType::Table,
                'name' => 'Стол 2',
                'display_number' => '2',
                'internal_code' => 'DEMO-OLD-TOWN-TABLE-2',
                'capacity' => 4,
                'icon' => 'square',
                'position_x' => 45,
                'position_y' => 20,
            ],
            [
                'area' => 'main',
                'type' => ServicePointType::Table,
                'name' => 'Стол 3',
                'display_number' => '3',
                'internal_code' => 'DEMO-OLD-TOWN-TABLE-3',
                'capacity' => 6,
                'icon' => 'square',
                'position_x' => 70,
                'position_y' => 20,
            ],
            [
                'area' => 'terrace',
                'type' => ServicePointType::Table,
                'name' => 'Терраса 1',
                'display_number' => 'T1',
                'internal_code' => 'DEMO-OLD-TOWN-TERRACE-1',
                'capacity' => 4,
                'icon' => 'sun',
                'position_x' => 25,
                'position_y' => 70,
            ],
            [
                'area' => 'terrace',
                'type' => ServicePointType::Table,
                'name' => 'Терраса 2',
                'display_number' => 'T2',
                'internal_code' => 'DEMO-OLD-TOWN-TERRACE-2',
                'capacity' => 2,
                'icon' => 'sun',
                'position_x' => 50,
                'position_y' => 70,
            ],
            [
                'area' => 'bar',
                'type' => ServicePointType::BarSeat,
                'name' => 'Бар 1',
                'display_number' => 'B1',
                'internal_code' => 'DEMO-OLD-TOWN-BAR-1',
                'capacity' => 1,
                'icon' => 'martini',
                'position_x' => 75,
                'position_y' => 65,
            ],
            [
                'area' => 'bar',
                'type' => ServicePointType::BarSeat,
                'name' => 'Бар 2',
                'display_number' => 'B2',
                'internal_code' => 'DEMO-OLD-TOWN-BAR-2',
                'capacity' => 1,
                'icon' => 'martini',
                'position_x' => 85,
                'position_y' => 65,
            ],
        ];

        return collect($rows)
            ->map(fn (array $row): ServicePoint => $this->servicePoint($branch, $areas[$row['area']], $row))
            ->values()
            ->all();
    }

    /**
     * @param  array{
     *     type: ServicePointType,
     *     name: string,
     *     display_number: string,
     *     internal_code: string,
     *     capacity: int,
     *     icon: string,
     *     position_x: int,
     *     position_y: int
     * }  $data
     */
    private function servicePoint(Branch $branch, AreaNode $area, array $data): ServicePoint
    {
        $servicePoint = ServicePoint::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('internal_code', $data['internal_code'])
            ->first();

        if (! $servicePoint instanceof ServicePoint) {
            $servicePoint = app(CreateServicePointAction::class)->handle($branch, [
                'area_node_id' => $area->id,
                'type' => $data['type']->value,
                'name' => $data['name'],
                'display_number' => $data['display_number'],
                'capacity' => $data['capacity'],
                'icon' => $data['icon'],
                'is_active' => true,
            ]);
        } elseif ($servicePoint->trashed()) {
            $servicePoint->restore();
        }

        $servicePoint->forceFill([
            'area_node_id' => $area->id,
            'type' => $data['type'],
            'name' => $data['name'],
            'display_number' => $data['display_number'],
            'internal_code' => $data['internal_code'],
            'capacity' => $data['capacity'],
            'icon' => $data['icon'],
            'position_x' => $data['position_x'],
            'position_y' => $data['position_y'],
            'is_active' => true,
            'metadata' => [],
        ])->save();

        return $servicePoint;
    }

    /**
     * @param  list<ServicePoint>  $servicePoints
     */
    private function seedQrCodes(array $servicePoints, User $owner): void
    {
        foreach ($servicePoints as $servicePoint) {
            app(GenerateQrCodeForServicePointAction::class)->handle($servicePoint, $owner);
        }
    }

    private function seedMenu(Branch $branch): void
    {
        $menu = Menu::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'name' => 'Bella Pizza Demo Menu',
            ],
            [
                'status' => MenuStatus::Active,
                'sort_order' => 10,
            ],
        );

        $departments = KitchenDepartment::query()
            ->where('branch_id', $branch->id)
            ->whereIn('type', [
                KitchenDepartmentType::Kitchen->value,
                KitchenDepartmentType::Bar->value,
                KitchenDepartmentType::Dessert->value,
            ])
            ->pluck('id', 'type');

        $pizza = $this->category($menu, 'Пицца', 'Классическая пицца для быстрого demo-заказа.', 'pizza', 10);
        $drinks = $this->category($menu, 'Напитки', 'Горячие и холодные напитки.', 'cup-soda', 20);
        $desserts = $this->category($menu, 'Десерты', 'Сладкое завершение заказа.', 'cake-slice', 30);

        $this->item($menu, $pizza, $departments->get(KitchenDepartmentType::Kitchen->value), [
            'name' => 'Маргарита',
            'description' => 'Томатный соус, моцарелла, базилик.',
            'price' => '9.50',
            'weight' => '420.00',
            'calories' => 820,
            'sort_order' => 10,
        ]);
        $this->item($menu, $pizza, $departments->get(KitchenDepartmentType::Kitchen->value), [
            'name' => 'Пепперони',
            'description' => 'Пикантная пепперони, моцарелла, томатный соус.',
            'price' => '11.90',
            'weight' => '450.00',
            'calories' => 980,
            'sort_order' => 20,
        ]);
        $this->item($menu, $pizza, $departments->get(KitchenDepartmentType::Kitchen->value), [
            'name' => 'Капричоза',
            'description' => 'Ветчина, грибы, артишоки и моцарелла.',
            'price' => '12.50',
            'weight' => '470.00',
            'calories' => 1020,
            'sort_order' => 30,
        ]);
        $this->item($menu, $drinks, $departments->get(KitchenDepartmentType::Bar->value), [
            'name' => 'Домашний лимонад',
            'description' => 'Лимон, мята, лёд и газированная вода.',
            'price' => '4.20',
            'volume' => '0.40',
            'calories' => 120,
            'sort_order' => 40,
        ]);
        $this->item($menu, $drinks, $departments->get(KitchenDepartmentType::Bar->value), [
            'name' => 'Эспрессо',
            'description' => 'Классический двойной эспрессо.',
            'price' => '2.80',
            'volume' => '0.06',
            'calories' => 5,
            'sort_order' => 50,
        ]);
        $this->item($menu, $desserts, $departments->get(KitchenDepartmentType::Dessert->value), [
            'name' => 'Тирамису',
            'description' => 'Маскарпоне, кофе и какао.',
            'price' => '5.90',
            'weight' => '160.00',
            'calories' => 410,
            'sort_order' => 60,
        ]);
        $this->item($menu, $desserts, $departments->get(KitchenDepartmentType::Dessert->value), [
            'name' => 'Чизкейк',
            'description' => 'Сливочный чизкейк с ягодным соусом.',
            'price' => '5.50',
            'weight' => '150.00',
            'calories' => 390,
            'sort_order' => 70,
        ]);
    }

    private function category(Menu $menu, string $name, string $description, string $icon, int $sortOrder): MenuCategory
    {
        $category = MenuCategory::query()->updateOrCreate(
            [
                'menu_id' => $menu->id,
                'name' => $name,
            ],
            [
                'description' => $description,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ],
        );

        foreach ($this->categoryTranslations($name, $description) as $languageCode => $translation) {
            MenuCategoryTranslation::query()->updateOrCreate(
                [
                    'menu_category_id' => $category->id,
                    'language_code' => $languageCode,
                ],
                $translation,
            );
        }

        return $category;
    }

    /**
     * @param  array{name: string, description: string, price: string, weight?: string, volume?: string, calories: int, sort_order: int}  $data
     */
    private function item(Menu $menu, MenuCategory $category, ?int $departmentId, array $data): MenuItem
    {
        $item = MenuItem::query()->updateOrCreate(
            [
                'menu_id' => $menu->id,
                'name' => $data['name'],
            ],
            [
                'category_id' => $category->id,
                'kitchen_department_id' => $departmentId,
                'description' => $data['description'],
                'price' => $data['price'],
                'weight' => $data['weight'] ?? null,
                'volume' => $data['volume'] ?? null,
                'calories' => $data['calories'],
                'is_available' => true,
                'sort_order' => $data['sort_order'],
            ],
        );

        foreach ($this->itemTranslations($data['name'], $data['description']) as $languageCode => $translation) {
            MenuItemTranslation::query()->updateOrCreate(
                [
                    'menu_item_id' => $item->id,
                    'language_code' => $languageCode,
                ],
                $translation,
            );
        }

        return $item;
    }

    /**
     * @return array<string, array{name: string, description: string}>
     */
    private function categoryTranslations(string $name, string $description): array
    {
        return match ($name) {
            'Пицца' => [
                'en' => ['name' => 'Pizza', 'description' => 'Classic pizza for a quick demo order.'],
                'lt' => ['name' => 'Picos', 'description' => 'Klasikinės picos greitam demo užsakymui.'],
            ],
            'Напитки' => [
                'en' => ['name' => 'Drinks', 'description' => 'Hot and cold drinks.'],
                'lt' => ['name' => 'Gėrimai', 'description' => 'Karšti ir šalti gėrimai.'],
            ],
            'Десерты' => [
                'en' => ['name' => 'Desserts', 'description' => 'A sweet finish to the order.'],
                'lt' => ['name' => 'Desertai', 'description' => 'Saldus užsakymo užbaigimas.'],
            ],
            default => [
                'en' => ['name' => $name, 'description' => $description],
                'lt' => ['name' => $name, 'description' => $description],
            ],
        };
    }

    /**
     * @return array<string, array{name: string, description: string}>
     */
    private function itemTranslations(string $name, string $description): array
    {
        return match ($name) {
            'Маргарита' => [
                'en' => ['name' => 'Margherita', 'description' => 'Tomato sauce, mozzarella, basil.'],
                'lt' => ['name' => 'Margarita', 'description' => 'Pomidorų padažas, mocarela, bazilikas.'],
            ],
            'Пепперони' => [
                'en' => ['name' => 'Pepperoni', 'description' => 'Spicy pepperoni, mozzarella, tomato sauce.'],
                'lt' => ['name' => 'Pepperoni', 'description' => 'Aitri pepperoni dešra, mocarela, pomidorų padažas.'],
            ],
            'Капричоза' => [
                'en' => ['name' => 'Capricciosa', 'description' => 'Ham, mushrooms, artichokes, and mozzarella.'],
                'lt' => ['name' => 'Capricciosa', 'description' => 'Kumpis, grybai, artišokai ir mocarela.'],
            ],
            'Домашний лимонад' => [
                'en' => ['name' => 'Homemade lemonade', 'description' => 'Lemon, mint, ice, and sparkling water.'],
                'lt' => ['name' => 'Naminis limonadas', 'description' => 'Citrina, mėta, ledas ir gazuotas vanduo.'],
            ],
            'Эспрессо' => [
                'en' => ['name' => 'Espresso', 'description' => 'Classic double espresso.'],
                'lt' => ['name' => 'Espresas', 'description' => 'Klasikinis dvigubas espresas.'],
            ],
            'Тирамису' => [
                'en' => ['name' => 'Tiramisu', 'description' => 'Mascarpone, coffee, and cocoa.'],
                'lt' => ['name' => 'Tiramisu', 'description' => 'Maskarponė, kava ir kakava.'],
            ],
            'Чизкейк' => [
                'en' => ['name' => 'Cheesecake', 'description' => 'Creamy cheesecake with berry sauce.'],
                'lt' => ['name' => 'Sūrio pyragas', 'description' => 'Kreminis sūrio pyragas su uogų padažu.'],
            ],
            default => [
                'en' => ['name' => $name, 'description' => $description],
                'lt' => ['name' => $name, 'description' => $description],
            ],
        };
    }

    private function ensureOrganizationMembership(
        Organization $organization,
        User $user,
        SystemRole $role,
        ?User $invitedBy,
    ): void {
        OrganizationUser::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role_id' => $this->role($role)->id,
                'status' => OrganizationUserStatus::Active,
                'joined_at' => now(),
                'invited_by_user_id' => $invitedBy?->id,
            ],
        );
    }

    private function ensureBranchAssignment(
        Organization $organization,
        Branch $branch,
        User $user,
        SystemRole $role,
        User $assignedBy,
    ): void {
        BranchUser::query()->updateOrCreate(
            [
                'branch_id' => $branch->id,
                'user_id' => $user->id,
            ],
            [
                'organization_id' => $organization->id,
                'role_id' => $this->role($role)->id,
                'status' => OrganizationUserStatus::Active,
                'assigned_at' => now(),
                'assigned_by_user_id' => $assignedBy->id,
            ],
        );
    }

    /**
     * @param  list<SystemPermission>  $permissions
     */
    private function grantPermissions(User $user, array $permissions): void
    {
        $permissionIds = Permission::query()
            ->whereIn('code', array_map(fn (SystemPermission $permission): string => $permission->value, $permissions))
            ->orderBy('id')
            ->pluck('id');

        $syncRows = $permissionIds
            ->mapWithKeys(fn (int $permissionId): array => [$permissionId => ['enabled' => true]])
            ->all();

        if ($syncRows === []) {
            return;
        }

        $user->permissionOverrides()->syncWithoutDetachingOrFail($syncRows);
    }

    private function role(SystemRole $role): Role
    {
        return Role::query()
            ->where('code', $role->value)
            ->firstOrFail();
    }
}
