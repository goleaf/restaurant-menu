<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Livewire\PublicQr\GuestEntry;
use App\Models\BranchOpeningHour;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\QrCode;
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

test('branch opening hours table stores multiple intervals per weekday', function () {
    expect(Schema::hasTable('branch_opening_hours'))->toBeTrue();
    expect(Schema::hasColumns('branch_opening_hours', [
        'branch_id',
        'day_of_week',
        'is_closed',
        'opens_at',
        'closes_at',
        'sort_order',
    ]))->toBeTrue();
});

test('owner can manage branch opening hours from branch settings', function () {
    [$organization, $brand, $branch, $owner] = createPrompt102Branch();

    $intervalEditor = Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch]);
    $mondayIntervals = $intervalEditor->get('openingHours')[0]['intervals'];

    $intervalEditor->call('addOpeningInterval', 1);

    expect($intervalEditor->get('openingHours')[0]['intervals'])->toHaveCount(count($mondayIntervals) + 1);

    $intervalEditor->call('removeOpeningInterval', 1, count($mondayIntervals));

    expect($intervalEditor->get('openingHours')[0]['intervals'])->toHaveCount(count($mondayIntervals));

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('openingHoursConfigured', true)
        ->set('openingHours', prompt102WeeklyHours([
            1 => [
                ['opens_at' => '10:00', 'closes_at' => '14:00'],
                ['opens_at' => '18:00', 'closes_at' => '22:00'],
            ],
            2 => [],
        ]))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Settings saved.');

    $hours = $branch->openingHours()
        ->where('day_of_week', 1)
        ->where('is_closed', false)
        ->orderBy('day_of_week')
        ->orderBy('sort_order')
        ->get();

    expect($hours)->toHaveCount(2)
        ->and($hours[0]->day_of_week)->toBe(1)
        ->and($hours[0]->is_closed)->toBeFalse()
        ->and($hours[0]->opens_at)->toBe('10:00')
        ->and($hours[0]->closes_at)->toBe('14:00')
        ->and($hours[1]->opens_at)->toBe('18:00')
        ->and($hours[1]->closes_at)->toBe('22:00');

    $closedTuesday = $branch->openingHours()
        ->where('day_of_week', 2)
        ->where('is_closed', true)
        ->first();

    expect($closedTuesday)->not->toBeNull();
});

test('opening status respects branch timezone and next interval', function () {
    [, , $branch] = createPrompt102Branch(withOwner: false);
    BranchOpeningHour::factory()
        ->for($branch)
        ->create(['day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '14:00', 'sort_order' => 10]);
    BranchOpeningHour::factory()
        ->for($branch)
        ->create(['day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '22:00', 'sort_order' => 20]);

    $action = app(GetBranchOpeningStatusAction::class);

    $openStatus = $action->handle($branch, Carbon::parse('2026-06-01 11:30:00', 'Europe/Vilnius'));
    $closedStatus = $action->handle($branch, Carbon::parse('2026-06-01 15:30:00', 'Europe/Vilnius'));

    expect($openStatus['is_configured'])->toBeTrue()
        ->and($openStatus['is_open'])->toBeTrue()
        ->and($openStatus['can_accept_orders'])->toBeTrue()
        ->and($openStatus['label'])->toBe(__('ui.actions.branches.getbranchopeningstatusaction.seicas_otkryto'))
        ->and($openStatus['detail'])->toBe(__('ui.actions.branches.getbranchopeningstatusaction.otkryto_do', ['time' => '14:00']));

    expect($closedStatus['is_configured'])->toBeTrue()
        ->and($closedStatus['is_open'])->toBeFalse()
        ->and($closedStatus['can_accept_orders'])->toBeFalse()
        ->and($closedStatus['label'])->toBe(__('ui.actions.branches.getbranchopeningstatusaction.seicas_zakryto'))
        ->and($closedStatus['detail'])->toBe(__('ui.actions.branches.getbranchopeningstatusaction.otkroetsia_v', ['time' => '18:00']));
});

test('opening status labels a next opening on another weekday', function () {
    [, , $branch] = createPrompt102Branch(withOwner: false);
    BranchOpeningHour::factory()
        ->for($branch)
        ->create(['day_of_week' => 2, 'opens_at' => '09:00', 'closes_at' => '17:00']);

    $status = app(GetBranchOpeningStatusAction::class)->handle(
        $branch,
        Carbon::parse('2026-06-01 18:00:00', 'Europe/Vilnius'),
    );
    $nextOpeningLabel = __('ui.actions.branches.getbranchopeningstatusaction.vt').' 09:00';

    expect($status['is_open'])->toBeFalse()
        ->and($status['next_opens_at'])->toBe('2026-06-02T09:00:00+03:00')
        ->and($status['detail'])->toBe(
            __('ui.actions.branches.getbranchopeningstatusaction.otkroetsia_v', ['time' => $nextOpeningLabel]),
        );
});

test('public qr opens when branch is closed and lets guest view the table without ordering', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 15:30:00', 'Europe/Vilnius'));

    [$qrCode] = createPrompt102GuestContext(scheduleIsClosedNow: true, withActiveSession: false);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('landing.opening_status_label', __('ui.actions.branches.getbranchopeningstatusaction.seicas_zakryto'))
        ->assertSet('landing.opening_status_detail', __('ui.actions.branches.getbranchopeningstatusaction.otkroetsia_v', ['time' => '18:00']))
        ->assertSee(__('ui.actions.branches.getbranchopeningstatusaction.seicas_zakryto'))
        ->set('guestName', 'Ana')
        ->call('enterTable')
        ->assertSet('guestCanViewTable', true)
        ->assertSet('guestCanAddItems', false)
        ->assertSee(__('menu.guest.title'))
        ->assertSee(__('guest.table.closed_description'));
});

test('closed branch blocks guest draft item creation and sending draft to waiter', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 15:30:00', 'Europe/Vilnius'));

    [, $branch, , $tableSession, $guest, $menuItem] = createPrompt102GuestContext(scheduleIsClosedNow: true);

    expect(fn () => app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
    ))->toThrow(ValidationException::class, __('ui.actions.branches.getbranchopeningstatusaction.seicas_zakryto'));

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::Draft]);
    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($guest, 'guest')
        ->for($menuItem)
        ->create(['item_name' => 'Margherita']);

    expect(fn () => app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $guest))
        ->toThrow(ValidationException::class, __('ui.actions.branches.getbranchopeningstatusaction.seicas_zakryto'));

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::Draft);
    expect($branch->openingHours()->count())->toBe(2);
});

function createPrompt102Branch(bool $withOwner = true): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 102 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 102 Brand']);
    $branch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Prompt 102 Branch',
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

function createPrompt102GuestContext(bool $scheduleIsClosedNow, bool $withActiveSession = true): array
{
    [, , $branch] = createPrompt102Branch(withOwner: false);

    if ($scheduleIsClosedNow) {
        BranchOpeningHour::factory()
            ->for($branch)
            ->create(['day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '14:00', 'sort_order' => 10]);
        BranchOpeningHour::factory()
            ->for($branch)
            ->create(['day_of_week' => 1, 'opens_at' => '18:00', 'closes_at' => '22:00', 'sort_order' => 20]);
    }

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
            'public_token' => 'prompt102'.fake()->unique()->numerify('########'),
            'short_code' => 'QR-P102',
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

function prompt102WeeklyHours(array $intervalsByDay): array
{
    $labels = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    return collect($labels)
        ->map(fn (string $label, int $dayOfWeek): array => [
            'day_of_week' => $dayOfWeek,
            'label' => $label,
            'is_closed' => ! array_key_exists($dayOfWeek, $intervalsByDay) || $intervalsByDay[$dayOfWeek] === [],
            'intervals' => $intervalsByDay[$dayOfWeek] ?? [],
        ])
        ->values()
        ->all();
}
