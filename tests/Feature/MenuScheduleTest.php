<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Menus\GetMenuAvailabilityStatusAction;
use App\Actions\Menus\UpdateMenuAvailabilityScheduleAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog as MenuCatalog;
use App\Livewire\PublicQr\GuestMenu;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('menu schedule table stores weekday intervals', function () {
    expect(Schema::hasTable('menu_availability_schedules'))->toBeTrue();
    expect(Schema::hasColumns('menu_availability_schedules', [
        'menu_id',
        'day_of_week',
        'starts_at',
        'ends_at',
    ]))->toBeTrue();
});

test('menu availability respects branch timezone current interval and next interval', function () {
    [$menu] = createPrompt104MenuContext();

    MenuAvailabilitySchedule::factory()
        ->for($menu)
        ->create(['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);
    MenuAvailabilitySchedule::factory()
        ->for($menu)
        ->create(['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '16:00']);

    $action = app(GetMenuAvailabilityStatusAction::class);

    $breakfast = $action->handle($menu, Carbon::parse('2026-06-01 09:30:00', 'Europe/Vilnius'));
    $betweenDays = $action->handle($menu, Carbon::parse('2026-06-01 17:30:00', 'Europe/Vilnius'));

    expect($breakfast['is_configured'])->toBeTrue()
        ->and($breakfast['is_available'])->toBeTrue()
        ->and($breakfast['label'])->toBe(__('menu.guest.available_now'))
        ->and($breakfast['detail'])->toBe(__('menu.guest.available_until', ['time' => '12:00 PM']));

    expect($betweenDays['is_configured'])->toBeTrue()
        ->and($betweenDays['is_available'])->toBeFalse()
        ->and($betweenDays['label'])->toBe(__('menu.guest.unavailable'))
        ->and($betweenDays['detail'])->toBe(__('menu.guest.available_from', ['time' => __('menu.guest.days.mon').' 8:00 AM']));
});

test('guest menu only returns menus available now and clears database cache on schedule change', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:30:00', 'Europe/Vilnius'));

    [$breakfastMenu, $branch] = createPrompt104MenuContext(menuName: 'Breakfast menu');
    [, , $breakfastItem] = createPrompt104MenuRows($breakfastMenu, 'Omelette');
    $lunchMenu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Lunch menu',
            'status' => MenuStatus::Active,
            'sort_order' => 20,
        ]);
    [, , $lunchItem] = createPrompt104MenuRows($lunchMenu, 'Burger');
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'en');

    MenuAvailabilitySchedule::factory()
        ->for($breakfastMenu)
        ->create(['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);
    MenuAvailabilitySchedule::factory()
        ->for($lunchMenu)
        ->create(['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '16:00']);

    $payload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeTrue()
        ->and($payload['menu']['name'])->toBe('Breakfast menu')
        ->and($payload['availability']['is_available'])->toBeTrue()
        ->and($payload['categories'][0]['items'][0]['id'])->toBe($breakfastItem->id)
        ->and(collect($payload['categories'])->flatMap(fn (array $category): array => $category['items'])->pluck('id')->contains($lunchItem->id))->toBeFalse();

    $breakfastMenu->availabilitySchedules()->firstOrFail()->update(['ends_at' => '09:00']);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    $freshPayload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    expect($freshPayload['menu'])->toBeNull()
        ->and($freshPayload['availability']['is_available'])->toBeFalse()
        ->and($freshPayload['availability']['detail'])->toBe(__('menu.guest.available_from', ['time' => '12:00 PM']));

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSeeText(__('menu.guest.unavailable'))
        ->assertSeeText(__('menu.guest.available_from', ['time' => '12:00 PM']))
        ->assertDontSeeText('Omelette')
        ->assertDontSeeText('Burger');
});

test('guest menu returns multiple active available menus grouped and sorted', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 09:30:00', 'Europe/Vilnius'));

    [$mainMenu, $branch] = createPrompt104MenuContext(menuName: 'Main menu');
    $mainMenu->update(['sort_order' => 30]);
    [, , $mainItem] = createPrompt104MenuRows($mainMenu, 'Margherita');

    $breakfastMenu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Breakfast menu',
            'status' => MenuStatus::Active,
            'sort_order' => 10,
        ]);
    [, , $breakfastItem] = createPrompt104MenuRows($breakfastMenu, 'Omelette');

    $lunchMenu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Business lunch',
            'status' => MenuStatus::Active,
            'sort_order' => 20,
        ]);
    [, , $lunchItem] = createPrompt104MenuRows($lunchMenu, 'Soup');

    $draftMenu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Wine card',
            'status' => MenuStatus::Draft,
            'sort_order' => 5,
        ]);
    createPrompt104MenuRows($draftMenu, 'Draft wine');

    MenuAvailabilitySchedule::factory()
        ->for($breakfastMenu)
        ->create(['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);
    MenuAvailabilitySchedule::factory()
        ->for($lunchMenu)
        ->create(['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '16:00']);

    $payload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    expect($payload['menus'])->toHaveCount(2)
        ->and(collect($payload['menus'])->pluck('name')->all())->toBe([
            'Breakfast menu',
            'Main menu',
        ])
        ->and($payload['menu']['name'])->toBe('Breakfast menu')
        ->and($payload['categories'][0]['items'][0]['id'])->toBe($breakfastItem->id)
        ->and(collect($payload['menus'])->flatMap(fn (array $menu): array => collect($menu['categories'])->flatMap(fn (array $category): array => $category['items'])->all())->pluck('id')->all())->toBe([
            $breakfastItem->id,
            $mainItem->id,
        ])
        ->and(collect($payload['unavailable_menus'])->pluck('name')->all())->toBe(['Business lunch'])
        ->and(collect($payload['unavailable_menus'])->pluck('availability.detail')->all())->toBe([__('menu.guest.available_from', ['time' => '12:00 PM'])])
        ->and(collect($payload['menus'])->pluck('name')->contains('Wine card'))->toBeFalse()
        ->and(collect($payload['menus'])->flatMap(fn (array $menu): array => collect($menu['categories'])->flatMap(fn (array $category): array => $category['items'])->all())->pluck('id')->contains($lunchItem->id))->toBeFalse();

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSeeTextInOrder(['Breakfast menu', 'Omelette', 'Main menu', 'Margherita'])
        ->assertSeeText('Business lunch')
        ->assertSeeText(__('menu.guest.available_from', ['time' => '12:00 PM']))
        ->assertDontSeeText('Soup')
        ->assertDontSeeText('Wine card')
        ->assertDontSeeText('Draft wine');
});

test('manager can add and delete menu schedule from menu admin', function () {
    [$menu, $branch, $organization, $brand, $manager] = createPrompt104MenuContext(withManager: true);
    grantPrompt104MenuPermission($manager, $organization, SystemPermission::ManageMenu);

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->assertSeeText('Menu schedule')
        ->set('scheduleMenuId', (string) $menu->id)
        ->set('scheduleDayOfWeek', '1')
        ->set('scheduleStartsAt', '08:00')
        ->set('scheduleEndsAt', '12:00')
        ->call('createMenuSchedule')
        ->assertHasNoErrors()
        ->assertSeeText(__('ui.actions.branches.getbranchopeningstatusaction.ponedelnik'))
        ->assertSeeText('08:00-12:00');

    $schedule = MenuAvailabilitySchedule::query()
        ->where('menu_id', $menu->id)
        ->firstOrFail();

    expect($schedule->day_of_week)->toBe(1)
        ->and($schedule->starts_at)->toBe('08:00')
        ->and($schedule->ends_at)->toBe('12:00');

    Livewire::actingAs($manager->fresh())
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('deleteMenuSchedule', $schedule->id)
        ->assertHasNoErrors();

    expect(MenuAvailabilitySchedule::query()->whereKey($schedule->id)->exists())->toBeFalse();
});

test('manager updates a menu schedule and invalidates every guest menu cache', function () {
    [$menu, $branch, $organization, $brand, $manager] = createPrompt104MenuContext(withManager: true);
    grantPrompt104MenuPermission($manager, $organization, SystemPermission::ManageMenu);
    $schedule = MenuAvailabilitySchedule::factory()->for($menu)->create([
        'day_of_week' => 2,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'en');
    app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');

    Livewire::actingAs($manager)
        ->test(MenuCatalog::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->call('startEditingMenuSchedule', $schedule->id)
        ->assertSet('editingScheduleDayOfWeek', '2')
        ->set('editingScheduleDayOfWeek', '3')
        ->set('editingScheduleStartsAt', '09:30')
        ->set('editingScheduleEndsAt', '14:00')
        ->call('updateMenuSchedule')
        ->assertHasNoErrors()
        ->assertSeeText('09:30-14:00');

    expect($schedule->refresh()->day_of_week)->toBe(3)
        ->and($schedule->starts_at)->toBe('09:30')
        ->and($schedule->ends_at)->toBe('14:00')
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();
});

test('menu schedule update rejects reversed and overlapping intervals', function () {
    [$menu, $branch] = createPrompt104MenuContext();
    $schedule = MenuAvailabilitySchedule::factory()->for($menu)->create([
        'day_of_week' => 2,
        'starts_at' => '08:00',
        'ends_at' => '12:00',
    ]);
    MenuAvailabilitySchedule::factory()->for($menu)->create([
        'day_of_week' => 2,
        'starts_at' => '13:00',
        'ends_at' => '17:00',
    ]);
    $action = app(UpdateMenuAvailabilityScheduleAction::class);

    expect(fn () => $action->handle($branch, $schedule, 2, '12:00', '11:00'))
        ->toThrow(ValidationException::class)
        ->and(fn () => $action->handle($branch, $schedule, 2, '12:30', '14:00'))
        ->toThrow(ValidationException::class);

    expect($schedule->fresh()->starts_at)->toBe('08:00')
        ->and($schedule->ends_at)->toBe('12:00');
});

test('menu schedule update cannot cross the branch boundary', function () {
    [, $branch] = createPrompt104MenuContext();
    [$foreignMenu] = createPrompt104MenuContext(menuName: 'Foreign schedule menu');
    $foreignSchedule = MenuAvailabilitySchedule::factory()->for($foreignMenu)->create();

    expect(fn () => app(UpdateMenuAvailabilityScheduleAction::class)->handle(
        $branch,
        $foreignSchedule,
        4,
        '09:00',
        '11:00',
    ))->toThrow(ModelNotFoundException::class);

    expect($foreignSchedule->fresh())->not->toBeNull();
});

test('unavailable scheduled menu blocks adding and sending draft items', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 15:30:00', 'Europe/Vilnius'));

    [$menu, , , , , $tableSession, $guest, $menuItem] = createPrompt104GuestDraftContext();
    MenuAvailabilitySchedule::factory()
        ->for($menu)
        ->create(['day_of_week' => 1, 'starts_at' => '08:00', 'ends_at' => '12:00']);

    expect(fn () => app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    ))->toThrow(ValidationException::class, __('menu.guest.available_from', ['time' => __('menu.guest.days.mon').' 8:00 AM']));

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);
    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($guest, 'guest')
        ->for($menuItem)
        ->create(['item_name' => 'Breakfast toast']);

    expect(fn () => app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest))
        ->toThrow(ValidationException::class, __('menu.guest.available_from', ['time' => __('menu.guest.days.mon').' 8:00 AM']));

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::Draft);
});

function createPrompt104MenuContext(string $menuName = 'Prompt 104 Menu', bool $withManager = false): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'Prompt 104 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 104 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 104 Branch',
            'address' => 'Schedule Street 1',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'is_active' => true,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => $menuName,
            'status' => MenuStatus::Active,
            'sort_order' => 10,
        ]);

    if ($withManager) {
        return [$menu, $branch, $organization, $brand, $manager->fresh()];
    }

    return [$menu, $branch, $organization, $brand];
}

function createPrompt104MenuRows(Menu $menu, string $itemName): array
{
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => $itemName.' category',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => $itemName,
            'price_cents' => 1000,
            'is_available' => true,
            'sort_order' => 10,
        ]);

    return [$menu, $category, $item];
}

function createPrompt104GuestDraftContext(): array
{
    [$menu, $branch, $organization, $brand] = createPrompt104MenuContext();
    [, , $menuItem] = createPrompt104MenuRows($menu, 'Breakfast toast');
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Schedule table',
            'is_active' => true,
            'status' => ServicePointStatus::Occupied,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    return [$menu, $branch, $organization, $brand, $servicePoint, $tableSession, $guest, $menuItem];
}

function grantPrompt104MenuPermission(User $user, Organization $organization, SystemPermission $permission): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->firstOrFail()
        ->load('role');

    $permissionRow = Permission::query()
        ->where('code', $permission->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permissionRow->id, ['enabled' => true]);
}
