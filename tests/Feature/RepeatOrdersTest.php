<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\Orders\SendOrderToKitchenBarAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\Waiter\TableDetail;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('guests can make repeat orders in the same table session', function () {
    [$organization, $servicePoint, $tableSession, $guest, $pizza, $pasta] = createPrompt64RepeatOrderScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 64 Waiter']);
    attachPrompt64Waiter($waiter, $organization);

    $firstItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $pizza,
        selectedModifierOptions: [],
        comment: 'First round',
    );
    $firstDraft = $firstItem->draftOrder()->firstOrFail();

    app(SendDraftOrderToWaiterAction::class)->handle($firstDraft, $guest);
    $firstOrder = app(ConfirmDraftOrderByWaiterAction::class)->handle($firstDraft, $waiter);
    app(SendOrderToKitchenBarAction::class)->handle($firstOrder, $waiter);

    $pizza->update([
        'name' => 'Prompt 64 Renamed Pizza',
        'price' => '99.00',
    ]);

    $firstOrderItem = OrderItem::query()
        ->where('order_id', $firstOrder->id)
        ->firstOrFail();

    expect($firstDraft->fresh()->status)->toBe(DraftOrderStatus::ConvertedToOrder)
        ->and($firstOrder->fresh()->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and($firstOrderItem->item_name)->toBe('Prompt 64 Pizza')
        ->and($firstOrderItem->unit_price)->toBe('12.00')
        ->and($firstOrderItem->total_price)->toBe('12.00')
        ->and(KitchenTicket::query()->where('table_session_id', $tableSession->id)->count())->toBe(1);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt64publictoken',
    ])
        ->assertSet('draftStatusValue', DraftOrderStatus::ConvertedToOrder->value)
        ->assertSet('confirmedOrdersTotalAmount', '12.00')
        ->assertSet('tableTotalAmount', '12.00');

    $secondItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $pasta,
        selectedModifierOptions: [],
        comment: 'Second round',
    );
    $secondDraft = $secondItem->draftOrder()->firstOrFail();
    $draftOrders = DraftOrder::query()
        ->where('table_session_id', $tableSession->id)
        ->orderBy('id')
        ->get();

    expect($secondDraft->id)->not->toBe($firstDraft->id)
        ->and($secondDraft->status)->toBe(DraftOrderStatus::Draft)
        ->and($draftOrders)->toHaveCount(2)
        ->and($draftOrders->last()->id)->toBe($secondDraft->id);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt64publictoken',
    ])
        ->assertSet('draftStatusValue', DraftOrderStatus::Draft->value)
        ->assertSet('totalAmount', '5.00')
        ->assertSet('confirmedOrdersTotalAmount', '12.00')
        ->assertSet('tableTotalAmount', '17.00')
        ->assertSee('Итого за стол');

    app(SendDraftOrderToWaiterAction::class)->handle($secondDraft, $guest);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('table.draft.id', $secondDraft->id)
        ->assertSet('table.current_draft_total', '5.00 EUR')
        ->assertSet('table.confirmed_orders_total', '12.00 EUR')
        ->assertSet('table.total', '17.00 EUR')
        ->assertSee('Confirmed orders')
        ->assertSee('Current draft total');

    $secondOrder = app(ConfirmDraftOrderByWaiterAction::class)->handle($secondDraft, $waiter);
    app(SendOrderToKitchenBarAction::class)->handle($secondOrder, $waiter);

    $orders = Order::query()
        ->where('table_session_id', $tableSession->id)
        ->orderBy('id')
        ->get();

    expect($orders)->toHaveCount(2)
        ->and($orders->pluck('total_price')->values()->all())->toBe(['12.00', '5.00'])
        ->and($orders->pluck('status')->values()->all())->toBe([
            OrderStatus::SentToKitchenBar,
            OrderStatus::SentToKitchenBar,
        ])
        ->and(KitchenTicket::query()->where('table_session_id', $tableSession->id)->count())->toBe(2)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Cooking);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt64publictoken',
    ])
        ->assertSet('draftStatusValue', DraftOrderStatus::ConvertedToOrder->value)
        ->assertSet('confirmedOrdersTotalAmount', '17.00')
        ->assertSet('tableTotalAmount', '17.00');
});

function createPrompt64RepeatOrderScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 64 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 64 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 64 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 64 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 64 Table',
            'status' => ServicePointStatus::Occupied,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'guest_token' => str_repeat('a', 64),
            'status' => TableSessionGuestStatus::Active,
        ]);
    $kitchen = KitchenDepartment::factory()
        ->for($branch)
        ->create([
            'type' => KitchenDepartmentType::Kitchen,
            'name' => 'Prompt 64 Kitchen',
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 64 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Prompt 64 Main']);
    $pizza = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($kitchen, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 64 Pizza',
            'price' => '12.00',
        ]);
    $pasta = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->for($kitchen, 'kitchenDepartment')
        ->create([
            'name' => 'Prompt 64 Pasta',
            'price' => '5.00',
        ]);

    return [$organization, $servicePoint, $tableSession, $guest, $pizza, $pasta];
}

function attachPrompt64Waiter(User $user, Organization $organization): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    foreach ([SystemPermission::ViewOrders, SystemPermission::ConfirmOrders, SystemPermission::SendToKitchen] as $permission) {
        $permissionModel = Permission::query()
            ->where('code', $permission->value)
            ->firstOrFail();

        $role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
    }

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $role;
}
