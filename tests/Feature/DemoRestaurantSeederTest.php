<?php

use App\Enums\QrCodeStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Support\Facades\Hash;

test('demo restaurant seeder creates a runnable demo restaurant', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();
    $brand = Brand::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Bella Pizza')
        ->firstOrFail();
    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->where('name', 'Demo Old Town')
        ->firstOrFail();

    expect($branch->settings()->exists())->toBeTrue()
        ->and(AreaNode::query()->where('branch_id', $branch->id)->count())->toBe(3)
        ->and(ServicePoint::query()->where('branch_id', $branch->id)->count())->toBe(7)
        ->and(Menu::query()->where('branch_id', $branch->id)->where('name', 'Bella Pizza Demo Menu')->count())->toBe(1);

    $servicePointIds = ServicePoint::query()
        ->where('branch_id', $branch->id)
        ->orderBy('id')
        ->pluck('id');

    expect(QrCode::query()
        ->whereIn('service_point_id', $servicePointIds)
        ->where('status', QrCodeStatus::Active->value)
        ->count())->toBe(7);

    $menu = Menu::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Bella Pizza Demo Menu')
        ->firstOrFail();

    expect(MenuCategory::query()->where('menu_id', $menu->id)->count())->toBe(3)
        ->and(MenuItem::query()->where('menu_id', $menu->id)->count())->toBe(7);

    foreach (demoRestaurantUsers() as $email => $identity) {
        $user = User::query()->where('email', $email)->firstOrFail();
        $role = $identity['role'];

        expect($user->hasSystemRole($role))->toBeTrue()
            ->and($user->name)->toBe($identity['name'])
            ->and(Hash::check(UserFactory::DEMO_PASSWORD, $user->password))->toBeTrue();

        if ($role === SystemRole::Superadmin) {
            expect($user->canAccessOrganization($organization))->toBeTrue()
                ->and($user->canAccessBranch($branch, $organization))->toBeTrue()
                ->and(OrganizationUser::query()
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $user->id)
                    ->exists())->toBeFalse()
                ->and(BranchUser::query()
                    ->where('branch_id', $branch->id)
                    ->where('user_id', $user->id)
                    ->exists())->toBeFalse();

            continue;
        }

        expect(OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists())->toBeTrue()
            ->and(BranchUser::query()
                ->where('branch_id', $branch->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists())->toBeTrue()
            ->and($user->canAccessOrganization($organization))->toBeTrue()
            ->and($user->canAccessBranch($branch, $organization))->toBeTrue();
    }

    foreach (demoRestaurantUsers() as $email => $identity) {
        if ($identity['role'] === SystemRole::Superadmin) {
            continue;
        }

        $user = User::query()->where('email', $email)->firstOrFail();

        expect(OrganizationUser::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->whereHas('role', fn ($query) => $query->where('code', $identity['role']->value))
            ->exists())->toBeTrue()
            ->and(BranchUser::query()
                ->where('branch_id', $branch->id)
                ->where('user_id', $user->id)
                ->whereHas('role', fn ($query) => $query->where('code', $identity['role']->value))
                ->exists())->toBeTrue();
    }

    $waiter = User::query()->where('email', 'waiter@demo.test')->firstOrFail();
    expect($waiter->hasPermission(SystemPermission::ViewOrders, $organization))->toBeTrue()
        ->and($waiter->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue()
        ->and($waiter->hasPermission(SystemPermission::ManageStaff, $organization))->toBeFalse()
        ->and(BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $waiter->id)
            ->exists())->toBeTrue();

    $accountant = User::query()->where('email', 'accountant@demo.test')->firstOrFail();
    expect($accountant->hasPermission(SystemPermission::ViewReports, $organization))->toBeTrue()
        ->and($accountant->hasPermission(SystemPermission::ViewPayments, $organization))->toBeTrue()
        ->and($accountant->hasPermission(SystemPermission::ManageMenu, $organization))->toBeFalse();

    $marketer = User::query()->where('email', 'marketer@demo.test')->firstOrFail();
    expect($marketer->hasPermission(SystemPermission::ManageMenu, $organization))->toBeTrue()
        ->and($marketer->hasPermission(SystemPermission::ManagePayments, $organization))->toBeFalse();

    $qrCode = QrCode::query()
        ->whereIn('service_point_id', $servicePointIds)
        ->where('status', QrCodeStatus::Active->value)
        ->oldest('id')
        ->firstOrFail();

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token]))
        ->assertSuccessful()
        ->assertSee('Bella Pizza')
        ->assertSee('Demo Old Town');
});

test('demo restaurant seeder is idempotent', function () {
    $this->seed(DemoRestaurantSeeder::class);
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()
        ->where('name', 'Demo Food Group')
        ->firstOrFail();
    $brand = Brand::query()
        ->where('organization_id', $organization->id)
        ->where('name', 'Bella Pizza')
        ->firstOrFail();
    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->where('name', 'Demo Old Town')
        ->firstOrFail();
    $menu = Menu::query()
        ->where('branch_id', $branch->id)
        ->where('name', 'Bella Pizza Demo Menu')
        ->firstOrFail();
    $servicePointIds = ServicePoint::query()
        ->where('branch_id', $branch->id)
        ->orderBy('id')
        ->pluck('id');

    expect(Organization::query()->where('name', 'Demo Food Group')->count())->toBe(1)
        ->and(Brand::query()->where('organization_id', $organization->id)->where('name', 'Bella Pizza')->count())->toBe(1)
        ->and(Branch::query()->where('brand_id', $brand->id)->where('name', 'Demo Old Town')->count())->toBe(1)
        ->and(AreaNode::query()->where('branch_id', $branch->id)->count())->toBe(3)
        ->and(ServicePoint::query()->where('branch_id', $branch->id)->count())->toBe(7)
        ->and(QrCode::query()
            ->whereIn('service_point_id', $servicePointIds)
            ->where('status', QrCodeStatus::Active->value)
            ->count())->toBe(7)
        ->and(Menu::query()->where('branch_id', $branch->id)->where('name', 'Bella Pizza Demo Menu')->count())->toBe(1)
        ->and(MenuCategory::query()->where('menu_id', $menu->id)->count())->toBe(3)
        ->and(MenuItem::query()->where('menu_id', $menu->id)->count())->toBe(7)
        ->and(User::query()->whereIn('email', array_keys(demoRestaurantUsers()))->count())->toBe(count(demoRestaurantUsers()))
        ->and(OrganizationUser::query()->where('organization_id', $organization->id)->count())->toBe(count(demoRestaurantUsers()) - 1)
        ->and(BranchUser::query()->where('branch_id', $branch->id)->count())->toBe(count(demoRestaurantUsers()) - 1);
});

/**
 * @return array<string, array{name: string, role: SystemRole}>
 */
function demoRestaurantUsers(): array
{
    return [
        'superadmin@demo.test' => ['name' => 'Demo Superadmin', 'role' => SystemRole::Superadmin],
        'owner@demo.test' => ['name' => 'Demo Owner', 'role' => SystemRole::Owner],
        'director@demo.test' => ['name' => 'Demo Director', 'role' => SystemRole::Director],
        'admin@demo.test' => ['name' => 'Demo Restaurant Admin', 'role' => SystemRole::RestaurantAdmin],
        'manager@demo.test' => ['name' => 'Demo Shift Manager', 'role' => SystemRole::ShiftManager],
        'waiter@demo.test' => ['name' => 'Demo Waiter', 'role' => SystemRole::Waiter],
        'chef@demo.test' => ['name' => 'Demo Head Chef', 'role' => SystemRole::HeadChef],
        'cook@demo.test' => ['name' => 'Demo Cook', 'role' => SystemRole::Cook],
        'bartender@demo.test' => ['name' => 'Demo Bartender', 'role' => SystemRole::Bartender],
        'cashier@demo.test' => ['name' => 'Demo Cashier', 'role' => SystemRole::Cashier],
        'accountant@demo.test' => ['name' => 'Demo Accountant', 'role' => SystemRole::Accountant],
        'marketer@demo.test' => ['name' => 'Demo Marketer', 'role' => SystemRole::Marketer],
    ];
}
