<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\DraftOrderStatus;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionStatus;
use App\Livewire\Waiter\Dashboard;
use App\Livewire\Workspace\RestaurantSwitcher;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationUser;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WaiterCall;
use App\Services\Waiter\WaiterTableQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Operational branch group']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create(['name' => 'A selected branch']);
    $this->waiter = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->for($this->waiter)->forSystemRole(SystemRole::Waiter)->active()->create();
});

test('attention types select matching tables and share current counts across screens', function (string $attention, string $table): void {
    $pending = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create(['name' => 'Pending table']))->active()->create();
    DraftOrder::factory()->for($pending)->create(['status' => DraftOrderStatus::SentToWaiter]);
    $calls = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create(['name' => 'Calls table']))->active()->create();
    WaiterCall::factory()->forTableSession($calls)->create();
    TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create(['name' => 'Bill table']))->active()->create(['status' => TableSessionStatus::PaymentRequested]);
    $other = Branch::factory()->create();
    TableSession::factory()->forServicePoint(ServicePoint::factory()->for($other)->create())->active()->create();

    $payload = app(BuildWaiterDashboardAction::class)->handle($this->waiter, 'all', $this->branch->id, attention: $attention);
    $counts = app(WaiterTableQueryService::class)->operationCounts($this->waiter, $this->branch);
    expect(array_column($payload['branches'][0]['service_points'], 'name'))->toBe([$table])
        ->and($counts['new_draft_count'])->toBe($payload['new_draft_count'])->toBe(1)
        ->and($counts['waiter_call_count'])->toBe($payload['waiter_call_count'])->toBe(1)
        ->and($counts['bill_request_count'])->toBe($payload['bill_request_count'])->toBe(1);
})->with(['pending' => ['pending', 'Pending table'], 'calls' => ['calls', 'Calls table'], 'bills' => ['bills', 'Bill table']]);

test('deep table selection loads an off-page table within the selected branch', function (): void {
    ServicePoint::factory()->count(50)->for($this->branch)->sequence(fn ($sequence) => ['name' => sprintf('A %03d', $sequence->index)])->create();
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create(['name' => 'Z linked table']))->active()->create();
    Livewire::actingAs($this->waiter)->withQueryParams(['branch' => (string) $this->branch->id, 'table' => (string) $session->id, 'zone' => 'all'])->test(Dashboard::class)
        ->assertSet('selectedTableSessionId', $session->id)->assertSee('Z linked table')->assertSee('data-waiter-table-preview', false);
});

test('branch change clears dependent state and table identity', function (): void {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    Livewire::actingAs($this->waiter)->test(RestaurantSwitcher::class, ['branchId' => $this->branch->id, 'destination' => 'waiter'])
        ->set('form.branchId', (string) $other->id)->call('choose')
        ->assertRedirect(route('restaurant.waiter.dashboard', ['branch' => $other->id]));
    Livewire::actingAs($this->waiter)->withQueryParams(['branch' => $other->id])->test(Dashboard::class)
        ->assertSet('selectedTableSessionId', null)->assertSet('tablePage', 1)->assertSet('zoneScope', 'mine');
});

test('malformed raw branch and table identifiers fail closed', function (string $parameter, mixed $value): void {
    Livewire::actingAs($this->waiter)->withQueryParams(['branch' => $this->branch->id, $parameter => $value])->test(Dashboard::class)->assertStatus(422);
})->with(['branch array' => ['branch', ['1']], 'branch float' => ['branch', '1.0'], 'table exponent' => ['table', '1e0'], 'table array' => ['table', ['1']], 'attention array' => ['attention', ['calls']], 'attention unknown' => ['attention', 'foreign']]);

test('table deep links cannot expose a foreign branch session', function (): void {
    $session = TableSession::factory()->active()->create();
    Livewire::actingAs($this->waiter)->withQueryParams(['branch' => $this->branch->id, 'table' => $session->id])->test(Dashboard::class)->assertNotFound();
});

test('revoking access after mount blocks even plain refresh and local selection responses', function (string $action): void {
    $session = TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    $component = Livewire::actingAs($this->waiter)->test(Dashboard::class);
    $this->organization->users()->updateExistingPivot($this->waiter->id, ['status' => OrganizationUserStatus::Suspended]);
    $component->call($action, ...($action === 'selectTable' ? [$session->id] : []))->assertForbidden();
})->with(['$refresh', 'selectTable', 'refreshDashboard']);

test('boolean Livewire branch transport cannot be coerced to an accessible id', function (): void {
    $component = Livewire::actingAs($this->waiter)->test(Dashboard::class);
    expect(fn () => $component->set('selectedBranchId', true))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('all branch current counts exclude archived and suspended organizations', function (): void {
    TableSession::factory()->forServicePoint(ServicePoint::factory()->for($this->branch)->create())->active()->create();
    expect(app(WaiterTableQueryService::class)->operationCounts($this->waiter)['active_session_count'])->toBe(1);
    $this->organization->subscription()->update(['status' => OrganizationSubscriptionStatus::Inactive]);
    expect(app(WaiterTableQueryService::class)->operationCounts($this->waiter)['active_session_count'])->toBe(0);
});

test('ready drilldown counts only unserved ready positions and keeps current work outside report dates', function (): void {
    $point = ServicePoint::factory()->for($this->branch)->create(['name' => 'Ready table']);
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $order = Order::factory()->for($session)->create(['branch_id' => $this->branch->id, 'service_point_id' => $point->id]);
    $ticket = KitchenTicket::factory()->forOrder($order)->create();
    $item = OrderItem::factory()->for($order)->create();
    KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $item)->ready()->create();
    $served = OrderItem::factory()->for($order)->create();
    KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $served)->ready()->create(['served_at' => now()]);
    $payload = app(BuildWaiterDashboardAction::class)->handle($this->waiter, 'all', $this->branch->id, attention: 'ready');
    expect($payload['ready_item_count'])->toBe(1)->and(array_column($payload['branches'][0]['service_points'], 'name'))->toBe(['Ready table']);
    Livewire::actingAs($this->waiter)->withQueryParams(['branch' => $this->branch->id, 'attention' => 'ready', 'period' => 'custom', 'from' => '2000-01-01', 'to' => '2000-01-02'])->test(Dashboard::class)->assertSet('readyItemCount', 1)->assertSee('Ready table');
});

test('single and batched branch permissions both reject archived or inactive subscriptions', function (string $state): void {
    if ($state === 'archived') {
        $this->organization->delete();
    } else {
        $this->organization->subscription()->update(['status' => OrganizationSubscriptionStatus::Inactive]);
    }
    $resolver = app(ResolveWaiterAccessibleBranchIdsAction::class);
    expect($resolver->handle($this->waiter))->toBeEmpty()
        ->and($resolver->handleMany($this->waiter, [SystemPermission::ViewOrders])[SystemPermission::ViewOrders->value])->toBeEmpty();
})->with(['archived', 'inactive subscription']);
