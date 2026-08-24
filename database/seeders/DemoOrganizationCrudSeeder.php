<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Modifiers\AssignModifierGroupToMenuItemAction;
use App\Enums\AreaNodeType;
use App\Enums\KitchenDepartmentType;
use App\Enums\ServicePointType;
use App\Enums\SupportedLocale;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierGroupTranslation;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTranslation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

final class DemoOrganizationCrudSeeder extends Seeder
{
    public const INACTIVE_AREA_NAME = 'CRUD archived area';

    public const INACTIVE_SERVICE_POINT_CODE = 'DEMO-CRUD-INACTIVE-SP';

    public const INACTIVE_DEPARTMENT_NAME = 'CRUD archived department';

    public const INACTIVE_ITEM_NAME = 'CRUD unavailable dish';

    public const INACTIVE_VARIANT_NAME = 'CRUD unavailable portion';

    private const INACTIVE_BRANCH_NAME = 'Coffee Bar Small Hall';

    private const TEMPORARILY_CLOSED_BRANCH_NAME = 'Bella Pizza Terrace';

    private const PNG_PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /**
     * Digests produced by the retired deterministic demo invitation fixtures.
     *
     * @var array<string, array{token: string, code: string}>
     */
    private const array LEGACY_INVITATION_HASHES = [
        'pending' => ['token' => '4ab20bc0f974fd08f420d9ded8d56037be89140c077edfb0e205de00d37b8212', 'code' => 'b5a094034e93afe2f4e4954538700fcfddcbab92473c6007f82e9425aa6b427d'],
        'accepted' => ['token' => '1cb2c01b0fb115c4afbfc9f2a0107963403855bf7689a3deec8c65993af24c7d', 'code' => '889311e1e1c14256e9719ce0677e9c549d8f00f3247de21ca388120d828d6030'],
        'expired' => ['token' => 'cf6e536db110fab2af776b77b61794ef52b6d079772ce237995e64d8485d8a32', 'code' => 'f0532760378f1262f0bfd1e957349494a5e5250e4257276dd41beaa8d01c8b31'],
        'cancelled' => ['token' => 'aca12dccb81f3b64ec33d95d1053a8719c6f570f9f5462c79ab1c54fb52ffc6f', 'code' => '9975448eb682333ea4deb656e96b3a50bbe246125d9085346674b1508e156cec'],
        'rejected' => ['token' => 'a9830bac017be106eb1f0504181b3f3f47cba3707e04767e3351c4f7750a98e3', 'code' => 'afb9d53fdd7305bdf0899c0c076b83b896866015c397718c2fdcb724e24031f1'],
    ];

    public function __construct(
        private readonly AssignModifierGroupToMenuItemAction $assignModifierGroup,
    ) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (strtolower((string) config('app.env')) === 'production') {
            throw new LogicException('Demo organization CRUD data cannot be seeded in production.');
        }

        $organization = Organization::query()
            ->select(['id', 'owner_user_id', 'name', 'logo_path'])
            ->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)
            ->firstOrFail();

        /** @var list<string> $mediaPaths */
        $mediaPaths = DB::transaction(function () use ($organization): array {
            $this->seedBranchAdministration($organization);
            $this->seedStaffLifecycle($organization);
            $this->seedMenuAdministration($organization);

            return $this->seedOwnedMedia($organization);
        });

        $image = base64_decode(self::PNG_PIXEL, true);

        if (! is_string($image)) {
            throw new LogicException('The deterministic demo image is invalid.');
        }

        foreach ($mediaPaths as $path) {
            Storage::disk('public')->put($path, $image);
        }
    }

    private function seedBranchAdministration(Organization $organization): void
    {
        $branches = Branch::query()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'name',
                'is_active',
                'is_temporarily_closed',
                'temporary_closed_reason',
                'temporary_closed_until',
            ])
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            foreach (range(1, 7) as $dayOfWeek) {
                $this->seedOpeningHour(
                    $branch,
                    $dayOfWeek,
                    10,
                    $dayOfWeek === 7,
                    $dayOfWeek === 6 ? '12:00' : '11:00',
                    $dayOfWeek === 6 ? '23:00' : '22:00',
                );
            }

            if ($branch->name === 'Bella Pizza Old Town') {
                $this->seedOpeningHour($branch, 2, 10, false, '11:00', '15:00');
                $this->seedOpeningHour($branch, 2, 20, false, '17:00', '22:00');
            }
        }

        $temporarilyClosedBranch = $branches->firstWhere('name', self::TEMPORARILY_CLOSED_BRANCH_NAME);

        if ($temporarilyClosedBranch instanceof Branch) {
            $attributes = Branch::factory()
                ->state(fn (): array => [
                    'is_temporarily_closed' => true,
                    'temporary_closed_reason' => 'Planned CRUD demonstration closure',
                    'temporary_closed_until' => CarbonImmutable::parse('2035-01-15 18:00:00', 'UTC'),
                ])
                ->make([
                    'organization_id' => $temporarilyClosedBranch->organization_id,
                    'brand_id' => $temporarilyClosedBranch->brand_id,
                ])
                ->only(['is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until']);

            $temporarilyClosedBranch->forceFill($attributes)->save();
        }

        $inactiveBranch = $branches->firstWhere('name', self::INACTIVE_BRANCH_NAME);

        if (! $inactiveBranch instanceof Branch) {
            return;
        }

        $inactiveBranchAttributes = Branch::factory()
            ->inactive()
            ->make([
                'organization_id' => $inactiveBranch->organization_id,
                'brand_id' => $inactiveBranch->brand_id,
            ])
            ->only(['is_active']);

        $inactiveBranch->forceFill($inactiveBranchAttributes)->save();

        $area = $this->seedInactiveArea($inactiveBranch);
        $this->seedInactiveServicePoint($inactiveBranch, $area);
        $this->seedInactiveDepartment($inactiveBranch);
    }

    private function seedOpeningHour(
        Branch $branch,
        int $dayOfWeek,
        int $sortOrder,
        bool $closed,
        string $opensAt,
        string $closesAt,
    ): void {
        $factory = BranchOpeningHour::factory()
            ->for($branch)
            ->state(fn (): array => [
                'day_of_week' => $dayOfWeek,
                'sort_order' => $sortOrder,
            ]);
        $factory = $closed ? $factory->closed() : $factory->open($opensAt, $closesAt);
        $openingHour = BranchOpeningHour::query()
            ->select(['id', 'branch_id', 'day_of_week', 'is_closed', 'opens_at', 'closes_at', 'sort_order'])
            ->where('branch_id', $branch->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('sort_order', $sortOrder)
            ->first();

        if (! $openingHour instanceof BranchOpeningHour) {
            $factory->create();

            return;
        }

        $openingHour->forceFill($factory->make()->getAttributes())->save();
    }

    private function seedInactiveArea(Branch $branch): AreaNode
    {
        $area = AreaNode::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('name', self::INACTIVE_AREA_NAME)
            ->first();
        $factory = AreaNode::factory()
            ->forBranch($branch)
            ->forType(AreaNodeType::Custom)
            ->inactive()
            ->state(fn (): array => [
                'name' => self::INACTIVE_AREA_NAME,
                'sort_order' => 900,
                'metadata' => ['demo_fixture' => 'inactive-area'],
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

    private function seedInactiveServicePoint(Branch $branch, AreaNode $area): ServicePoint
    {
        $servicePoint = ServicePoint::withTrashed()
            ->where('branch_id', $branch->id)
            ->where('internal_code', self::INACTIVE_SERVICE_POINT_CODE)
            ->first();
        $factory = ServicePoint::factory()
            ->inAreaNode($area)
            ->blocked()
            ->state(fn (): array => [
                'type' => ServicePointType::Table,
                'name' => 'CRUD unavailable table',
                'display_number' => 'X',
                'internal_code' => self::INACTIVE_SERVICE_POINT_CODE,
                'capacity' => 4,
                'icon' => 'archive-box',
                'position_x' => 90,
                'position_y' => 90,
                'metadata' => ['demo_fixture' => 'inactive-service-point'],
            ]);

        if (! $servicePoint instanceof ServicePoint) {
            return $factory->create();
        }

        if ($servicePoint->trashed()) {
            $servicePoint->restore();
        }

        $servicePoint->forceFill($factory->make()->getAttributes())->save();

        return $servicePoint->refresh();
    }

    private function seedInactiveDepartment(Branch $branch): KitchenDepartment
    {
        $department = KitchenDepartment::query()
            ->where('branch_id', $branch->id)
            ->where('name', self::INACTIVE_DEPARTMENT_NAME)
            ->first();
        $factory = KitchenDepartment::factory()
            ->for($branch)
            ->forType(KitchenDepartmentType::Custom)
            ->inactive()
            ->state(fn (): array => [
                'name' => self::INACTIVE_DEPARTMENT_NAME,
                'sort_order' => 900,
            ]);

        if (! $department instanceof KitchenDepartment) {
            return $factory->create();
        }

        $department->forceFill($factory->make()->getAttributes())->save();

        return $department->refresh();
    }

    private function seedStaffLifecycle(Organization $organization): void
    {
        $owner = User::query()
            ->select(['id', 'name', 'email'])
            ->findOrFail($organization->owner_user_id);
        $waiterRole = Role::query()
            ->select(['id', 'code'])
            ->where('code', SystemRole::Waiter->value)
            ->firstOrFail();
        $branches = Branch::query()
            ->select(['id', 'organization_id', 'name'])
            ->where('organization_id', $organization->id)
            ->whereIn('name', ['Bella Pizza Old Town', 'Bella Pizza Terrace', self::INACTIVE_BRANCH_NAME])
            ->orderBy('id')
            ->get()
            ->keyBy('name');

        $suspendedUser = $this->seedLifecycleUser('CRUD Suspended Waiter', 'suspended.staff@demo.test');
        $removedUser = $this->seedLifecycleUser('CRUD Removed Waiter', 'removed.staff@demo.test');
        $permissionUser = $this->seedLifecycleUser('CRUD Permission Analyst', 'permission.staff@demo.test');

        $this->seedOrganizationMembership($organization, $suspendedUser, $waiterRole, $owner, 'suspended');
        $this->seedOrganizationMembership($organization, $removedUser, $waiterRole, $owner, 'removed');
        $this->seedOrganizationMembership($organization, $permissionUser, $waiterRole, $owner, 'active');

        $terraceBranch = $branches->get('Bella Pizza Terrace');
        $inactiveBranch = $branches->get(self::INACTIVE_BRANCH_NAME);

        if ($terraceBranch instanceof Branch) {
            $this->seedBranchMembership($terraceBranch, $suspendedUser, $waiterRole, $owner, 'suspended');
        }

        if ($inactiveBranch instanceof Branch) {
            $this->seedBranchMembership($inactiveBranch, $removedUser, $waiterRole, $owner, 'removed');
        }

        $primaryBranch = $branches->get('Bella Pizza Old Town');

        if ($primaryBranch instanceof Branch) {
            $this->seedBranchMembership($primaryBranch, $permissionUser, $waiterRole, $owner, 'active');
        }

        $waiter = User::query()
            ->select(['id', 'name', 'email'])
            ->where('email', 'waiter@demo.test')
            ->firstOrFail();

        foreach (['Bella Pizza Old Town', 'Bella Pizza Terrace'] as $branchName) {
            $branch = $branches->get($branchName);

            if (! $branch instanceof Branch) {
                continue;
            }

            $area = AreaNode::query()
                ->select(['id', 'branch_id'])
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->firstOrFail();

            $this->seedWaiterAssignment($organization, $branch, $area, $waiter, $owner);
        }

        $this->seedInvitations($organization, $owner, $waiterRole, $branches);
        $this->seedPermissionOverrides();
    }

    private function seedLifecycleUser(string $name, string $email): User
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'locale', 'email_verified_at'])
            ->where('email', $email)
            ->first();
        $factory = User::factory()->demoIdentity($name, $email);

        if (! $user instanceof User) {
            return $factory->create();
        }

        $attributes = $factory->make()->getAttributes();

        unset($attributes['password']);

        $user->forceFill($attributes)->save();

        return $user->refresh();
    }

    private function seedOrganizationMembership(
        Organization $organization,
        User $user,
        Role $role,
        User $owner,
        string $state,
    ): void {
        $membership = OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->first();
        $factory = OrganizationUser::factory()
            ->forOrganization($organization)
            ->forUser($user)
            ->forRole($role)
            ->{$state}()
            ->state(fn (): array => ['invited_by_user_id' => $owner->id]);

        if (! $membership instanceof OrganizationUser) {
            $factory->create();

            return;
        }

        $membership->forceFill($factory->make()->getAttributes())->save();
    }

    private function seedBranchMembership(
        Branch $branch,
        User $user,
        Role $role,
        User $owner,
        string $state,
    ): void {
        $assignment = BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $user->id)
            ->first();
        $factory = BranchUser::factory()
            ->forBranch($branch)
            ->forUser($user)
            ->forRole($role)
            ->{$state}()
            ->state(fn (): array => ['assigned_by_user_id' => $owner->id]);

        if (! $assignment instanceof BranchUser) {
            $factory->create();

            return;
        }

        $assignment->forceFill($factory->make()->getAttributes())->save();
    }

    private function seedWaiterAssignment(
        Organization $organization,
        Branch $branch,
        AreaNode $area,
        User $waiter,
        User $owner,
    ): void {
        $assignment = AreaNodeWaiter::query()
            ->where('area_node_id', $area->id)
            ->where('user_id', $waiter->id)
            ->first();
        $factory = AreaNodeWaiter::factory()->state(fn (): array => [
            'organization_id' => $organization->id,
            'branch_id' => $branch->id,
            'area_node_id' => $area->id,
            'user_id' => $waiter->id,
            'assigned_by_user_id' => $owner->id,
            'assigned_at' => CarbonImmutable::parse('2026-08-23 09:00:00', 'UTC'),
        ]);

        if (! $assignment instanceof AreaNodeWaiter) {
            $factory->create();

            return;
        }

        $assignment->forceFill($factory->make()->getAttributes())->save();
    }

    /**
     * @param  Collection<string, Branch>  $branches
     */
    private function seedInvitations(
        Organization $organization,
        User $owner,
        Role $role,
        Collection $branches,
    ): void {
        $profiles = [
            ['email' => 'pending.invitation@demo.test', 'state' => 'pending', 'branch' => 'Bella Pizza Old Town'],
            ['email' => 'permission.staff@demo.test', 'state' => 'accepted', 'branch' => 'Bella Pizza Old Town'],
            ['email' => 'expired.invitation@demo.test', 'state' => 'expired', 'branch' => 'Bella Pizza Terrace'],
            ['email' => 'revoked.invitation@demo.test', 'state' => 'cancelled', 'branch' => self::INACTIVE_BRANCH_NAME],
            ['email' => 'rejected.invitation@demo.test', 'state' => 'rejected', 'branch' => 'Bella Pizza Terrace'],
        ];
        $acceptedUser = User::query()
            ->select(['id', 'email'])
            ->where('email', 'permission.staff@demo.test')
            ->firstOrFail();

        foreach ($profiles as $index => $profile) {
            $branch = $branches->get($profile['branch']);

            if (! $branch instanceof Branch) {
                continue;
            }

            $invitation = Invitation::query()
                ->where('organization_id', $organization->id)
                ->where('email', $profile['email'])
                ->first();
            $state = $profile['state'];

            if (! $invitation instanceof Invitation && $state === 'cancelled') {
                $invitation = Invitation::query()
                    ->where('organization_id', $organization->id)
                    ->where('email', 'cancelled.invitation@demo.test')
                    ->first();
            }

            $factory = Invitation::factory()
                ->forOrganization($organization)
                ->forRole($role);
            $factory = $state === 'accepted'
                ? $factory->acceptedBy($acceptedUser)
                : $factory->{$state}();
            $factory = $factory->state(fn (): array => [
                'brand_id' => $branch->brand_id,
                'branch_id' => $branch->id,
                'email' => $profile['email'],
                'phone' => null,
                'expires_at' => $state === 'expired'
                    ? CarbonImmutable::parse('2026-08-22 09:00:00', 'UTC')
                    : CarbonImmutable::parse('2035-01-15 18:00:00', 'UTC'),
                'invited_by_user_id' => $owner->id,
                'created_at' => CarbonImmutable::parse('2026-08-23 09:00:00', 'UTC')->addMinutes($index),
                'updated_at' => CarbonImmutable::parse('2026-08-23 09:00:00', 'UTC')->addMinutes($index),
            ]);

            if (! $invitation instanceof Invitation) {
                $factory->create();

                continue;
            }

            $attributes = $factory->make()->getAttributes();

            if (! $this->usesLegacyInvitationCredential($invitation, $state)) {
                unset($attributes['invite_token_hash'], $attributes['invite_code_hash']);
            }

            $invitation->forceFill($attributes)->save();
        }
    }

    private function usesLegacyInvitationCredential(Invitation $invitation, string $state): bool
    {
        $legacyHashes = self::LEGACY_INVITATION_HASHES[$state];

        return hash_equals($legacyHashes['token'], (string) $invitation->getRawOriginal('invite_token_hash'))
            || hash_equals($legacyHashes['code'], (string) $invitation->getRawOriginal('invite_code_hash'));
    }

    private function seedPermissionOverrides(): void
    {
        $profiles = [
            ['email' => 'permission.staff@demo.test', 'permission' => SystemPermission::ChangeAvailability, 'state' => 'allowed'],
            ['email' => 'permission.staff@demo.test', 'permission' => SystemPermission::ViewReports, 'state' => 'denied'],
        ];

        foreach ($profiles as $profile) {
            $user = User::query()
                ->select(['id'])
                ->where('email', $profile['email'])
                ->firstOrFail();
            $permission = Permission::query()
                ->select(['id', 'code'])
                ->where('code', $profile['permission']->value)
                ->firstOrFail();
            $override = PermissionUserOverride::query()
                ->where('user_id', $user->id)
                ->where('permission_id', $permission->id)
                ->first();
            $state = $profile['state'];
            $factory = PermissionUserOverride::factory()
                ->forUser($user)
                ->forPermission($permission)
                ->{$state}();

            if (! $override instanceof PermissionUserOverride) {
                $factory->create();

                continue;
            }

            $override->forceFill($factory->make()->getAttributes())->save();
        }
    }

    private function seedMenuAdministration(Organization $organization): void
    {
        $branches = Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name'])
            ->where('organization_id', $organization->id)
            ->with(['menus' => fn ($query) => $query
                ->select(['id', 'branch_id', 'name'])
                ->orderBy('id')])
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            $menu = $branch->menus->first();

            if (! $menu instanceof Menu) {
                continue;
            }

            $this->seedMenuSchedule($menu, 'weekday', 2);
            $this->seedMenuSchedule($menu, 'weekend', 6);
            $this->seedModifiers($branch, $menu);
        }

        $inactiveBranch = $branches->firstWhere('name', self::INACTIVE_BRANCH_NAME);

        if ($inactiveBranch instanceof Branch) {
            $this->seedInactiveDish($inactiveBranch);
        }
    }

    private function seedMenuSchedule(Menu $menu, string $state, int $dayOfWeek): void
    {
        $factory = MenuAvailabilitySchedule::factory()
            ->for($menu)
            ->{$state}($dayOfWeek);
        $schedule = MenuAvailabilitySchedule::query()
            ->where('menu_id', $menu->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $schedule instanceof MenuAvailabilitySchedule) {
            $factory->create();

            return;
        }

        $schedule->forceFill($factory->make()->getAttributes())->save();
    }

    private function seedModifiers(Branch $branch, Menu $menu): void
    {
        $required = $this->seedModifierGroup($branch, 'CRUD size choice', 'required', 10);
        $optional = $this->seedModifierGroup($branch, 'CRUD optional extras', 'optional', 20);

        $this->seedModifierOption($required, 'Standard', 'free', 0, true, 10);
        $this->seedModifierOption($required, 'Large', 'surcharge', 300, true, 20);
        $this->seedModifierOption($required, 'Small', 'discount', 100, true, 30);
        $this->seedModifierOption($optional, 'Extra cheese', 'surcharge', 150, true, 10);
        $this->seedModifierOption($optional, 'House sauce', 'surcharge', 75, true, 20);
        $this->seedModifierOption($optional, 'Seasonal topping', 'surcharge', 200, false, 30);

        $item = MenuItem::query()
            ->select(['id', 'menu_id', 'name'])
            ->where('menu_id', $menu->id)
            ->where('name', '!=', self::INACTIVE_ITEM_NAME)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->firstOrFail();

        $this->assignModifierGroup->handle($branch, $item, $required);
    }

    private function seedModifierGroup(Branch $branch, string $name, string $state, int $sortOrder): ModifierGroup
    {
        $group = ModifierGroup::query()
            ->where('branch_id', $branch->id)
            ->where('name', $name)
            ->first();
        $factory = ModifierGroup::factory()
            ->for($branch)
            ->{$state}()
            ->state(fn (): array => [
                'name' => $name,
                'sort_order' => $sortOrder,
            ]);

        if (! $group instanceof ModifierGroup) {
            $group = $factory->create();
        } else {
            $group->forceFill($factory->make()->getAttributes())->save();
        }

        foreach (DemoMenuTranslations::modifierGroup($name) as $languageCode => $translatedName) {
            ModifierGroupTranslation::query()->updateOrCreate(
                ['modifier_group_id' => $group->id, 'language_code' => $languageCode],
                ['name' => $translatedName],
            );
        }

        return $group->refresh();
    }

    private function seedModifierOption(
        ModifierGroup $group,
        string $name,
        string $state,
        int $cents,
        bool $available,
        int $sortOrder,
    ): void {
        $option = ModifierOption::query()
            ->where('modifier_group_id', $group->id)
            ->where('name', $name)
            ->first();
        $factory = ModifierOption::factory()
            ->for($group, 'group')
            ->{$state}($cents)
            ->state(fn (): array => [
                'name' => $name,
                'is_available' => $available,
                'sort_order' => $sortOrder,
            ]);

        if (! $option instanceof ModifierOption) {
            $option = $factory->create();
        } else {
            $option->forceFill($factory->make()->getAttributes())->save();
        }

        foreach (DemoMenuTranslations::modifierOption($name) as $languageCode => $translatedName) {
            ModifierOptionTranslation::query()->updateOrCreate(
                ['modifier_option_id' => $option->id, 'language_code' => $languageCode],
                ['name' => $translatedName],
            );
        }
    }

    private function seedInactiveDish(Branch $branch): void
    {
        $menu = Menu::query()
            ->select(['id', 'branch_id'])
            ->where('branch_id', $branch->id)
            ->orderBy('id')
            ->firstOrFail();
        $category = MenuCategory::query()
            ->select(['id', 'menu_id'])
            ->where('menu_id', $menu->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->firstOrFail();
        $department = KitchenDepartment::query()
            ->select(['id', 'branch_id'])
            ->where('branch_id', $branch->id)
            ->where('name', self::INACTIVE_DEPARTMENT_NAME)
            ->firstOrFail();
        $item = MenuItem::withTrashed()
            ->where('menu_id', $menu->id)
            ->where('name', self::INACTIVE_ITEM_NAME)
            ->first();
        $factory = MenuItem::factory()
            ->for($menu)
            ->for($category, 'category')
            ->unavailable()
            ->state(fn (): array => [
                'kitchen_department_id' => $department->id,
                'name' => self::INACTIVE_ITEM_NAME,
                'description' => 'Deterministic unavailable item for administration lifecycle checks.',
                'price_cents' => 990,
                'allergens' => [],
                'dietary_labels' => [],
                'image' => null,
                'weight' => '100.00',
                'volume' => null,
                'calories' => 100,
                'sort_order' => 900,
            ]);

        if (! $item instanceof MenuItem) {
            $item = $factory->create();
        } else {
            if ($item->trashed()) {
                $item->restore();
            }

            $item->forceFill($factory->make()->getAttributes())->save();
        }

        $this->seedInactiveItemTranslations($item);

        $variant = MenuItemVariant::query()
            ->where('menu_item_id', $item->id)
            ->where('name', self::INACTIVE_VARIANT_NAME)
            ->first();
        $variantFactory = MenuItemVariant::factory()
            ->for($item, 'item')
            ->portion()
            ->unavailable()
            ->state(fn (): array => [
                'name' => self::INACTIVE_VARIANT_NAME,
                'price_cents' => 1290,
                'weight' => '150.00',
                'is_default' => false,
                'sort_order' => 900,
            ]);

        if (! $variant instanceof MenuItemVariant) {
            $variant = $variantFactory->create();
        } else {
            $variant->forceFill($variantFactory->make()->getAttributes())->save();
        }

        $this->seedInactiveVariantTranslations($variant);
    }

    private function seedInactiveItemTranslations(MenuItem $item): void
    {
        $translations = [
            SupportedLocale::English->value => ['CRUD unavailable dish', 'Unavailable demonstration item.'],
            SupportedLocale::Lithuanian->value => ['CRUD nepasiekiamas patiekalas', 'Nepasiekiama demonstracinė pozicija.'],
            SupportedLocale::Russian->value => ['Недоступное CRUD-блюдо', 'Недоступная демонстрационная позиция.'],
        ];

        foreach ($translations as $languageCode => [$name, $description]) {
            $translation = MenuItemTranslation::query()
                ->where('menu_item_id', $item->id)
                ->where('language_code', $languageCode)
                ->first();
            $factory = MenuItemTranslation::factory()
                ->for($item, 'item')
                ->state(fn (): array => [
                    'language_code' => $languageCode,
                    'name' => $name,
                    'description' => $description,
                ]);

            if (! $translation instanceof MenuItemTranslation) {
                $factory->create();

                continue;
            }

            $translation->forceFill($factory->make()->getAttributes())->save();
        }
    }

    private function seedInactiveVariantTranslations(MenuItemVariant $variant): void
    {
        $translations = [
            SupportedLocale::English->value => 'CRUD unavailable portion',
            SupportedLocale::Lithuanian->value => 'CRUD nepasiekiama porcija',
            SupportedLocale::Russian->value => 'Недоступная CRUD-порция',
        ];

        foreach ($translations as $languageCode => $name) {
            $translation = MenuItemVariantTranslation::query()
                ->where('menu_item_variant_id', $variant->id)
                ->where('language_code', $languageCode)
                ->first();
            $factory = MenuItemVariantTranslation::factory()
                ->for($variant, 'variant')
                ->state(fn (): array => [
                    'language_code' => $languageCode,
                    'name' => $name,
                ]);

            if (! $translation instanceof MenuItemVariantTranslation) {
                $factory->create();

                continue;
            }

            $translation->forceFill($factory->make()->getAttributes())->save();
        }
    }

    /**
     * @return list<string>
     */
    private function seedOwnedMedia(Organization $organization): array
    {
        $paths = [];
        $organizationPath = 'demo/media/organizations/demo-food-group-logo.png';
        $organizationAttributes = Organization::factory()
            ->state(fn (): array => ['logo_path' => $organizationPath])
            ->make([
                'owner_user_id' => $organization->owner_user_id,
                'name' => $organization->name,
            ])
            ->only(['logo_path']);
        $organization->forceFill($organizationAttributes)->save();
        $paths[] = $organizationPath;

        $brands = Brand::query()
            ->select(['id', 'organization_id', 'name', 'logo_path'])
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        foreach ($brands as $brand) {
            $path = 'demo/media/brands/'.Str::slug($brand->name).'-logo.png';
            $attributes = Brand::factory()
                ->state(fn (): array => ['logo_path' => $path])
                ->make(['organization_id' => $organization->id, 'name' => $brand->name])
                ->only(['logo_path']);
            $brand->forceFill($attributes)->save();
            $paths[] = $path;
        }

        $branches = Branch::query()
            ->select(['id', 'organization_id', 'brand_id', 'name', 'logo_path', 'cover_image_path'])
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        foreach ($branches as $branch) {
            $slug = Str::slug($branch->name);
            $logoPath = 'demo/media/branches/'.$slug.'-logo.png';
            $coverPath = 'demo/media/branches/'.$slug.'-cover.png';
            $attributes = Branch::factory()
                ->state(fn (): array => [
                    'logo_path' => $logoPath,
                    'cover_image_path' => $coverPath,
                ])
                ->make([
                    'organization_id' => $branch->organization_id,
                    'brand_id' => $branch->brand_id,
                    'name' => $branch->name,
                ])
                ->only(['logo_path', 'cover_image_path']);
            $branch->forceFill($attributes)->save();
            array_push($paths, $logoPath, $coverPath);

            $item = MenuItem::query()
                ->select(['id', 'menu_id', 'category_id', 'name', 'image'])
                ->whereHas('menu', fn ($query) => $query->where('branch_id', $branch->id))
                ->where('name', '!=', self::INACTIVE_ITEM_NAME)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if (! $item instanceof MenuItem) {
                continue;
            }

            $itemPath = 'demo/media/menu-items/'.$slug.'-'.Str::slug($item->name).'.png';
            $itemAttributes = MenuItem::factory()
                ->state(fn (): array => ['image' => $itemPath])
                ->make([
                    'menu_id' => $item->menu_id,
                    'category_id' => $item->category_id,
                    'name' => $item->name,
                ])
                ->only(['image']);
            $item->forceFill($itemAttributes)->save();
            $paths[] = $itemPath;
            array_push($paths, ...$this->seedMenuItemGalleryImages($item, $slug));
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private function seedMenuItemGalleryImages(MenuItem $item, string $branchSlug): array
    {
        $paths = [];

        foreach (range(0, 1) as $sortOrder) {
            $path = sprintf(
                'demo/media/menu-items/%s-%s-gallery-%d.png',
                $branchSlug,
                Str::slug($item->name),
                $sortOrder + 1,
            );
            $image = MenuItemImage::query()
                ->select(['id', 'menu_item_id', 'path', 'sort_order'])
                ->where('path', $path)
                ->first();

            if ($image instanceof MenuItemImage) {
                if ($image->menu_item_id !== $item->id || $image->sort_order !== $sortOrder) {
                    throw new LogicException('A deterministic demo gallery path is already assigned to another image slot.');
                }

                $paths[] = $path;

                continue;
            }

            if (MenuItemImage::query()
                ->where('menu_item_id', $item->id)
                ->where('sort_order', $sortOrder)
                ->exists()) {
                throw new LogicException('A deterministic demo gallery slot is already occupied by another image.');
            }

            MenuItemImage::factory()
                ->for($item, 'item')
                ->create([
                    'path' => $path,
                    'sort_order' => $sortOrder,
                ]);
            $paths[] = $path;
        }

        return $paths;
    }
}
