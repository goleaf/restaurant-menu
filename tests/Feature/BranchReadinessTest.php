<?php

use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\DisableQrCodeAction;
use App\Enums\MenuStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\OrganizationUser;
use App\Models\QrCode;
use App\Models\RestaurantOnboarding;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Restaurant\BranchReadinessService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Readiness restaurant']);
    $brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($brand)->create();
    $this->service = app(BranchReadinessService::class);
});

test('new branch reports current blockers and exact authorized repair links', function (): void {
    $payload = $this->service->handle($this->owner, $this->branch);
    $items = collect($payload['items'])->keyBy('key');
    expect($payload['status'])->toBe('blocked')->and($payload['ordering']['kind'])->toBe('setup_problem')
        ->and($items['tables']['status'])->toBe('blocker')->and($items['menu']['status'])->toBe('blocker')
        ->and($items['tables']['url'])->toBe(route('organizations.brands.branches.service-points.index', [$this->organization->id, $this->branch->brand_id, $this->branch->id]));
});

test('current readiness changes when completed onboarding entities are removed and repaired', function (): void {
    [$point, $qr, $menu, $item] = prepareReadinessBranch($this->branch);
    RestaurantOnboarding::factory()->for($this->owner)->create(['organization_id' => $this->organization->id, 'brand_id' => $this->branch->brand_id, 'branch_id' => $this->branch->id, 'completed_at' => now()->subDay()]);
    $payload = $this->service->handle($this->owner, $this->branch);
    expect($payload['onboarding_completed'])->toBeTrue()->and($payload['ordering']['kind'])->toBe('open');
    $item->delete();
    $payload = $this->service->handle($this->owner, $this->branch);
    expect($payload['onboarding_completed'])->toBeTrue()->and(collect($payload['items'])->firstWhere('key', 'menu')['status'])->toBe('blocker');
    $item->restore();
    expect($this->service->handle($this->owner, $this->branch)['ordering']['kind'])->toBe('open');
    app(DisableQrCodeAction::class)->handle($qr, $this->owner);
    expect(collect($this->service->handle($this->owner, $this->branch)['items'])->firstWhere('key', 'qr')['status'])->toBe('blocker');
});

test('temporarily hidden dishes and inactive departments are reflected without file reads', function (): void {
    [, , , $item] = prepareReadinessBranch($this->branch);
    $item->update(['hidden_until' => now()->addHour()]);
    expect(collect($this->service->handle($this->owner, $this->branch)['items'])->firstWhere('key', 'menu')['status'])->toBe('blocker');
    $item->update(['hidden_until' => now()->subMinute()]);
    $item->kitchenDepartment()->update(['is_active' => false]);
    expect(collect($this->service->handle($this->owner, $this->branch)['items'])->firstWhere('key', 'routing')['status'])->toBe('warning');
});

test('effective pause expires and repeated desired-state reopening stays safe', function (): void {
    prepareReadinessBranch($this->branch);
    $closure = app(UpdateBranchTemporaryClosureAction::class);
    $closure->handle($this->owner, $this->branch, true, 'Short kitchen break', now($this->branch->timezone)->addHour()->format('Y-m-d\TH:i'), 0, $this->branch->timezone, (string) Str::uuid());
    $payload = $this->service->handle($this->owner, $this->branch);
    expect($payload['ordering']['kind'])->toBe('manual_pause')->and($payload['ordering']['reason'])->toBe('Short kitchen break')->and($payload['ordering']['can_accept_orders'])->toBeFalse();
    $this->travel(2)->hours();
    expect($this->service->handle($this->owner, $this->branch)['ordering']['kind'])->toBe('open');
    $closure->handle($this->owner, $this->branch, false, null, null, $this->branch->fresh()->pause_version, $this->branch->timezone, (string) Str::uuid());
    $closure->handle($this->owner, $this->branch, false, null, null, $this->branch->fresh()->pause_version, $this->branch->timezone, (string) Str::uuid());
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
});

test('repair links and staff information obey current branch permissions', function (): void {
    $waiter = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->for($waiter)->forSystemRole(SystemRole::Waiter)->active()->create();
    $payload = $this->service->handle($waiter, $this->branch);
    expect(array_column($payload['items'], 'url'))->each->toBeNull()->and($payload['ordering']['can_manage'])->toBeFalse()
        ->and(array_column($payload['items'], 'key'))->not->toContain('staff');
});

/** @return array{ServicePoint, QrCode, Menu, MenuItem} */
function prepareReadinessBranch(Branch $branch): array
{
    $point = ServicePoint::factory()->for($branch)->create();
    $qr = QrCode::factory()->forServicePoint($point)->create();
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);
    $department = KitchenDepartment::factory()->for($branch)->create(['is_active' => true]);
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->for($department, 'kitchenDepartment')->create(['is_available' => true]);

    return [$point, $qr, $menu, $item];
}

test('readiness distinguishes partial QR coverage from complete loss and branch deactivation', function (): void {
    prepareReadinessBranch($this->branch);
    ServicePoint::factory()->for($this->branch)->create();
    expect(collect($this->service->handle($this->owner, $this->branch)['items'])->firstWhere('key', 'qr')['status'])->toBe('warning');
    $this->branch->update(['is_active' => false]);
    expect($this->service->handle($this->owner, $this->branch)['ordering']['kind'])->toBe('setup_problem');
});

test('readiness follows the existing menu schedule and variant availability', function (): void {
    [, , $menu, $item] = prepareReadinessBranch($this->branch);
    $this->travelTo(now($this->branch->timezone)->startOfDay()->addHours(12));
    MenuAvailabilitySchedule::factory()->for($menu)->create(['day_of_week' => now($this->branch->timezone)->isoWeekday(), 'starts_at' => '14:00', 'ends_at' => '16:00']);
    expect(collect($this->service->handle($this->owner, $this->branch)['items'])->firstWhere('key', 'menu')['status'])->toBe('blocker');
    $this->travel(3)->hours();
    expect(collect($this->service->handle($this->owner, $this->branch)['items'])->firstWhere('key', 'menu')['status'])->toBe('ready');
    MenuItemVariant::factory()->for($item, 'item')->create(['is_available' => false]);
    expect(collect($this->service->handle($this->owner, $this->branch)['items'])->firstWhere('key', 'menu')['status'])->toBe('blocker');
});

test('readiness never reads QR or image storage while preparing polling data', function (): void {
    prepareReadinessBranch($this->branch);
    Storage::shouldReceive('disk')->never();
    expect($this->service->handle($this->owner, $this->branch)['ordering']['can_accept_orders'])->toBeTrue();
});

test('a revoked QR is a specific entrance limitation and does not pause the restaurant', function (): void {
    [, $qr] = prepareReadinessBranch($this->branch);
    app(DisableQrCodeAction::class)->handle($qr, $this->owner);
    $payload = $this->service->handle($this->owner, $this->branch);
    expect($payload['ordering']['can_accept_orders'])->toBeTrue()
        ->and(collect($payload['items'])->firstWhere('key', 'qr')['status'])->toBe('blocker');
});
