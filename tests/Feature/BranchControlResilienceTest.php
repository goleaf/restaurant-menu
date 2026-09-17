<?php

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Livewire\Restaurant\Dashboard;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\DraftOrder;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WaiterCall;
use App\Services\Reports\BranchReportQuery;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-04 12:00:00', 'UTC'));
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create(['name' => 'Resilient branch', 'timezone' => 'Europe/Vilnius']);
    $this->owner = User::factory()->create();
    $this->membership = OrganizationUser::factory()->forOrganization($this->branch->organization)
        ->forUser($this->owner)->forSystemRole(SystemRole::Owner)->active()->create();
});

test('historical reporting leaves every current operational queue and destination unchanged', function (): void {
    $pending = TableSession::factory()->recycle($this->branch)->active()->create();
    DraftOrder::factory()->forTableSession($pending)->create(['status' => DraftOrderStatus::SentToWaiter]);
    WaiterCall::factory()->forTableSession($pending)->create();
    TableSession::factory()->recycle($this->branch)->active()->create(['status' => TableSessionStatus::PaymentRequested]);
    $ready = TableSession::factory()->recycle($this->branch)->active()->create();
    $order = Order::factory()->forTableSession($ready)->create(['total_price_cents' => 1200]);
    $item = OrderItem::factory()->for($order)->create(['table_session_guest_id' => null, 'total_price_cents' => 1200]);
    $ticket = KitchenTicket::factory()->forOrder($order)->create();
    KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $item)->ready()->create();

    $action = app(BuildRestaurantDashboardAction::class);
    $today = $action->handle($this->owner, $this->branch->id)['dashboard'];
    $history = $action->handle($this->owner, $this->branch->id, 'custom', '2000-01-01', '2000-01-02')['dashboard'];

    expect(array_column($today['operations'], 'value', 'key'))->toBe([
        'all' => 3, 'pending' => 1, 'ready' => 1, 'calls' => 1, 'bills' => 1,
    ])->and($today['report_snapshot']['orders_count'])->toBe(1)
        ->and($history['report_snapshot']['orders_count'])->toBe(0)
        ->and($history['report']['period_label'])->toBe('2000-01-01 – 2000-01-02')
        ->and($history['operations'])->toBe($today['operations']);
});

test('failed report refresh preserves its true age and revoked report access removes stale results', function (): void {
    $session = TableSession::factory()->recycle($this->branch)->active()->create();
    $order = Order::factory()->forTableSession($session)->create(['total_price_cents' => 4300]);
    OrderItem::factory()->for($order)->create([
        'table_session_guest_id' => null, 'item_name' => 'Historical resilience dish', 'total_price_cents' => 4300,
    ]);
    $fresh = app(BuildRestaurantDashboardAction::class)->handle($this->owner, $this->branch->id)['dashboard'];
    $generatedAt = CarbonImmutable::parse('2026-06-04 12:00:00', 'UTC');
    $displayedAt = LocalizedDateFormatter::dateTime($generatedAt->setTimezone($this->branch->timezone));
    expect($fresh['report_snapshot']['generated_at'])->toBe($generatedAt->toIso8601String())
        ->and($fresh['report']['cached_at'])->toBe($displayedAt);

    $this->mock(BranchReportQuery::class)->shouldReceive('handle')->twice()
        ->andThrow(new RuntimeException('Simulated report read failure.'));
    $this->travel(2)->minutes();

    $component = Livewire::actingAs($this->owner)->test(Dashboard::class)
        ->assertSee('Historical resilience dish')
        ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['report']['stale']
            && ! $dashboard['report']['unavailable']
            && $dashboard['report']['metrics'] === $fresh['report']['metrics']
            && $dashboard['report']['cached_at'] === $displayedAt
            && $dashboard['report_snapshot']['generated_at'] === $generatedAt->toIso8601String()
            && $dashboard['operations_updated_at'] !== $displayedAt);

    $this->travel(1)->minutes();
    $component->call('$refresh')->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['report']['stale']
        && $dashboard['report']['cached_at'] === $displayedAt
        && $dashboard['report_snapshot']['generated_at'] === $generatedAt->toIso8601String());

    $permission = Permission::query()->select(['id', 'code'])->where('code', SystemPermission::ViewReports->value)->firstOrFail();
    PermissionUserOverride::factory()->forUser($this->owner)->forPermission($permission)->denied()->create();
    $component->call('$refresh')->assertSet('canAccessRestaurantDashboard', true)
        ->assertDontSee('Historical resilience dish')
        ->assertViewHas('dashboard', fn (array $dashboard): bool => ! $dashboard['report']['can_view_reports']
            && $dashboard['report']['metrics'] === []
            && $dashboard['report']['popular_items'] === []
            && $dashboard['report']['cached_at'] === null
            && $dashboard['report_snapshot'] === null
            && $dashboard['operations'] !== []);

    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $component->call('$refresh')->assertSet('canAccessRestaurantDashboard', false)
        ->assertDontSee('Resilient branch')->assertDontSee('Historical resilience dish');
});

test('failed first report load is unavailable instead of a successful zero total', function (): void {
    $this->mock(BranchReportQuery::class)->shouldReceive('handle')->once()
        ->andThrow(new RuntimeException('Simulated first report read failure.'));

    $dashboard = app(BuildRestaurantDashboardAction::class)->handle($this->owner, $this->branch->id)['dashboard'];

    expect($dashboard['report']['can_view_reports'])->toBeTrue()
        ->and($dashboard['report']['unavailable'])->toBeTrue()
        ->and($dashboard['report']['metrics'])->toBeEmpty()
        ->and($dashboard['report']['cached_at'])->toBeNull()
        ->and($dashboard['report_snapshot'])->toBeNull()
        ->and($dashboard['operations'])->not->toBeEmpty();
});

test('Livewire renders report content without serializing it into public component state', function (): void {
    $session = TableSession::factory()->recycle($this->branch)->active()->create();
    $order = Order::factory()->forTableSession($session)->create(['total_price_cents' => 1700]);
    OrderItem::factory()->for($order)->create([
        'table_session_guest_id' => null, 'item_name' => 'Report-only historical entrée', 'total_price_cents' => 1700,
    ]);

    $component = Livewire::actingAs($this->owner)->test(Dashboard::class)->assertSee('Report-only historical entrée');
    expect($component->snapshot['data'])->not->toHaveKey('dashboard')
        ->not->toHaveKey('report_snapshot')->not->toHaveKey('report')
        ->and(json_encode($component->snapshot, JSON_THROW_ON_ERROR))->not->toContain('Report-only historical');

    $component->call('$refresh')->assertSee('Report-only historical entrée');
    expect($component->snapshot['data'])->not->toHaveKey('dashboard')
        ->not->toHaveKey('report_snapshot')->not->toHaveKey('report')
        ->and(json_encode($component->snapshot, JSON_THROW_ON_ERROR))->not->toContain('Report-only historical');
});

test('an open schedule cannot label blocked guest ordering as accepting orders', function (): void {
    BranchOpeningHour::factory()->for($this->branch)->open('10:00', '22:00')->create(['day_of_week' => 4]);
    expect(app(GetBranchOpeningStatusAction::class)->handle($this->branch)['is_open'])->toBeTrue();

    Livewire::actingAs($this->owner)->test(Dashboard::class)
        ->assertSee(__('availability.guest.unavailable'))
        ->assertViewHas('dashboard', fn (array $dashboard): bool => $dashboard['ordering']['kind'] === 'setup_problem'
            && ! $dashboard['ordering']['can_accept_orders']
            && $dashboard['ordering']['label'] === __('availability.guest.unavailable'));
});
