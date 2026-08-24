<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Enums\AreaNodeType;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuAllergen;
use App\Enums\MenuItemVariantType;
use App\Enums\ServicePointType;
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
use App\Models\MenuTranslation;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DemoTenantPortfolioSeeder extends Seeder
{
    /**
     * @var list<array{
     *     owner_name: string,
     *     owner_email: string,
     *     organization: string,
     *     brand: string,
     *     branch: string,
     *     address: string,
     *     city: string,
     *     area: string,
     *     area_type: AreaNodeType,
     *     menu: array<string, string>,
     *     category: array<string, string>,
     *     item: array<string, string>,
     *     description: array<string, string>,
     *     variant: array<string, string>,
     *     price_cents: int,
     *     allergens: list<MenuAllergen>
     * }>
     */
    private const array PROFILES = [
        [
            'owner_name' => 'Demo Baltic Owner',
            'owner_email' => 'owner.baltic@demo.test',
            'organization' => 'Baltic Table Group',
            'brand' => 'Amber Bistro',
            'branch' => 'Amber Bistro Kaunas',
            'address' => 'Karaliaus Mindaugo pr. 18',
            'city' => 'Kaunas',
            'area' => 'River Room',
            'area_type' => AreaNodeType::Room,
            'menu' => ['en' => 'Amber Seasonal Menu', 'lt' => 'Gintarinis sezoninis meniu', 'ru' => 'Янтарное сезонное меню'],
            'category' => ['en' => 'Seasonal favourites', 'lt' => 'Sezono favoritai', 'ru' => 'Сезонные блюда'],
            'item' => ['en' => 'Forest mushroom barley', 'lt' => 'Perlinės kruopos su miško grybais', 'ru' => 'Перловка с лесными грибами'],
            'description' => ['en' => 'Pearl barley, roasted mushrooms and herb butter.', 'lt' => 'Perlinės kruopos, kepti grybai ir žolelių sviestas.', 'ru' => 'Перловая крупа, жареные грибы и масло с травами.'],
            'variant' => ['en' => 'Regular', 'lt' => 'Įprasta', 'ru' => 'Обычная'],
            'price_cents' => 1290,
            'allergens' => [MenuAllergen::Gluten, MenuAllergen::Milk],
        ],
        [
            'owner_name' => 'Demo Garden Owner',
            'owner_email' => 'owner.garden@demo.test',
            'organization' => 'Vilnius Garden Rooms',
            'brand' => 'Garden Supper Club',
            'branch' => 'Garden Supper Club Užupis',
            'address' => 'Užupio g. 12',
            'city' => 'Vilnius',
            'area' => 'Orangery',
            'area_type' => AreaNodeType::Hall,
            'menu' => ['en' => 'Garden Evening Menu', 'lt' => 'Sodo vakaro meniu', 'ru' => 'Вечернее меню сада'],
            'category' => ['en' => 'Garden plates', 'lt' => 'Sodo lėkštės', 'ru' => 'Блюда сада'],
            'item' => ['en' => 'Roasted beetroot plate', 'lt' => 'Keptų burokėlių lėkštė', 'ru' => 'Запечённая свёкла'],
            'description' => ['en' => 'Roasted beetroot, goat cheese and toasted seeds.', 'lt' => 'Kepti burokėliai, ožkų sūris ir skrudintos sėklos.', 'ru' => 'Запечённая свёкла, козий сыр и поджаренные семена.'],
            'variant' => ['en' => 'Dinner plate', 'lt' => 'Vakarienės porcija', 'ru' => 'Порция для ужина'],
            'price_cents' => 1090,
            'allergens' => [MenuAllergen::Milk],
        ],
    ];

    public function __construct(
        private readonly EnsureBranchSettingsAction $ensureBranchSettings,
        private readonly SeedKitchenDepartmentsForBranchAction $seedKitchenDepartments,
    ) {}

    public function run(): void
    {
        if (strtolower((string) config('app.env')) === 'production') {
            throw new LogicException('Demo tenant portfolio cannot be seeded in production.');
        }

        DB::transaction(function (): void {
            foreach (self::PROFILES as $profile) {
                $this->seedProfile($profile);
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function organizationNames(): array
    {
        return array_column(self::PROFILES, 'organization');
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function seedProfile(array $profile): void
    {
        $owner = $this->owner($profile['owner_name'], $profile['owner_email']);
        $organization = $this->organization($owner, $profile['organization']);
        $ownerRole = Role::query()->where('code', SystemRole::Owner->value)->firstOrFail();

        $this->organizationMembership($organization, $owner, $ownerRole);
        $this->subscription($organization);

        $brand = $this->brand($organization, $profile['brand']);
        $branch = $this->branch($brand, $profile);
        $this->branchAssignment($branch, $owner, $ownerRole);
        $this->branchSettings($branch);
        $this->seedKitchenDepartments->handle($branch);

        $area = $this->area($branch, $profile['area'], $profile['area_type']);
        $this->tables($area);
        $this->menu($branch, $profile);
    }

    private function owner(string $name, string $email): User
    {
        $user = User::query()->where('email', $email)->first();
        $factory = User::factory()->demoIdentity($name, $email);

        if (! $user instanceof User) {
            return $factory->create();
        }

        $attributes = $factory->make()->getAttributes();
        unset($attributes['password']);
        $user->forceFill($attributes)->save();

        return $user->refresh();
    }

    private function organization(User $owner, string $name): Organization
    {
        $organization = Organization::withTrashed()
            ->where('owner_user_id', $owner->id)
            ->where('name', $name)
            ->first();
        $factory = Organization::factory()->for($owner, 'owner')->state(['name' => $name, 'logo_path' => null]);

        if (! $organization instanceof Organization) {
            return $factory->create();
        }

        if ($organization->trashed()) {
            $organization->restore();
        }

        $organization->forceFill($factory->make()->getAttributes())->save();

        return $organization->refresh();
    }

    private function organizationMembership(Organization $organization, User $owner, Role $role): void
    {
        $membership = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $owner->id)
            ->first();
        $factory = OrganizationUser::factory()
            ->forOrganization($organization)
            ->forUser($owner)
            ->forRole($role)
            ->active()
            ->state(['invited_by_user_id' => null]);

        if (! $membership instanceof OrganizationUser) {
            $factory->create();

            return;
        }

        $membership->forceFill($factory->make()->getAttributes())->save();
    }

    private function subscription(Organization $organization): void
    {
        $subscription = OrganizationSubscription::query()
            ->where('organization_id', $organization->id)
            ->first();
        $factory = OrganizationSubscription::factory()
            ->for($organization)
            ->active()
            ->paymentPaid();

        if (! $subscription instanceof OrganizationSubscription) {
            $factory->create();

            return;
        }

        $subscription->forceFill($factory->make()->getAttributes())->save();
    }

    private function brand(Organization $organization, string $name): Brand
    {
        $brand = Brand::withTrashed()
            ->where('organization_id', $organization->id)
            ->where('name', $name)
            ->first();
        $factory = Brand::factory()->for($organization)->state(['name' => $name, 'logo_path' => null]);

        if (! $brand instanceof Brand) {
            return $factory->create();
        }

        if ($brand->trashed()) {
            $brand->restore();
        }

        $brand->forceFill($factory->make()->getAttributes())->save();

        return $brand->refresh();
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function branch(Brand $brand, array $profile): Branch
    {
        $branch = Branch::withTrashed()
            ->where('brand_id', $brand->id)
            ->where('name', $profile['branch'])
            ->first();
        $factory = Branch::factory()->forBrand($brand)->active()->state([
            'name' => $profile['branch'],
            'public_name' => $profile['branch'],
            'public_description' => 'A deterministic demo restaurant for tenant-isolation scenarios.',
            'address' => $profile['address'],
            'city' => $profile['city'],
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'phone' => null,
            'email' => null,
            'website_url' => null,
        ]);

        if (! $branch instanceof Branch) {
            return $factory->create();
        }

        if ($branch->trashed()) {
            $branch->restore();
        }

        $branch->forceFill($factory->make()->getAttributes())->save();

        return $branch->refresh();
    }

    private function branchAssignment(Branch $branch, User $owner, Role $role): void
    {
        $assignment = BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $owner->id)
            ->first();
        $factory = BranchUser::factory()
            ->forBranch($branch)
            ->forUser($owner)
            ->forRole($role)
            ->active()
            ->state(['assigned_by_user_id' => $owner->id]);

        if (! $assignment instanceof BranchUser) {
            $factory->create();

            return;
        }

        $assignment->forceFill($factory->make()->getAttributes())->save();
    }

    private function branchSettings(Branch $branch): void
    {
        $settings = $this->ensureBranchSettings->handle($branch);
        $settings->forceFill(
            BranchSetting::factory()->demoReadyForService($branch)->make()->getAttributes(),
        )->save();
    }

    private function area(Branch $branch, string $name, AreaNodeType $type): AreaNode
    {
        $area = AreaNode::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('name', $name)
            ->first();
        $factory = AreaNode::factory()
            ->forBranch($branch)
            ->forType($type)
            ->active()
            ->state([
                'name' => $name,
                'icon' => 'squares-2x2',
                'sort_order' => 10,
                'metadata' => ['demo_fixture' => 'tenant-portfolio'],
            ]);

        if (! $area instanceof AreaNode) {
            return $factory->create();
        }

        if ($area->trashed()) {
            $area->restore();
        }

        $area->forceFill($factory->make()->getAttributes())->save();

        return $area->refresh();
    }

    private function tables(AreaNode $area): void
    {
        foreach ([1, 2] as $position) {
            $internalCode = 'DEMO-PORTFOLIO-'.$area->branch_id.'-'.$position;
            $table = ServicePoint::withTrashed()
                ->where('branch_id', $area->branch_id)
                ->where('internal_code', $internalCode)
                ->first();
            $factory = ServicePoint::factory()
                ->inAreaNode($area)
                ->free()
                ->state([
                    'type' => ServicePointType::Table,
                    'name' => 'Table '.$position,
                    'display_number' => (string) $position,
                    'internal_code' => $internalCode,
                    'capacity' => $position === 1 ? 2 : 4,
                    'icon' => 'squares-2x2',
                    'position_x' => $position * 30,
                    'position_y' => 40,
                    'metadata' => ['demo_fixture' => 'tenant-portfolio'],
                ]);

            if (! $table instanceof ServicePoint) {
                $factory->create();

                continue;
            }

            if ($table->trashed()) {
                $table->restore();
            }

            $table->forceFill($factory->make()->getAttributes())->save();
        }
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function menu(Branch $branch, array $profile): void
    {
        $menu = Menu::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('name', $profile['menu']['en'])
            ->first();
        $factory = Menu::factory()
            ->forBranch($branch)
            ->active()
            ->state(['name' => $profile['menu']['en'], 'sort_order' => 10]);

        if (! $menu instanceof Menu) {
            $menu = $factory->create();
        } else {
            if ($menu->trashed()) {
                $menu->restore();
            }

            $menu->forceFill($factory->make()->getAttributes())->save();
        }

        foreach ($profile['menu'] as $locale => $name) {
            $translation = MenuTranslation::query()
                ->where('menu_id', $menu->id)
                ->where('language_code', $locale)
                ->first();
            $translationFactory = MenuTranslation::factory()
                ->for($menu)
                ->state(['language_code' => $locale, 'name' => $name]);
            $this->saveFactoryRecord($translation, $translationFactory->make());
        }

        $category = $this->category($menu, $profile);
        $department = KitchenDepartment::query()
            ->where('branch_id', $branch->id)
            ->where('type', KitchenDepartmentType::Kitchen->value)
            ->firstOrFail();

        $this->item($menu, $category, $department, $profile);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function category(Menu $menu, array $profile): MenuCategory
    {
        $category = MenuCategory::withTrashed()
            ->where('menu_id', $menu->id)
            ->where('name', $profile['category']['en'])
            ->first();
        $factory = MenuCategory::factory()->for($menu)->active()->state([
            'name' => $profile['category']['en'],
            'description' => $profile['description']['en'],
            'icon' => 'sparkles',
            'sort_order' => 10,
        ]);

        if (! $category instanceof MenuCategory) {
            $category = $factory->create();
        } else {
            if ($category->trashed()) {
                $category->restore();
            }

            $category->forceFill($factory->make()->getAttributes())->save();
        }

        foreach ($profile['category'] as $locale => $name) {
            $translation = MenuCategoryTranslation::query()
                ->where('menu_category_id', $category->id)
                ->where('language_code', $locale)
                ->first();
            $translationFactory = MenuCategoryTranslation::factory()
                ->for($category, 'category')
                ->state([
                    'language_code' => $locale,
                    'name' => $name,
                    'description' => $profile['description'][$locale],
                ]);
            $this->saveFactoryRecord($translation, $translationFactory->make());
        }

        return $category;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function item(
        Menu $menu,
        MenuCategory $category,
        KitchenDepartment $department,
        array $profile,
    ): void {
        $item = MenuItem::withTrashed()
            ->where('menu_id', $menu->id)
            ->where('name', $profile['item']['en'])
            ->first();
        $factory = MenuItem::factory()
            ->for($menu)
            ->for($category, 'category')
            ->assignedToDepartment($department)
            ->available()
            ->withAllergens(...$profile['allergens'])
            ->state([
                'name' => $profile['item']['en'],
                'description' => $profile['description']['en'],
                'price_cents' => $profile['price_cents'],
                'weight' => null,
                'volume' => null,
                'calories' => null,
                'sort_order' => 10,
            ]);

        if (! $item instanceof MenuItem) {
            $item = $factory->create();
        } else {
            if ($item->trashed()) {
                $item->restore();
            }

            $item->forceFill($factory->make()->getAttributes())->save();
        }

        foreach ($profile['item'] as $locale => $name) {
            $translation = MenuItemTranslation::query()
                ->where('menu_item_id', $item->id)
                ->where('language_code', $locale)
                ->first();
            $translationFactory = MenuItemTranslation::factory()
                ->for($item, 'item')
                ->state([
                    'language_code' => $locale,
                    'name' => $name,
                    'description' => $profile['description'][$locale],
                ]);
            $this->saveFactoryRecord($translation, $translationFactory->make());
        }

        $this->variant($item, $profile);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function variant(MenuItem $item, array $profile): void
    {
        $variant = MenuItemVariant::query()
            ->where('menu_item_id', $item->id)
            ->where('type', MenuItemVariantType::Portion->value)
            ->where('name', $profile['variant']['en'])
            ->first();
        $factory = MenuItemVariant::factory()
            ->for($item, 'item')
            ->portion()
            ->default()
            ->available()
            ->state([
                'name' => $profile['variant']['en'],
                'price_cents' => $profile['price_cents'],
                'sort_order' => 10,
            ]);

        if (! $variant instanceof MenuItemVariant) {
            $variant = $factory->create();
        } else {
            $variant->forceFill($factory->make()->getAttributes())->save();
        }

        foreach ($profile['variant'] as $locale => $name) {
            $translation = MenuItemVariantTranslation::query()
                ->where('menu_item_variant_id', $variant->id)
                ->where('language_code', $locale)
                ->first();
            $translationFactory = MenuItemVariantTranslation::factory()
                ->for($variant, 'variant')
                ->state(['language_code' => $locale, 'name' => $name]);
            $this->saveFactoryRecord($translation, $translationFactory->make());
        }
    }

    private function saveFactoryRecord(?Model $record, Model $factoryModel): void
    {
        if (! $record instanceof Model) {
            $factoryModel->save();

            return;
        }

        $record->forceFill($factoryModel->getAttributes())->save();
    }
}
