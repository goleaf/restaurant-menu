<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('order schema stores real order links and item snapshots', function () {
    expect(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasColumns('orders', [
            'branch_id',
            'service_point_id',
            'table_session_id',
            'draft_order_id',
            'status',
            'confirmed_by_user_id',
            'confirmed_at',
            'total_price_cents',
            'currency',
            'metadata',
        ]))->toBeTrue()
        ->and(Schema::hasTable('order_items'))->toBeTrue()
        ->and(Schema::hasColumns('order_items', [
            'order_id',
            'table_session_guest_id',
            'menu_item_id',
            'menu_item_variant_id',
            'original_menu_item_id',
            'kitchen_department_id',
            'kitchen_department_type',
            'kitchen_department_name',
            'guest_name',
            'guest_name_snapshot',
            'item_name',
            'item_name_snapshot',
            'item_description_snapshot',
            'variant_name',
            'variant_type',
            'quantity',
            'unit_price_cents',
            'unit_price_snapshot_cents',
            'modifier_total_cents',
            'total_price_cents',
            'selected_modifiers',
            'modifiers_snapshot',
            'tax_snapshot',
            'service_snapshot',
            'comment',
            'cancelled_at',
            'cancelled_by_user_id',
            'cancellation_reason',
        ]))->toBeTrue();

    $orderItemIndexes = collect(Schema::getIndexes('order_items'));
    $orderItemForeignKeys = collect(Schema::getForeignKeys('order_items'));

    expect($orderItemIndexes->contains(
        fn (array $index): bool => $index['columns'] === ['order_id', 'cancelled_at'],
    ))->toBeTrue()
        ->and($orderItemForeignKeys->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['cancelled_by_user_id']
                && $foreignKey['foreign_table'] === 'users'
                && mb_strtolower((string) $foreignKey['on_delete']) === 'set null',
        ))->toBeTrue()
        ->and($orderItemForeignKeys->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['menu_item_variant_id']
                && $foreignKey['foreign_table'] === 'menu_item_variants'
                && mb_strtolower((string) $foreignKey['on_delete']) === 'set null',
        ))->toBeTrue();
});

test('order status enum contains the prepared order lifecycle', function () {
    expect(OrderStatus::values())->toBe([
        'confirmed_by_waiter',
        'sent_to_kitchen_bar',
        'in_progress',
        'ready',
        'served',
        'payment_requested',
        'paid',
        'closed',
        'cancelled',
    ])->and(OrderStatus::options())->toHaveKey('confirmed_by_waiter', 'Confirmed by waiter')
        ->and(OrderStatus::SentToKitchenBar->label())->toBe('Sent to kitchen/bar');
});

test('confirming draft creates immutable order item snapshots', function () {
    [$organization, $servicePoint, $draftOrder, $menuItem, $modifierGroup, $modifierOption, $guest] = createPrompt56SentDraftScenario();
    $waiter = User::factory()->create();

    attachPrompt56Staff($waiter, $organization, [SystemPermission::ViewOrders, SystemPermission::ConfirmOrders]);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);

    $orderItem = $order->items()
        ->with(['guest:id,guest_name', 'menuItem:id,name,price'])
        ->firstOrFail();

    expect($order->status)->toBe(OrderStatus::ConfirmedByWaiter)
        ->and($order->branch_id)->toBe($servicePoint->branch_id)
        ->and($order->service_point_id)->toBe($servicePoint->id)
        ->and($order->table_session_id)->toBe($draftOrder->table_session_id)
        ->and($order->draft_order_id)->toBe($draftOrder->id)
        ->and($order->total_price_cents)->toBe(1700)
        ->and($order->currency)->toBe('EUR')
        ->and($orderItem->original_menu_item_id)->toBe($menuItem->id)
        ->and($orderItem->guest_name)->toBe('Ana')
        ->and($orderItem->guest_name_snapshot)->toBe('Ana')
        ->and($orderItem->item_name)->toBe('Original Steak')
        ->and($orderItem->item_name_snapshot)->toBe('Original Steak')
        ->and($orderItem->item_description_snapshot)->toBe('Original menu description')
        ->and($orderItem->quantity)->toBe(2)
        ->and($orderItem->unit_price_cents)->toBe(750)
        ->and($orderItem->unit_price_snapshot_cents)->toBe(750)
        ->and($orderItem->modifier_total_cents)->toBe(100)
        ->and($orderItem->total_price_cents)->toBe(1700)
        ->and($orderItem->selected_modifiers)->toBe([
            [
                'group_name' => 'Sauce',
                'option_name' => 'Pepper sauce',
                'price_delta_cents' => 100,
            ],
        ])
        ->and($orderItem->modifiers_snapshot)->toBe([
            [
                'group_name' => 'Sauce',
                'option_name' => 'Pepper sauce',
                'price_delta_cents' => 100,
            ],
        ])
        ->and($orderItem->tax_snapshot)->toBe([])
        ->and($orderItem->service_snapshot)->toBe([])
        ->and($orderItem->comment)->toBe('Medium rare')
        ->and($orderItem->guest?->guest_name)->toBe('Ana')
        ->and($orderItem->menuItem?->name)->toBe('Original Steak')
        ->and($draftOrder->fresh()->status)->toBe(DraftOrderStatus::ConvertedToOrder)
        ->and($servicePoint->fresh()->orders()->whereKey($order->id)->exists())->toBeTrue();

    $menuItem->update([
        'name' => 'Renamed Steak',
        'description' => 'Renamed menu description',
        'price_cents' => 9900,
    ]);
    $guest->update(['guest_name' => 'Renamed Ana']);
    $modifierGroup->update(['name' => 'Premium sauce']);
    $modifierOption->update([
        'name' => 'Truffle sauce',
        'price_delta_cents' => 800,
    ]);

    $orderItem = $orderItem->fresh();

    expect($orderItem->item_name)->toBe('Original Steak')
        ->and($orderItem->item_name_snapshot)->toBe('Original Steak')
        ->and($orderItem->item_description_snapshot)->toBe('Original menu description')
        ->and($orderItem->guest_name_snapshot)->toBe('Ana')
        ->and($orderItem->unit_price_cents)->toBe(750)
        ->and($orderItem->unit_price_snapshot_cents)->toBe(750)
        ->and($orderItem->modifier_total_cents)->toBe(100)
        ->and($orderItem->total_price_cents)->toBe(1700)
        ->and($orderItem->selected_modifiers)->toBe([
            [
                'group_name' => 'Sauce',
                'option_name' => 'Pepper sauce',
                'price_delta_cents' => 100,
            ],
        ])
        ->and($orderItem->modifiers_snapshot)->toBe([
            [
                'group_name' => 'Sauce',
                'option_name' => 'Pepper sauce',
                'price_delta_cents' => 100,
            ],
        ])
        ->and($orderItem->historicalGuestName())->toBe('Ana')
        ->and($orderItem->historicalItemName())->toBe('Original Steak')
        ->and($orderItem->historicalModifiers())->toBe([
            [
                'group_name' => 'Sauce',
                'option_name' => 'Pepper sauce',
                'price_delta_cents' => 100,
            ],
        ])
        ->and($order->fresh()->items()->count())->toBe(1);
});

function createPrompt56SentDraftScenario(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 56 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 56 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 56 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()->for($branch)->create(['name' => 'Prompt 56 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 56 Table',
            'status' => ServicePointStatus::HasNewOrder,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create(['status' => TableSessionStatus::Active]);
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 56 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Main']);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Original Steak',
            'description' => 'Original menu description',
            'price_cents' => 750,
        ]);
    $modifierGroup = ModifierGroup::factory()
        ->for($branch)
        ->create(['name' => 'Sauce']);
    $modifierOption = ModifierOption::factory()
        ->for($modifierGroup, 'modifierGroup')
        ->create([
            'name' => 'Pepper sauce',
            'price_delta_cents' => 100,
        ]);

    $menuItem->modifierGroups()->sync([$modifierGroup->id]);

    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create([
            'status' => DraftOrderStatus::SentToWaiter,
            'sent_to_waiter_at' => now(),
            'sent_by_guest_id' => $guest->id,
        ]);

    DraftOrderItem::factory()
        ->for($draftOrder, 'draftOrder')
        ->for($guest, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'item_name' => 'Original Steak',
            'quantity' => 2,
            'unit_price_cents' => 750,
            'modifier_total_cents' => 100,
            'total_price_cents' => 1700,
            'selected_modifiers' => [
                [
                    'group_name' => 'Sauce',
                    'option_name' => 'Pepper sauce',
                    'price_delta_cents' => 100,
                ],
            ],
            'comment' => 'Medium rare',
        ]);

    return [$organization, $servicePoint, $draftOrder, $menuItem, $modifierGroup, $modifierOption, $guest];
}

/**
 * @param  list<SystemPermission>  $permissions
 */
function attachPrompt56Staff(User $user, Organization $organization, array $permissions): Role
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
