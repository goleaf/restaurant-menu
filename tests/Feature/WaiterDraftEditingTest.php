<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\AddDraftOrderItemByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Livewire\Waiter\TableDetail;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('waiter with confirm orders can update quantity comment and modifiers before confirming', function () {
    [
        'organization' => $organization,
        'tableSession' => $tableSession,
        'draftOrder' => $draftOrder,
        'ana' => $ana,
        'pizzaDraftItem' => $pizzaDraftItem,
        'sizeGroup' => $sizeGroup,
        'largeOption' => $largeOption,
    ] = createPrompt55SentDraftScenario();
    $waiter = User::factory()->create(['name' => 'Prompt 55 Confirm Waiter']);
    attachPrompt55Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSee('Edit draft')
        ->call('editDraftItem', $pizzaDraftItem->id)
        ->assertSet('editingItemName', 'Pizza Margherita')
        ->set('editingQuantity', 2)
        ->set('editingComment', 'No onion, cut in slices')
        ->set('editingModifierOptions.'.(string) $sizeGroup->id, [$largeOption->id])
        ->call('updateDraftItem')
        ->assertHasNoErrors()
        ->assertSee('Waiter review')
        ->assertSee('25.00 EUR');

    $pizzaDraftItem = $pizzaDraftItem->fresh();

    expect($pizzaDraftItem->quantity)->toBe(2)
        ->and($pizzaDraftItem->modifier_total)->toBe('2.50')
        ->and($pizzaDraftItem->total_price)->toBe('25.00')
        ->and($pizzaDraftItem->comment)->toBe('No onion, cut in slices')
        ->and($pizzaDraftItem->selected_modifiers[0]['option_name'])->toBe('Large')
        ->and($draftOrder->fresh()->status)->toBe(DraftOrderStatus::WaiterReview);

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $ana->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt55publictoken',
    ])
        ->assertSet('totalAmount', '25.00')
        ->assertSee('Large')
        ->assertSee('No onion, cut in slices');

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSee('Waiter review')
        ->assertSee('25.00 EUR');

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->call('confirmDraft')
        ->assertHasNoErrors()
        ->assertSee('Order #');

    $order = Order::query()
        ->with(['items'])
        ->where('draft_order_id', $draftOrder->id)
        ->firstOrFail();

    expect($order->total_price)->toBe('25.00')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->total_price)->toBe('25.00')
        ->and($order->items->first()->comment)->toBe('No onion, cut in slices')
        ->and($order->items->first()->selected_modifiers[0]['option_name'])->toBe('Large');
});

test('waiter can add and delete draft positions before confirmation', function () {
    [
        'organization' => $organization,
        'tableSession' => $tableSession,
        'draftOrder' => $draftOrder,
        'ana' => $ana,
        'zara' => $zara,
        'waterItem' => $waterItem,
        'pizzaDraftItem' => $pizzaDraftItem,
    ] = createPrompt55SentDraftScenario();
    $waiter = User::factory()->create();
    attachPrompt55Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->set('addingGuestId', (string) $zara->id)
        ->set('addingMenuItemId', (string) $waterItem->id)
        ->set('addingQuantity', 3)
        ->set('addingComment', 'Still water')
        ->call('addDraftItem')
        ->assertHasNoErrors()
        ->assertSee('22.00 EUR')
        ->call('deleteDraftItem', $pizzaDraftItem->id)
        ->assertHasNoErrors()
        ->assertSee('12.00 EUR');

    expect(DraftOrderItem::query()->whereKey($pizzaDraftItem->id)->exists())->toBeFalse()
        ->and($draftOrder->fresh()->status)->toBe(DraftOrderStatus::WaiterReview);

    $waterDraftItem = DraftOrderItem::query()
        ->where('draft_order_id', $draftOrder->id)
        ->where('menu_item_id', $waterItem->id)
        ->firstOrFail();

    expect($waterDraftItem->table_session_guest_id)->toBe($zara->id)
        ->and($waterDraftItem->quantity)->toBe(3)
        ->and($waterDraftItem->total_price)->toBe('12.00')
        ->and($waterDraftItem->comment)->toBe('Still water');

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $ana->id,
        'currency' => 'EUR',
        'publicToken' => 'prompt55publictoken',
    ])
        ->assertSet('totalAmount', '12.00')
        ->assertSee('Still Water')
        ->assertDontSee('Pizza Margherita');
});

test('waiter cannot add draft item from menu outside current schedule', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-01 15:30:00', 'Europe/Vilnius'));

    [
        'organization' => $organization,
        'tableSession' => $tableSession,
        'draftOrder' => $draftOrder,
        'zara' => $zara,
        'waterItem' => $waterItem,
    ] = createPrompt55SentDraftScenario();
    $waiter = User::factory()->create();
    attachPrompt55Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    MenuAvailabilitySchedule::factory()
        ->for($waterItem->menu)
        ->create([
            'day_of_week' => 1,
            'starts_at' => '08:00',
            'ends_at' => '12:00',
        ]);

    Livewire::actingAs($waiter)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSet('addableMenuItems', []);

    expect(fn () => app(AddDraftOrderItemByWaiterAction::class)->handle(
        draftOrder: $draftOrder,
        guest: $zara,
        menuItem: $waterItem,
        editedBy: $waiter,
        quantity: 1,
        selectedModifierOptions: [],
    ))->toThrow(ValidationException::class, 'Будет доступно с Пн 08:00');

    expect(DraftOrderItem::query()
        ->where('draft_order_id', $draftOrder->id)
        ->where('menu_item_id', $waterItem->id)
        ->exists())->toBeFalse();
});

test('edit pending orders permission can edit sent draft without confirming it', function () {
    [
        'organization' => $organization,
        'tableSession' => $tableSession,
        'draftOrder' => $draftOrder,
        'pizzaDraftItem' => $pizzaDraftItem,
    ] = createPrompt55SentDraftScenario();
    $staff = User::factory()->create();
    attachPrompt55Staff($staff, $organization, [SystemPermission::ViewOrders, SystemPermission::EditPendingOrders]);

    Livewire::actingAs($staff)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertSee('Edit draft')
        ->assertDontSee('Confirm order')
        ->call('editDraftItem', $pizzaDraftItem->id)
        ->set('editingQuantity', 2)
        ->call('updateDraftItem')
        ->assertHasNoErrors()
        ->assertSee('20.00 EUR')
        ->call('confirmDraft')
        ->assertHasErrors('draft_review');

    expect($pizzaDraftItem->fresh()->total_price)->toBe('20.00')
        ->and($draftOrder->fresh()->status)->toBe(DraftOrderStatus::WaiterReview)
        ->and(Order::query()->count())->toBe(0);
});

test('user with view orders only cannot edit sent draft', function () {
    [
        'organization' => $organization,
        'tableSession' => $tableSession,
        'draftOrder' => $draftOrder,
        'zara' => $zara,
        'waterItem' => $waterItem,
        'pizzaDraftItem' => $pizzaDraftItem,
    ] = createPrompt55SentDraftScenario();
    $viewer = User::factory()->create();
    attachPrompt55Staff($viewer, $organization, [SystemPermission::ViewOrders]);

    Livewire::actingAs($viewer)
        ->test(TableDetail::class, ['tableSession' => $tableSession])
        ->assertDontSee('Edit draft')
        ->call('editDraftItem', $pizzaDraftItem->id)
        ->assertHasErrors('draft_edit')
        ->set('addingGuestId', (string) $zara->id)
        ->set('addingMenuItemId', (string) $waterItem->id)
        ->call('addDraftItem')
        ->assertHasErrors('draft_edit')
        ->call('deleteDraftItem', $pizzaDraftItem->id)
        ->assertHasErrors('draft_edit');

    expect($pizzaDraftItem->fresh())->toBeInstanceOf(DraftOrderItem::class)
        ->and($draftOrder->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter);
});

/**
 * @return array<string, mixed>
 */
function createPrompt55SentDraftScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 55 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 55 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 55 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 55 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 55 Table',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);

    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 55 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Mains',
            'is_active' => true,
        ]);
    $pizzaItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Pizza Margherita',
            'price' => '10.00',
            'is_available' => true,
        ]);
    $waterItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Still Water',
            'price' => '4.00',
            'is_available' => true,
        ]);
    $sizeGroup = ModifierGroup::factory()
        ->for($branch)
        ->create([
            'name' => 'Size',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);
    $smallOption = ModifierOption::factory()
        ->for($sizeGroup)
        ->create([
            'name' => 'Small',
            'price_delta' => '0.00',
            'is_available' => true,
        ]);
    $largeOption = ModifierOption::factory()
        ->for($sizeGroup)
        ->create([
            'name' => 'Large',
            'price_delta' => '2.50',
            'is_available' => true,
        ]);
    $pizzaItem->modifierGroups()->attach($sizeGroup->id);

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $ana->id,
        ]);

    $pizzaDraftItem = DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($ana, 'guest')
        ->for($pizzaItem, 'menuItem')
        ->create([
            'item_name' => 'Pizza Margherita',
            'quantity' => 1,
            'unit_price' => '10.00',
            'modifier_total' => '0.00',
            'total_price' => '10.00',
            'selected_modifiers' => [
                [
                    'group_id' => $sizeGroup->id,
                    'group_name' => 'Size',
                    'option_id' => $smallOption->id,
                    'option_name' => 'Small',
                    'price_delta' => '0.00',
                ],
            ],
            'comment' => 'No garlic',
        ]);

    return [
        'organization' => $organization,
        'branch' => $branch,
        'servicePoint' => $servicePoint,
        'tableSession' => $tableSession,
        'draftOrder' => $draftOrder,
        'ana' => $ana,
        'zara' => $zara,
        'pizzaItem' => $pizzaItem,
        'waterItem' => $waterItem,
        'sizeGroup' => $sizeGroup,
        'smallOption' => $smallOption,
        'largeOption' => $largeOption,
        'pizzaDraftItem' => $pizzaDraftItem,
    ];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt55Staff(User $user, Organization $organization, array $permissions): Role
{
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    foreach ($permissions as $permission) {
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
