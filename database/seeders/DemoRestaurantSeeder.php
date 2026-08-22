<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Enums\AreaNodeType;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchSetting;
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
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoRestaurantSeeder extends Seeder
{
    private const ORGANIZATION_NAME = 'Demo Food Group';

    private const PRIMARY_BRANCH_KEY = 'bella_pizza_old_town';

    private const LEGACY_PRIMARY_BRANCH_NAME = 'Demo Old Town';

    public function __construct(
        private readonly EnsureBranchSettingsAction $ensureBranchSettings,
        private readonly SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments,
        private readonly CreateAreaNodeAction $createAreaNode,
        private readonly CreateServicePointAction $createServicePoint,
        private readonly GenerateQrCodeForServicePointAction $generateQrCode,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (strtolower((string) config('app.env')) === 'production') {
            throw new RuntimeException('DemoRestaurantSeeder is development-only and cannot run while APP_ENV=production.');
        }

        $this->call(SystemPermissionsSeeder::class);

        DB::transaction(function (): void {
            $superadmin = $this->demoUser(SystemRole::Superadmin);
            $this->syncPermissions($superadmin, []);

            $owner = $this->demoUser(SystemRole::Owner);
            $organization = $this->demoOrganization($owner);
            $brands = $this->demoBrands($organization);
            $branches = $this->demoBranches($brands);

            foreach ($branches as $branch) {
                $this->ensureBranchSetup($branch);
            }

            $this->seedStaff($organization, $branches, $owner);

            $primaryBranch = $branches[self::PRIMARY_BRANCH_KEY];

            $areas = $this->seedAreas($primaryBranch);
            $servicePoints = $this->seedServicePoints($primaryBranch, $areas);
            $this->seedQrCodes($servicePoints, $owner);
            $this->seedMenu($primaryBranch);
        });

        $this->call(DemoOperationalStateSeeder::class);
    }

    private function demoOrganization(User $owner): Organization
    {
        $organization = Organization::withTrashed()
            ->where('name', self::ORGANIZATION_NAME)
            ->first();

        $attributes = Organization::factory()
            ->demoFoodGroup($owner)
            ->make()
            ->getAttributes();

        if ($organization instanceof Organization) {
            if ($organization->trashed()) {
                $organization->restore();
            }

            $organization->forceFill($attributes)->save();
        } else {
            $organization = Organization::factory()
                ->demoFoodGroup($owner)
                ->create();
        }

        $this->ensureOrganizationMembership($organization, $owner, SystemRole::Owner, null);

        return $organization->refresh();
    }

    /**
     * @return array{bella_pizza: Brand, sushi_master: Brand, coffee_bar_demo: Brand}
     */
    private function demoBrands(Organization $organization): array
    {
        return [
            'bella_pizza' => $this->demoBrand($organization, 'bellaPizza'),
            'sushi_master' => $this->demoBrand($organization, 'sushiMaster'),
            'coffee_bar_demo' => $this->demoBrand($organization, 'coffeeBarDemo'),
        ];
    }

    private function demoBrand(Organization $organization, string $factoryState): Brand
    {
        $factory = Brand::factory()
            ->for($organization)
            ->{$factoryState}();
        $attributes = $factory->make()->getAttributes();
        $name = (string) $attributes['name'];

        $brand = Brand::withTrashed()
            ->where('organization_id', $organization->id)
            ->where('name', $name)
            ->first();

        if ($brand instanceof Brand) {
            if ($brand->trashed()) {
                $brand->restore();
            }

            $brand->forceFill($attributes)->save();

            return $brand->refresh();
        }

        return Brand::factory()
            ->for($organization)
            ->{$factoryState}()
            ->create();
    }

    /**
     * @param  array{bella_pizza: Brand, sushi_master: Brand, coffee_bar_demo: Brand}  $brands
     * @return array{
     *     bella_pizza_old_town: Branch,
     *     bella_pizza_terrace: Branch,
     *     sushi_master_center: Branch,
     *     coffee_bar_small_hall: Branch
     * }
     */
    private function demoBranches(array $brands): array
    {
        return [
            'bella_pizza_old_town' => $this->demoBranch(
                $brands['bella_pizza'],
                'bellaPizzaOldTown',
                self::LEGACY_PRIMARY_BRANCH_NAME,
            ),
            'bella_pizza_terrace' => $this->demoBranch($brands['bella_pizza'], 'bellaPizzaTerrace'),
            'sushi_master_center' => $this->demoBranch($brands['sushi_master'], 'sushiMasterCenter'),
            'coffee_bar_small_hall' => $this->demoBranch($brands['coffee_bar_demo'], 'coffeeBarSmallHall'),
        ];
    }

    private function demoBranch(Brand $brand, string $factoryState, ?string $legacyName = null): Branch
    {
        $factory = Branch::factory()->{$factoryState}($brand);
        $attributes = $factory->make()->getAttributes();
        $name = (string) $attributes['name'];

        $branch = Branch::withTrashed()
            ->where('brand_id', $brand->id)
            ->where('name', $name)
            ->first();

        if (! $branch instanceof Branch && $legacyName !== null) {
            $branch = Branch::withTrashed()
                ->where('brand_id', $brand->id)
                ->where('name', $legacyName)
                ->first();
        }

        if ($branch instanceof Branch) {
            if ($branch->trashed()) {
                $branch->restore();
            }

            $branch->forceFill($attributes)->save();
            $this->deleteLegacyBranchDuplicates($brand, $branch, $legacyName);

            return $branch->refresh();
        }

        $branch = Branch::factory()
            ->{$factoryState}($brand)
            ->create();

        $this->deleteLegacyBranchDuplicates($brand, $branch, $legacyName);

        return $branch;
    }

    private function deleteLegacyBranchDuplicates(Brand $brand, Branch $branch, ?string $legacyName): void
    {
        if ($legacyName === null) {
            return;
        }

        Branch::query()
            ->where('brand_id', $brand->id)
            ->where('name', $legacyName)
            ->whereKeyNot($branch->id)
            ->delete();
    }

    private function ensureBranchSetup(Branch $branch): void
    {
        $settings = $this->ensureBranchSettings->handle($branch);
        $attributes = BranchSetting::factory()
            ->demoReadyForService($branch)
            ->make()
            ->toArray();

        $settings->forceFill($attributes)->save();

        $this->seedKitchenDepartments->handle($branch);
    }

    private function demoUser(SystemRole $role): User
    {
        $identity = DemoAccountCatalog::forRole($role);
        $user = User::query()
            ->select(['id', 'name', 'email', 'locale', 'email_verified_at', 'password'])
            ->where('email', $identity['email'])
            ->first();

        if (! $user instanceof User) {
            $user = User::factory()
                ->demoIdentity($identity['name'], $identity['email'])
                ->create();
        } else {
            $attributes = User::factory()
                ->demoIdentity($identity['name'], $identity['email'])
                ->make()
                ->getAttributes();

            if (Hash::check(UserFactory::DEMO_PASSWORD, (string) $user->password)) {
                unset($attributes['password']);
            }

            $user->forceFill($attributes)->save();
        }

        $roleModel = $this->role($role);
        $user->roles()->sync([$roleModel->id]);

        return $user->refresh();
    }

    /**
     * @param  array{
     *     bella_pizza_old_town: Branch,
     *     bella_pizza_terrace: Branch,
     *     sushi_master_center: Branch,
     *     coffee_bar_small_hall: Branch
     * }  $branches
     */
    private function seedStaff(Organization $organization, array $branches, User $owner): void
    {
        $director = $this->demoUser(SystemRole::Director);
        $admin = $this->demoUser(SystemRole::RestaurantAdmin);
        $manager = $this->demoUser(SystemRole::ShiftManager);
        $waiter = $this->demoUser(SystemRole::Waiter);
        $headChef = $this->demoUser(SystemRole::HeadChef);
        $cook = $this->demoUser(SystemRole::Cook);
        $bartender = $this->demoUser(SystemRole::Bartender);
        $cashier = $this->demoUser(SystemRole::Cashier);
        $accountant = $this->demoUser(SystemRole::Accountant);
        $marketer = $this->demoUser(SystemRole::Marketer);

        $allBranches = array_values($branches);

        $assignments = [
            [$owner, SystemRole::Owner, null, $owner, [], $allBranches],
            [$director, SystemRole::Director, $owner, $owner, [], $allBranches],
            [$admin, SystemRole::RestaurantAdmin, $director, $director, [], $allBranches],
            [$manager, SystemRole::ShiftManager, $admin, $admin, [], $allBranches],
            [
                $waiter,
                SystemRole::Waiter,
                $manager,
                $manager,
                [],
                $this->branchSubset($branches, ['bella_pizza_old_town', 'bella_pizza_terrace']),
            ],
            [
                $headChef,
                SystemRole::HeadChef,
                $manager,
                $manager,
                [],
                $this->branchSubset($branches, ['bella_pizza_old_town', 'bella_pizza_terrace', 'sushi_master_center']),
            ],
            [
                $cook,
                SystemRole::Cook,
                $headChef,
                $headChef,
                [],
                $this->branchSubset($branches, ['bella_pizza_old_town', 'sushi_master_center']),
            ],
            [
                $bartender,
                SystemRole::Bartender,
                $manager,
                $manager,
                [],
                $this->branchSubset($branches, ['bella_pizza_terrace', 'coffee_bar_small_hall']),
            ],
            [
                $cashier,
                SystemRole::Cashier,
                $manager,
                $manager,
                [],
                $this->branchSubset($branches, ['bella_pizza_old_town', 'coffee_bar_small_hall']),
            ],
            [$accountant, SystemRole::Accountant, $director, $director, [], $allBranches],
            [$marketer, SystemRole::Marketer, $admin, $admin, [], $allBranches],
        ];

        foreach ($assignments as [$user, $role, $invitedBy, $assignedBy, $permissions, $assignedBranches]) {
            $this->ensureOrganizationMembership($organization, $user, $role, $invitedBy);
            $this->syncBranchAssignments($organization, $user, $role, $assignedBy, $assignedBranches);
            $this->syncPermissions($user, $permissions);
        }
    }

    /**
     * @param  array<string, Branch>  $branches
     * @param  list<string>  $keys
     * @return list<Branch>
     */
    private function branchSubset(array $branches, array $keys): array
    {
        return array_map(
            fn (string $key): Branch => $branches[$key],
            $keys,
        );
    }

    /**
     * @return array<string, AreaNode>
     */
    private function seedAreas(Branch $branch): array
    {
        return [
            'main' => $this->area($branch, 'Главный зал', AreaNodeType::Hall, 'squares-2x2', 10),
            'terrace' => $this->area($branch, 'Терраса', AreaNodeType::Terrace, 'sun', 20),
            'bar' => $this->area($branch, 'Бар', AreaNodeType::BarArea, 'beaker', 30),
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

        return $this->createAreaNode->handle($branch, [
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
                'icon' => 'squares-2x2',
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
                'icon' => 'squares-2x2',
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
                'icon' => 'squares-2x2',
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
                'icon' => 'beaker',
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
                'icon' => 'beaker',
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
            $servicePoint = $this->createServicePoint->handle($branch, [
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
            $this->generateQrCode->handle($servicePoint, $owner);
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

        $pizza = $this->category($menu, 'Пицца', 'Классическая пицца для быстрого demo-заказа.', 'fire', 10);
        $drinks = $this->category($menu, 'Напитки', 'Горячие и холодные напитки.', 'beaker', 20);
        $desserts = $this->category($menu, 'Десерты', 'Сладкое завершение заказа.', 'cake', 30);

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
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Напитки' => [
                'en' => ['name' => 'Drinks', 'description' => 'Hot and cold drinks.'],
                'lt' => ['name' => 'Gėrimai', 'description' => 'Karšti ir šalti gėrimai.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Десерты' => [
                'en' => ['name' => 'Desserts', 'description' => 'A sweet finish to the order.'],
                'lt' => ['name' => 'Desertai', 'description' => 'Saldus užsakymo užbaigimas.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            default => [
                'en' => ['name' => $name, 'description' => $description],
                'lt' => ['name' => $name, 'description' => $description],
                'ru' => ['name' => $name, 'description' => $description],
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
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Пепперони' => [
                'en' => ['name' => 'Pepperoni', 'description' => 'Spicy pepperoni, mozzarella, tomato sauce.'],
                'lt' => ['name' => 'Pepperoni', 'description' => 'Aitri pepperoni dešra, mocarela, pomidorų padažas.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Капричоза' => [
                'en' => ['name' => 'Capricciosa', 'description' => 'Ham, mushrooms, artichokes, and mozzarella.'],
                'lt' => ['name' => 'Capricciosa', 'description' => 'Kumpis, grybai, artišokai ir mocarela.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Домашний лимонад' => [
                'en' => ['name' => 'Homemade lemonade', 'description' => 'Lemon, mint, ice, and sparkling water.'],
                'lt' => ['name' => 'Naminis limonadas', 'description' => 'Citrina, mėta, ledas ir gazuotas vanduo.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Эспрессо' => [
                'en' => ['name' => 'Espresso', 'description' => 'Classic double espresso.'],
                'lt' => ['name' => 'Espresas', 'description' => 'Klasikinis dvigubas espresas.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Тирамису' => [
                'en' => ['name' => 'Tiramisu', 'description' => 'Mascarpone, coffee, and cocoa.'],
                'lt' => ['name' => 'Tiramisu', 'description' => 'Maskarponė, kava ir kakava.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            'Чизкейк' => [
                'en' => ['name' => 'Cheesecake', 'description' => 'Creamy cheesecake with berry sauce.'],
                'lt' => ['name' => 'Sūrio pyragas', 'description' => 'Kreminis sūrio pyragas su uogų padažu.'],
                'ru' => ['name' => $name, 'description' => $description],
            ],
            default => [
                'en' => ['name' => $name, 'description' => $description],
                'lt' => ['name' => $name, 'description' => $description],
                'ru' => ['name' => $name, 'description' => $description],
            ],
        };
    }

    private function ensureOrganizationMembership(
        Organization $organization,
        User $user,
        SystemRole $role,
        ?User $invitedBy,
    ): void {
        $membership = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first() ?? new OrganizationUser;

        $membership->forceFill([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $this->role($role)->id,
            'status' => OrganizationUserStatus::Active,
            'joined_at' => now(),
            'invited_by_user_id' => $invitedBy?->id,
        ])->save();
    }

    /**
     * @param  list<Branch>  $branches
     */
    private function syncBranchAssignments(
        Organization $organization,
        User $user,
        SystemRole $role,
        User $assignedBy,
        array $branches,
    ): void {
        $branchIds = array_map(
            fn (Branch $branch): int => (int) $branch->id,
            $branches,
        );

        foreach ($branches as $branch) {
            $this->ensureBranchAssignment($organization, $branch, $user, $role, $assignedBy);
        }

        $staleAssignments = BranchUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id);

        if ($branchIds === []) {
            $staleAssignments->delete();

            return;
        }

        $staleAssignments
            ->whereNotIn('branch_id', $branchIds)
            ->delete();
    }

    private function ensureBranchAssignment(
        Organization $organization,
        Branch $branch,
        User $user,
        SystemRole $role,
        User $assignedBy,
    ): void {
        $assignment = BranchUser::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->first() ?? new BranchUser;

        $assignment->forceFill([
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role_id' => $this->role($role)->id,
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => now(),
            'assigned_by_user_id' => $assignedBy->id,
        ])->save();
    }

    /**
     * @param  list<SystemPermission>  $permissions
     */
    private function syncPermissions(User $user, array $permissions): void
    {
        $permissionIds = Permission::query()
            ->whereIn('code', array_map(fn (SystemPermission $permission): string => $permission->value, $permissions))
            ->orderBy('id')
            ->pluck('id');

        $syncRows = $permissionIds
            ->mapWithKeys(fn (int $permissionId): array => [$permissionId => ['enabled' => true]])
            ->all();

        $user->permissionOverrides()->sync($syncRows);
    }

    private function role(SystemRole $role): Role
    {
        return Role::query()
            ->where('code', $role->value)
            ->firstOrFail();
    }
}
