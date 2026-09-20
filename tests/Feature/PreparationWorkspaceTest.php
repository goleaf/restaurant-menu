<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Departments\Dashboard;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Preparation group']);
    $brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($brand)->create();
    $point = ServicePoint::factory()->for($this->branch)->create();
    $session = TableSession::factory()->forServicePoint($point)->active()->create();
    $order = Order::factory()->forTableSession($session)->sentToDepartments()->create();
    $this->departments = [];
    $this->items = [];
    $this->tickets = [];
    foreach (['kitchen' => KitchenDepartmentType::Kitchen, 'bar' => KitchenDepartmentType::Bar] as $key => $type) {
        $department = KitchenDepartment::factory()->for($this->branch)->create(['type' => $type, 'name' => 'Preparation '.$key]);
        $ticket = KitchenTicket::factory()->forOrder($order)->create(['kitchen_department_id' => $department->id, 'department_type' => $type->value, 'department_name' => $department->name]);
        $orderItem = OrderItem::factory()->for($order)->create(['item_name' => 'Saved '.$key, 'variant_name' => 'Saved large']);
        $this->items[$key] = KitchenTicketItem::factory()->forDispatchedOrderItem($ticket, $orderItem)->create(['item_name' => 'Saved '.$key]);
        $this->departments[$key] = $department;
        $this->tickets[$key] = $ticket;
    }
    $this->actor = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::HeadChef->value)->firstOrFail();
    $this->organization->users()->syncWithoutDetachingOrFail([$this->actor->id => ['role_id' => $role->id, 'status' => OrganizationUserStatus::Active->value, 'joined_at' => now()]]);
});

test('canonical preparation entry shows only the union of separately authorized departments', function (): void {
    Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id, 'department' => 'all'])
        ->test(Dashboard::class)->assertOk()->assertSet('selectedDepartmentId', 'all')
        ->assertSee('Saved kitchen')->assertSee('Saved bar')->assertSee('Saved large');
    $this->actingAs($this->actor)->get(route('restaurant.preparation.dashboard', ['branch' => $this->branch->id]))->assertOk();
});

test('preparation preserves distinct absent all and invalid department states', function (mixed $invalid): void {
    Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id])
        ->test(Dashboard::class)->set('selectedDepartmentId', $invalid)->assertHasErrors('filters.department');
})->with([['wrong'], [false], [[1]], ['0']]);

test('selected ticket resolves its actual department and contradictory context fails', function (): void {
    Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id, 'ticket' => $this->tickets['bar']->id])
        ->test(Dashboard::class)->assertSet('selectedDepartmentId', (string) $this->departments['bar']->id)
        ->assertSee('Saved bar')->assertDontSee('Saved kitchen');
    Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id, 'department' => $this->departments['kitchen']->id, 'ticket' => $this->tickets['bar']->id])
        ->test(Dashboard::class)->assertStatus(409);
});

test('canonical row action preserves version and never substitutes an invisible target', function (): void {
    $item = $this->items['kitchen'];
    $page = Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id, 'department' => $this->departments['kitchen']->id])->test(Dashboard::class);
    $page->call('setItemStatus', $this->items['bar']->id, 'ready')->assertHasErrors('ticket_item_status');
    $item->forceFill(['status' => KitchenTicketItemStatus::Cancelled])->save();
    $page->call('setItemStatus', $item->id, 'ready', 'new', $item->getRawOriginal('updated_at'), $this->tickets['kitchen']->id)->assertHasErrors('ticket_item_status');
    expect($item->fresh()->status)->toBe(KitchenTicketItemStatus::Cancelled);
});

test('order fulfilment links only authorized preparation tickets with exact context', function (): void {
    $waiter = User::factory()->create();
    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $this->organization->users()->syncWithoutDetachingOrFail([$waiter->id => ['role_id' => $role->id, 'status' => OrganizationUserStatus::Active->value, 'joined_at' => now()]]);
    $tableSession = TableSession::query()->findOrFail($this->tickets['bar']->table_session_id);

    $payload = app(BuildWaiterTableDetailAction::class)->handle($waiter, $tableSession, 'fulfilment');
    $rows = collect($payload['table']['orders'][0]['items'])->keyBy('ticket_item_id');
    expect($rows[$this->items['kitchen']->id]['preparation_url'])->toBeNull()
        ->and($rows[$this->items['bar']->id]['preparation_url'])->toBe(route('restaurant.preparation.dashboard', [
            'branch' => $this->branch->id, 'department' => $this->departments['bar']->id, 'ticket' => $this->tickets['bar']->id,
        ]));
});

test('invalid preparation filters remain validation errors instead of template errors', function (mixed $filter): void {
    Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id])
        ->test(Dashboard::class)->set('ticketFilter', $filter)->assertHasErrors('filters.filter');
})->with([['all'], [['ready']], [false]]);

test('preparation renders localized views and targeted errors without unresolved placeholders', function (string $locale, string $ready, string $history): void {
    app()->setLocale($locale);
    $page = Livewire::actingAs($this->actor)->withQueryParams(['branch' => $this->branch->id, 'department' => 'all'])
        ->test(Dashboard::class)->assertSee($ready)->assertSee($history)->assertDontSee(':count');
    $page->call('setItemStatus', $this->items['kitchen']->id, 'unsupported')
        ->assertHasErrors('ticket_item_status')->assertSee(__('ui.livewire.departments.dashboard.neizvestnyi_status_pozicii'))
        ->assertDontSee('preparation.errors.')->assertDontSee(':id');
})->with([
    ['en', 'Ready to serve', 'History'],
    ['lt', 'Paruošta patiekti', 'Istorija'],
    ['ru', 'Готово к подаче', 'История'],
]);
