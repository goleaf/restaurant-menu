<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('branches store temporary closed mode fields', function () {
    expect(Schema::hasColumns('branches', [
        'is_temporarily_closed',
        'temporary_closed_reason',
        'temporary_closed_until',
    ]))->toBeTrue();
});

test('owner can enable and disable temporary closed mode from branch settings', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00', 'Europe/Vilnius'));

    [$organization, $brand, $branch, $owner] = createPrompt103Branch();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('temporarilyClosed', false)
        ->set('temporarilyClosed', true)
        ->set('temporaryClosedReason', 'Частное мероприятие')
        ->set('temporaryClosedUntil', '2026-06-04T18:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'))
        ->assertSee('Settings saved.');

    $branch->refresh();

    expect($branch->is_temporarily_closed)->toBeTrue()
        ->and($branch->temporary_closed_reason)->toBe('Частное мероприятие')
        ->and($branch->temporaryClosedUntilForBranch()?->format('Y-m-d H:i'))->toBe('2026-06-04 18:00');

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('temporarilyClosed', false)
        ->call('save')
        ->assertHasNoErrors();

    $branch->refresh();

    expect($branch->is_temporarily_closed)->toBeFalse()
        ->and($branch->temporary_closed_reason)->toBeNull()
        ->and($branch->temporary_closed_until)->toBeNull();
});

test('temporary closed mode has priority over opening hours and can expire', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00', 'Europe/Vilnius'));

    [, , $branch] = createPrompt103Branch(withOwner: false);
    $branch->openingHours()->create([
        'day_of_week' => 4,
        'is_closed' => false,
        'opens_at' => '08:00',
        'closes_at' => '22:00',
        'sort_order' => 10,
    ]);
    $branch->update([
        'is_temporarily_closed' => true,
        'temporary_closed_reason' => 'Кухня закрыта',
        'temporary_closed_until' => Carbon::parse('2026-06-04 18:00:00', 'Europe/Vilnius')->setTimezone('UTC'),
    ]);

    $status = app(GetBranchOpeningStatusAction::class)->handle($branch->fresh(), Carbon::parse('2026-06-04 10:30:00', 'Europe/Vilnius'));

    expect($status['is_open'])->toBeFalse()
        ->and($status['can_accept_orders'])->toBeFalse()
        ->and($status['label'])->toBe(__('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'))
        ->and($status['detail'])->toContain('Кухня закрыта')
        ->and($status['detail'])->toContain(__('ui.actions.branches.getbranchopeningstatusaction.zakryto_do', ['time' => '6:00 PM']));

    $expiredStatus = app(GetBranchOpeningStatusAction::class)->handle($branch->fresh(), Carbon::parse('2026-06-04 18:01:00', 'Europe/Vilnius'));

    expect($expiredStatus['is_open'])->toBeTrue()
        ->and($expiredStatus['can_accept_orders'])->toBeTrue()
        ->and($expiredStatus['label'])->toBe(__('ui.actions.branches.getbranchopeningstatusaction.seicas_otkryto'));
});

test('public qr opens during temporary closed mode and guest can view menu without ordering', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00', 'Europe/Vilnius'));

    [$qrCode] = createPrompt103GuestContext(withActiveSession: false);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('landing.opening_status_label', __('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'))
        ->assertSet('landing.can_accept_orders', false)
        ->assertSee(__('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'))
        ->assertSee('Технические работы')
        ->set('guestName', 'Ana')
        ->call('enterTable')
        ->assertSet('guestCanViewTable', true)
        ->assertSet('guestCanAddItems', false)
        ->assertSee(__('menu.guest.title'))
        ->assertSee(__('guest.table.orders_after_opening'));
});

test('temporary closed mode blocks guest draft item creation and sending draft to waiter', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00', 'Europe/Vilnius'));

    [, $branch, , $tableSession, $guest, $menuItem] = createPrompt103GuestContext();

    expect(fn () => app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    ))->toThrow(ValidationException::class, __('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'));

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);
    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($guest, 'guest')
        ->for($menuItem)
        ->create(['item_name' => 'Margherita']);

    expect(fn () => app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest))
        ->toThrow(ValidationException::class, __('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'));

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::Draft);
    expect($branch->fresh()->is_temporarily_closed)->toBeTrue();
});

test('waiter can disable temporary closed mode from dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00', 'Europe/Vilnius'));

    [$organization, , $branch] = createPrompt103Branch(withOwner: false);
    $branch->update([
        'is_temporarily_closed' => true,
        'temporary_closed_reason' => 'Ресторан закрыт сегодня',
        'temporary_closed_until' => Carbon::parse('2026-06-04 18:00:00', 'Europe/Vilnius')->setTimezone('UTC'),
    ]);
    $waiter = User::factory()->create(['name' => 'Prompt 103 Waiter']);

    attachPrompt103Waiter($waiter, $organization);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSee(__('ui.actions.branches.getbranchopeningstatusaction.restoran_vremenno_zakryt'))
        ->assertSee('Ресторан закрыт сегодня')
        ->assertSee(__('ui.waiter.dashboard.otkryt_zakazy'))
        ->call('disableTemporaryClosure', $branch->id)
        ->assertHasNoErrors()
        ->assertSee(__('ui.livewire.waiter.dashboard.restoran_snova_otkryt_dlia_zakazov'))
        ->assertDontSee(__('ui.waiter.dashboard.otkryt_zakazy'));

    $branch->refresh();

    expect($branch->is_temporarily_closed)->toBeFalse()
        ->and($branch->temporary_closed_reason)->toBeNull()
        ->and($branch->temporary_closed_until)->toBeNull();
});

function createPrompt103Branch(bool $withOwner = true): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 103 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 103 Brand']);
    $branch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Prompt 103 Branch',
        'address' => 'Pilies 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    if ($withOwner) {
        return [$organization, $brand, $branch, $owner->fresh()];
    }

    return [$organization, $brand, $branch];
}

function createPrompt103GuestContext(bool $withActiveSession = true): array
{
    [, , $branch] = createPrompt103Branch(withOwner: false);
    $branch->update([
        'is_temporarily_closed' => true,
        'temporary_closed_reason' => 'Технические работы',
        'temporary_closed_until' => Carbon::parse('2026-06-04 18:00:00', 'Europe/Vilnius')->setTimezone('UTC'),
    ]);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Window Table',
            'is_active' => true,
            'status' => ServicePointStatus::Occupied,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt103'.fake()->unique()->numerify('########'),
            'short_code' => 'QR-P103',
            'status' => QrCodeStatus::Active,
        ]);
    $tableSession = null;
    $guest = null;

    if ($withActiveSession) {
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
    }

    $menu = Menu::factory()
        ->for($branch)
        ->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['is_active' => true]);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Margherita',
            'price_cents' => 1450,
            'is_available' => true,
        ]);

    return [$qrCode, $branch, $servicePoint, $tableSession, $guest, $menuItem];
}

function attachPrompt103Waiter(User $user, Organization $organization): Role
{
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $viewOrders = Permission::query()
        ->where('code', SystemPermission::ViewOrders->value)
        ->firstOrFail();

    $waiterRole->permissions()->updateExistingPivot($viewOrders->id, ['enabled' => true]);

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $waiterRole;
}
