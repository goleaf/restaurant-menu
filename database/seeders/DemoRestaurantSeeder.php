<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AreaNodes\CreateAreaNodeAction;
use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Actions\QrCodes\StoreDemoQrCodeImageAction;
use App\Actions\ServicePoints\CreateServicePointAction;
use App\Enums\AreaNodeType;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\MenuItemVariantType;
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
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use App\Support\MoneyFormatter;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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
        private readonly StoreDemoQrCodeImageAction $storeQrCodeImage,
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

        /** @var list<array{qr_code: QrCode, service_point: ServicePoint}> $qrImageSources */
        $qrImageSources = DB::transaction(function (): array {
            $qrImageSources = [];
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

            foreach ($branches as $branchKey => $branch) {
                $areas = $this->seedAreas($branch, $branchKey);
                $servicePoints = $this->seedServicePoints($branch, $areas, $branchKey);
                array_push($qrImageSources, ...$this->seedQrCodes($servicePoints, $owner));
                $this->seedMenu($branch, $branchKey);
            }

            return $qrImageSources;
        });

        foreach ($qrImageSources as $source) {
            $this->storeQrCodeImage->handle($source['qr_code'], $source['service_point']);
        }

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
    private function seedAreas(Branch $branch, string $branchKey): array
    {
        return collect($this->areaProfiles($branchKey))
            ->mapWithKeys(fn (array $profile, string $key): array => [
                $key => $this->area(
                    $branch,
                    $profile['name'],
                    $profile['type'],
                    $profile['icon'],
                    $profile['sort_order'],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, array{name: string, type: AreaNodeType, icon: string, sort_order: int}>
     */
    private function areaProfiles(string $branchKey): array
    {
        return match ($branchKey) {
            self::PRIMARY_BRANCH_KEY => [
                'main' => ['name' => 'Главный зал', 'type' => AreaNodeType::Hall, 'icon' => 'squares-2x2', 'sort_order' => 10],
                'terrace' => ['name' => 'Терраса', 'type' => AreaNodeType::Terrace, 'icon' => 'sun', 'sort_order' => 20],
                'bar' => ['name' => 'Бар', 'type' => AreaNodeType::BarArea, 'icon' => 'beaker', 'sort_order' => 30],
            ],
            'bella_pizza_terrace' => [
                'terrace' => ['name' => 'Большая терраса', 'type' => AreaNodeType::Terrace, 'icon' => 'sun', 'sort_order' => 10],
                'bar' => ['name' => 'Бар террасы', 'type' => AreaNodeType::BarArea, 'icon' => 'beaker', 'sort_order' => 20],
            ],
            'sushi_master_center' => [
                'main' => ['name' => 'Суши-зал', 'type' => AreaNodeType::Hall, 'icon' => 'squares-2x2', 'sort_order' => 10],
                'pickup' => ['name' => 'Зона выдачи', 'type' => AreaNodeType::PickupArea, 'icon' => 'shopping-bag', 'sort_order' => 20],
            ],
            'coffee_bar_small_hall' => [
                'main' => ['name' => 'Малый зал', 'type' => AreaNodeType::Hall, 'icon' => 'squares-2x2', 'sort_order' => 10],
                'bar' => ['name' => 'Кофейный бар', 'type' => AreaNodeType::BarArea, 'icon' => 'beaker', 'sort_order' => 20],
            ],
            default => throw new RuntimeException("Unknown demo branch key [$branchKey]."),
        };
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
    private function seedServicePoints(Branch $branch, array $areas, string $branchKey): array
    {
        $rows = $this->servicePointProfiles($branchKey);

        return collect($rows)
            ->map(fn (array $row): ServicePoint => $this->servicePoint($branch, $areas[$row['area']], $row))
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     area: string,
     *     type: ServicePointType,
     *     name: string,
     *     display_number: string,
     *     internal_code: string,
     *     capacity: int,
     *     icon: string,
     *     position_x: int,
     *     position_y: int
     * }>
     */
    private function servicePointProfiles(string $branchKey): array
    {
        return match ($branchKey) {
            self::PRIMARY_BRANCH_KEY => [
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
            ],
            'bella_pizza_terrace' => [
                ['area' => 'terrace', 'type' => ServicePointType::Table, 'name' => 'Терраса 1', 'display_number' => 'T1', 'internal_code' => 'DEMO-PIZZA-TERRACE-T1', 'capacity' => 4, 'icon' => 'sun', 'position_x' => 20, 'position_y' => 30],
                ['area' => 'terrace', 'type' => ServicePointType::Table, 'name' => 'Терраса 2', 'display_number' => 'T2', 'internal_code' => 'DEMO-PIZZA-TERRACE-T2', 'capacity' => 4, 'icon' => 'sun', 'position_x' => 45, 'position_y' => 30],
                ['area' => 'terrace', 'type' => ServicePointType::Table, 'name' => 'Терраса 3', 'display_number' => 'T3', 'internal_code' => 'DEMO-PIZZA-TERRACE-T3', 'capacity' => 6, 'icon' => 'sun', 'position_x' => 70, 'position_y' => 30],
                ['area' => 'bar', 'type' => ServicePointType::BarSeat, 'name' => 'Бар террасы 1', 'display_number' => 'B1', 'internal_code' => 'DEMO-PIZZA-TERRACE-B1', 'capacity' => 1, 'icon' => 'beaker', 'position_x' => 40, 'position_y' => 75],
            ],
            'sushi_master_center' => [
                ['area' => 'main', 'type' => ServicePointType::Table, 'name' => 'Суши 1', 'display_number' => 'S1', 'internal_code' => 'DEMO-SUSHI-CENTER-S1', 'capacity' => 2, 'icon' => 'squares-2x2', 'position_x' => 20, 'position_y' => 30],
                ['area' => 'main', 'type' => ServicePointType::Table, 'name' => 'Суши 2', 'display_number' => 'S2', 'internal_code' => 'DEMO-SUSHI-CENTER-S2', 'capacity' => 4, 'icon' => 'squares-2x2', 'position_x' => 50, 'position_y' => 30],
                ['area' => 'main', 'type' => ServicePointType::Table, 'name' => 'Суши 3', 'display_number' => 'S3', 'internal_code' => 'DEMO-SUSHI-CENTER-S3', 'capacity' => 4, 'icon' => 'squares-2x2', 'position_x' => 80, 'position_y' => 30],
                ['area' => 'pickup', 'type' => ServicePointType::PickupWindow, 'name' => 'Выдача 1', 'display_number' => 'P1', 'internal_code' => 'DEMO-SUSHI-CENTER-P1', 'capacity' => 1, 'icon' => 'shopping-bag', 'position_x' => 50, 'position_y' => 75],
            ],
            'coffee_bar_small_hall' => [
                ['area' => 'main', 'type' => ServicePointType::Table, 'name' => 'Кофе 1', 'display_number' => 'C1', 'internal_code' => 'DEMO-COFFEE-HALL-C1', 'capacity' => 2, 'icon' => 'squares-2x2', 'position_x' => 25, 'position_y' => 30],
                ['area' => 'main', 'type' => ServicePointType::Table, 'name' => 'Кофе 2', 'display_number' => 'C2', 'internal_code' => 'DEMO-COFFEE-HALL-C2', 'capacity' => 2, 'icon' => 'squares-2x2', 'position_x' => 65, 'position_y' => 30],
                ['area' => 'bar', 'type' => ServicePointType::BarSeat, 'name' => 'Кофейный бар 1', 'display_number' => 'B1', 'internal_code' => 'DEMO-COFFEE-BAR-B1', 'capacity' => 1, 'icon' => 'beaker', 'position_x' => 35, 'position_y' => 75],
                ['area' => 'bar', 'type' => ServicePointType::BarSeat, 'name' => 'Кофейный бар 2', 'display_number' => 'B2', 'internal_code' => 'DEMO-COFFEE-BAR-B2', 'capacity' => 1, 'icon' => 'beaker', 'position_x' => 65, 'position_y' => 75],
            ],
            default => throw new RuntimeException("Unknown demo branch key [$branchKey]."),
        };
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
     * @return list<array{qr_code: QrCode, service_point: ServicePoint}>
     */
    private function seedQrCodes(array $servicePoints, User $owner): array
    {
        $sources = [];

        foreach ($servicePoints as $servicePoint) {
            $sources[] = [
                'qr_code' => $this->generateQrCode->handle($servicePoint, $owner),
                'service_point' => $servicePoint,
            ];
        }

        return $sources;
    }

    private function seedMenu(Branch $branch, string $branchKey): void
    {
        $menuName = match ($branchKey) {
            self::PRIMARY_BRANCH_KEY => 'Bella Pizza Demo Menu',
            'bella_pizza_terrace' => 'Bella Pizza Terrace Menu',
            'sushi_master_center' => 'Sushi Master Demo Menu',
            'coffee_bar_small_hall' => 'Coffee Bar Demo Menu',
            default => throw new RuntimeException("Unknown demo branch key [$branchKey]."),
        };
        $menu = Menu::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('name', $menuName)
            ->first() ?? new Menu;

        if ($menu->trashed()) {
            $menu->restore();
        }

        $menu->forceFill([
            'branch_id' => $branch->id,
            'name' => $menuName,
            'status' => MenuStatus::Active,
            'sort_order' => 10,
        ])->save();

        $departments = KitchenDepartment::query()
            ->where('branch_id', $branch->id)
            ->whereIn('type', [
                KitchenDepartmentType::Kitchen->value,
                KitchenDepartmentType::Bar->value,
                KitchenDepartmentType::Dessert->value,
            ])
            ->pluck('id', 'type');

        if ($branchKey === 'sushi_master_center') {
            $this->seedSushiMenu($menu, $departments);

            return;
        }

        if ($branchKey === 'coffee_bar_small_hall') {
            $this->seedCoffeeMenu($menu, $departments);

            return;
        }

        $pizza = $this->category($menu, 'Пицца', 'Классическая пицца для быстрого demo-заказа.', 'fire', 10);
        $drinks = $this->category($menu, 'Напитки', 'Горячие и холодные напитки.', 'beaker', 20);
        $desserts = $this->category($menu, 'Десерты', 'Сладкое завершение заказа.', 'cake', 30);

        $this->item($menu, $pizza, $departments->get(KitchenDepartmentType::Kitchen->value), [
            'name' => 'Маргарита',
            'description' => 'Томатный соус, моцарелла, базилик.',
            'price' => '9.50',
            'allergens' => [MenuAllergen::Gluten, MenuAllergen::Milk],
            'dietary_labels' => [MenuDietaryLabel::Vegetarian],
            'weight' => '420.00',
            'calories' => 820,
            'sort_order' => 10,
        ]);
        $this->item($menu, $pizza, $departments->get(KitchenDepartmentType::Kitchen->value), [
            'name' => 'Пепперони',
            'description' => 'Пикантная пепперони, моцарелла, томатный соус.',
            'price' => '11.90',
            'allergens' => [MenuAllergen::Gluten, MenuAllergen::Milk],
            'weight' => '450.00',
            'calories' => 980,
            'sort_order' => 20,
        ]);
        $this->item($menu, $pizza, $departments->get(KitchenDepartmentType::Kitchen->value), [
            'name' => 'Капричоза',
            'description' => 'Ветчина, грибы, артишоки и моцарелла.',
            'price' => '12.50',
            'allergens' => [MenuAllergen::Gluten, MenuAllergen::Milk],
            'weight' => '470.00',
            'calories' => 1020,
            'sort_order' => 30,
        ]);
        $this->item($menu, $drinks, $departments->get(KitchenDepartmentType::Bar->value), [
            'name' => 'Домашний лимонад',
            'description' => 'Лимон, мята, лёд и газированная вода.',
            'price' => '4.20',
            'dietary_labels' => [MenuDietaryLabel::Vegan, MenuDietaryLabel::GlutenFree, MenuDietaryLabel::LactoseFree],
            'volume' => '0.40',
            'calories' => 120,
            'sort_order' => 40,
        ]);
        $this->item($menu, $drinks, $departments->get(KitchenDepartmentType::Bar->value), [
            'name' => 'Эспрессо',
            'description' => 'Классический двойной эспрессо.',
            'price' => '2.80',
            'dietary_labels' => [MenuDietaryLabel::Vegan, MenuDietaryLabel::GlutenFree, MenuDietaryLabel::LactoseFree],
            'volume' => '0.06',
            'calories' => 5,
            'sort_order' => 50,
        ]);
        $this->item($menu, $desserts, $departments->get(KitchenDepartmentType::Dessert->value), [
            'name' => 'Тирамису',
            'description' => 'Маскарпоне, кофе и какао.',
            'price' => '5.90',
            'allergens' => [MenuAllergen::Gluten, MenuAllergen::Eggs, MenuAllergen::Milk],
            'dietary_labels' => [MenuDietaryLabel::Vegetarian],
            'weight' => '160.00',
            'calories' => 410,
            'sort_order' => 60,
        ]);
        $this->item($menu, $desserts, $departments->get(KitchenDepartmentType::Dessert->value), [
            'name' => 'Чизкейк',
            'description' => 'Сливочный чизкейк с ягодным соусом.',
            'price' => '5.50',
            'allergens' => [MenuAllergen::Gluten, MenuAllergen::Eggs, MenuAllergen::Milk],
            'dietary_labels' => [MenuDietaryLabel::Vegetarian],
            'weight' => '150.00',
            'calories' => 390,
            'sort_order' => 70,
        ]);
    }

    /**
     * @param  Collection<string, int>  $departments
     */
    private function seedSushiMenu(Menu $menu, Collection $departments): void
    {
        $rolls = $this->category($menu, 'Роллы', 'Роллы и нигири, приготовленные после заказа.', 'squares-2x2', 10);
        $departmentId = $departments->get(KitchenDepartmentType::Kitchen->value);

        $this->item($menu, $rolls, $departmentId, [
            'name' => 'Филадельфия',
            'description' => 'Лосось, сливочный сыр, огурец и рис.',
            'price' => '12.90',
            'allergens' => [MenuAllergen::Fish, MenuAllergen::Milk],
            'weight' => '260.00',
            'calories' => 520,
            'sort_order' => 10,
        ]);
        $this->item($menu, $rolls, $departmentId, [
            'name' => 'Калифорния',
            'description' => 'Краб, авокадо, огурец и тобико.',
            'price' => '11.50',
            'allergens' => [MenuAllergen::Crustaceans, MenuAllergen::Fish],
            'weight' => '250.00',
            'calories' => 480,
            'sort_order' => 20,
        ]);
        $this->item($menu, $rolls, $departmentId, [
            'name' => 'Нигири с лососем',
            'description' => 'Лосось и рис, две штуки.',
            'price' => '6.20',
            'allergens' => [MenuAllergen::Fish],
            'dietary_labels' => [MenuDietaryLabel::GlutenFree, MenuDietaryLabel::LactoseFree],
            'weight' => '110.00',
            'calories' => 210,
            'sort_order' => 30,
        ]);
    }

    /**
     * @param  Collection<string, int>  $departments
     */
    private function seedCoffeeMenu(Menu $menu, Collection $departments): void
    {
        $coffee = $this->category($menu, 'Кофе и выпечка', 'Кофе из свежеобжаренных зёрен и свежая выпечка.', 'beaker', 10);
        $barDepartmentId = $departments->get(KitchenDepartmentType::Bar->value);
        $dessertDepartmentId = $departments->get(KitchenDepartmentType::Dessert->value);

        $this->item($menu, $coffee, $barDepartmentId, [
            'name' => 'Капучино',
            'description' => 'Эспрессо и взбитое молоко.',
            'price' => '3.80',
            'allergens' => [MenuAllergen::Milk],
            'dietary_labels' => [MenuDietaryLabel::Vegetarian, MenuDietaryLabel::GlutenFree],
            'volume' => '0.25',
            'calories' => 120,
            'sort_order' => 10,
        ]);
        $this->item($menu, $coffee, $barDepartmentId, [
            'name' => 'Флэт уайт',
            'description' => 'Двойной эспрессо и тонкий слой молочной пены.',
            'price' => '4.20',
            'allergens' => [MenuAllergen::Milk],
            'dietary_labels' => [MenuDietaryLabel::Vegetarian, MenuDietaryLabel::GlutenFree],
            'volume' => '0.20',
            'calories' => 100,
            'sort_order' => 20,
        ]);
        $this->item($menu, $coffee, $dessertDepartmentId, [
            'name' => 'Круассан',
            'description' => 'Сливочный круассан, выпеченный сегодня.',
            'price' => '3.20',
            'allergens' => [MenuAllergen::Gluten, MenuAllergen::Eggs, MenuAllergen::Milk],
            'dietary_labels' => [MenuDietaryLabel::Vegetarian],
            'weight' => '85.00',
            'calories' => 310,
            'sort_order' => 30,
        ]);
    }

    private function category(Menu $menu, string $name, string $description, string $icon, int $sortOrder): MenuCategory
    {
        $category = MenuCategory::withTrashed()
            ->where('menu_id', $menu->id)
            ->where('name', $name)
            ->first() ?? new MenuCategory;

        if ($category->trashed()) {
            $category->restore();
        }

        $category->forceFill([
            'menu_id' => $menu->id,
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ])->save();

        foreach (DemoMenuTranslations::category($name, $description) as $languageCode => $translation) {
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
     * @param  array{name: string, description: string, price: string, allergens?: list<MenuAllergen>, dietary_labels?: list<MenuDietaryLabel>, weight?: string, volume?: string, calories: int, sort_order: int}  $data
     */
    private function item(Menu $menu, MenuCategory $category, ?int $departmentId, array $data): MenuItem
    {
        $item = MenuItem::withTrashed()
            ->where('menu_id', $menu->id)
            ->where('name', $data['name'])
            ->first() ?? new MenuItem;

        if ($item->trashed()) {
            $item->restore();
        }

        $item->forceFill([
            'menu_id' => $menu->id,
            'name' => $data['name'],
            'category_id' => $category->id,
            'kitchen_department_id' => $departmentId,
            'description' => $data['description'],
            'price_cents' => MoneyFormatter::decimalToCents($data['price']),
            'allergens' => array_map(
                fn (MenuAllergen $allergen): string => $allergen->value,
                $data['allergens'] ?? [],
            ),
            'dietary_labels' => array_map(
                fn (MenuDietaryLabel $label): string => $label->value,
                $data['dietary_labels'] ?? [],
            ),
            'weight' => $data['weight'] ?? null,
            'volume' => $data['volume'] ?? null,
            'calories' => $data['calories'],
            'is_available' => true,
            'sort_order' => $data['sort_order'],
        ])->save();

        foreach (DemoMenuTranslations::item($data['name'], $data['description']) as $languageCode => $translation) {
            MenuItemTranslation::query()->updateOrCreate(
                [
                    'menu_item_id' => $item->id,
                    'language_code' => $languageCode,
                ],
                $translation,
            );
        }

        $this->seedItemVariants($item);

        return $item;
    }

    private function seedItemVariants(MenuItem $item): void
    {
        $profiles = $this->variantProfiles($item);

        if ($profiles === []) {
            return;
        }

        $item->variants()->update(['is_default' => false]);

        foreach ($profiles as $profile) {
            $variant = MenuItemVariant::query()->updateOrCreate(
                [
                    'menu_item_id' => $item->id,
                    'type' => $profile['type']->value,
                    'name' => $profile['name'],
                ],
                [
                    'price_cents' => $profile['price_cents'],
                    'weight' => $profile['weight'],
                    'volume' => $profile['volume'],
                    'is_default' => $profile['is_default'],
                    'is_available' => true,
                    'sort_order' => $profile['sort_order'],
                ],
            );

            foreach ($profile['translations'] as $languageCode => $name) {
                MenuItemVariantTranslation::query()->updateOrCreate(
                    [
                        'menu_item_variant_id' => $variant->id,
                        'language_code' => $languageCode,
                    ],
                    ['name' => $name],
                );
            }
        }
    }

    /**
     * @return list<array{
     *     type: MenuItemVariantType,
     *     name: string,
     *     price_cents: int,
     *     weight: string|null,
     *     volume: string|null,
     *     is_default: bool,
     *     sort_order: int,
     *     translations: array{en: string, lt: string, ru: string}
     * }>
     */
    private function variantProfiles(MenuItem $item): array
    {
        $basePriceCents = $item->price_cents;

        return match ($item->name) {
            'Маргарита', 'Пепперони', 'Капричоза' => [
                $this->portionProfile('Малая (25 см)', max(1, $basePriceCents - 150), '350.00', null, false, 10, 'Small (25 cm)', 'Maža (25 cm)'),
                $this->portionProfile('Стандартная (30 см)', $basePriceCents, $item->weight, null, true, 20, 'Regular (30 cm)', 'Standartinė (30 cm)'),
                $this->portionProfile('Большая (35 см)', $basePriceCents + 350, '650.00', null, false, 30, 'Large (35 cm)', 'Didelė (35 cm)'),
            ],
            'Домашний лимонад' => [
                $this->portionProfile('400 мл', $basePriceCents, null, '0.40', true, 10, '400 ml', '400 ml'),
                $this->portionProfile('600 мл', $basePriceCents + 140, null, '0.60', false, 20, '600 ml', '600 ml'),
            ],
            'Филадельфия', 'Калифорния' => [
                $this->portionProfile('6 шт.', max(1, $basePriceCents - 350), null, null, false, 10, '6 pieces', '6 vnt.'),
                $this->portionProfile('12 шт.', $basePriceCents, $item->weight, null, true, 20, '12 pieces', '12 vnt.'),
            ],
            'Капучино' => [
                $this->portionProfile('250 мл', $basePriceCents, null, '0.25', true, 10, '250 ml', '250 ml'),
                $this->portionProfile('350 мл', $basePriceCents + 80, null, '0.35', false, 20, '350 ml', '350 ml'),
            ],
            'Флэт уайт' => [
                $this->portionProfile('200 мл', $basePriceCents, null, '0.20', true, 10, '200 ml', '200 ml'),
                $this->portionProfile('300 мл', $basePriceCents + 80, null, '0.30', false, 20, '300 ml', '300 ml'),
            ],
            default => [],
        };
    }

    /**
     * @return array{
     *     type: MenuItemVariantType,
     *     name: string,
     *     price_cents: int,
     *     weight: string|null,
     *     volume: string|null,
     *     is_default: bool,
     *     sort_order: int,
     *     translations: array{en: string, lt: string, ru: string}
     * }
     */
    private function portionProfile(
        string $russianName,
        int $priceCents,
        ?string $weight,
        ?string $volume,
        bool $isDefault,
        int $sortOrder,
        string $englishName,
        string $lithuanianName,
    ): array {
        return [
            'type' => MenuItemVariantType::Portion,
            'name' => $russianName,
            'price_cents' => $priceCents,
            'weight' => $weight,
            'volume' => $volume,
            'is_default' => $isDefault,
            'sort_order' => $sortOrder,
            'translations' => [
                'en' => $englishName,
                'lt' => $lithuanianName,
                'ru' => $russianName,
            ],
        ];
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
