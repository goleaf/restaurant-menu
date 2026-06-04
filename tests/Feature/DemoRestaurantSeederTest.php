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
use Database\Seeders\DemoRestaurantSeeder;

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

    foreach (demoSeederEmails() as $email => $role) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->hasSystemRole($role))->toBeTrue()
            ->and(OrganizationUser::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->exists())->toBeTrue();
    }

    $waiter = User::query()->where('email', 'demo.waiter@example.com')->firstOrFail();
    expect($waiter->hasPermission(SystemPermission::ViewOrders, $organization))->toBeTrue()
        ->and($waiter->hasPermission(SystemPermission::ConfirmOrders, $organization))->toBeTrue()
        ->and(BranchUser::query()
            ->where('branch_id', $branch->id)
            ->where('user_id', $waiter->id)
            ->exists())->toBeTrue();

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
        ->and(MenuItem::query()->where('menu_id', $menu->id)->count())->toBe(7);
});

/**
 * @return array<string, SystemRole>
 */
function demoSeederEmails(): array
{
    return [
        'demo.owner@example.com' => SystemRole::Owner,
        'demo.admin@example.com' => SystemRole::RestaurantAdmin,
        'demo.waiter@example.com' => SystemRole::Waiter,
        'demo.chef@example.com' => SystemRole::HeadChef,
        'demo.bartender@example.com' => SystemRole::Bartender,
        'demo.cashier@example.com' => SystemRole::Cashier,
    ];
}
