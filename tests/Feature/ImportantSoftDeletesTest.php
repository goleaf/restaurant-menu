<?php

use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

test('important entity tables expose soft delete columns', function () {
    foreach (prompt87ImportantSoftDeleteTables() as $tableName) {
        expect(Schema::hasColumn($tableName, 'deleted_at'))->toBeTrue($tableName.' should have deleted_at');
    }
});

test('important entity models use soft deletes and disappear from normal queries', function () {
    $context = createPrompt87ImportantEntityContext();

    foreach ($context as $model) {
        expect(class_uses_recursive($model::class))->toHaveKey(SoftDeletes::class);

        $model->delete();

        $this->assertSoftDeleted($model);

        expect($model::query()->whereKey($model->getKey())->exists())->toBeFalse($model::class.' should be hidden');
        expect($model::withTrashed()->whereKey($model->getKey())->firstOrFail()->trashed())->toBeTrue();
    }
});

test('old order snapshots survive soft deleted menu and venue records', function () {
    $context = createPrompt87ImportantEntityContext();
    $organization = $context['organization'];
    $brand = $context['brand'];
    $branch = $context['branch'];
    $areaNode = $context['areaNode'];
    $servicePoint = $context['servicePoint'];
    $menu = $context['menu'];
    $category = $context['category'];
    $menuItem = $context['menuItem'];

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create();
    $order = Order::factory()
        ->for($tableSession)
        ->for($draftOrder, 'draftOrder')
        ->create([
            'branch_id' => $branch->id,
            'service_point_id' => $servicePoint->id,
            'status' => OrderStatus::ConfirmedByWaiter,
            'total_price_cents' => 1850,
            'currency' => 'EUR',
        ]);
    $orderItem = OrderItem::factory()
        ->for($order)
        ->for($guest, 'guest')
        ->for($menuItem, 'menuItem')
        ->create([
            'guest_name' => 'Ana',
            'item_name' => 'Archived Steak',
            'quantity' => 2,
            'unit_price_cents' => 800,
            'modifier_total_cents' => 125,
            'total_price_cents' => 1850,
            'selected_modifiers' => [
                [
                    'group_name' => 'Sauce',
                    'option_name' => 'Pepper',
                    'price_delta_cents' => 125,
                ],
            ],
            'comment' => 'No onion',
        ]);

    foreach ([$menuItem, $category, $menu, $servicePoint, $areaNode, $branch, $brand, $organization] as $model) {
        $model->delete();
    }

    $reloadedOrder = Order::query()
        ->with([
            'branch.organization',
            'branch.brand',
            'servicePoint',
            'items.menuItem',
        ])
        ->whereKey($order->id)
        ->firstOrFail();
    $reloadedItem = $reloadedOrder->items->first();

    expect($reloadedOrder->branch?->trashed())->toBeTrue()
        ->and($reloadedOrder->branch?->organization?->trashed())->toBeTrue()
        ->and($reloadedOrder->branch?->brand?->trashed())->toBeTrue()
        ->and($reloadedOrder->servicePoint?->trashed())->toBeTrue()
        ->and($reloadedItem?->menuItem?->trashed())->toBeTrue()
        ->and($reloadedItem?->id)->toBe($orderItem->id)
        ->and($reloadedItem?->item_name)->toBe('Archived Steak')
        ->and($reloadedItem?->unit_price_cents)->toBe(800)
        ->and($reloadedItem?->modifier_total_cents)->toBe(125)
        ->and($reloadedItem?->total_price_cents)->toBe(1850)
        ->and($reloadedItem?->selected_modifiers)->toBe([
            [
                'group_name' => 'Sauce',
                'option_name' => 'Pepper',
                'price_delta_cents' => 125,
            ],
        ])
        ->and($reloadedItem?->comment)->toBe('No onion')
        ->and(MenuItem::query()->whereKey($menuItem->id)->exists())->toBeFalse()
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue()
        ->and(OrderItem::query()->whereKey($orderItem->id)->exists())->toBeTrue();
});

/**
 * @return list<string>
 */
function prompt87ImportantSoftDeleteTables(): array
{
    return [
        'organizations',
        'brands',
        'branches',
        'area_nodes',
        'service_points',
        'menus',
        'menu_categories',
        'menu_items',
    ];
}

/**
 * @return array{organization: Organization, brand: Brand, branch: Branch, areaNode: AreaNode, servicePoint: ServicePoint, menu: Menu, category: MenuCategory, menuItem: MenuItem}
 */
function createPrompt87ImportantEntityContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 87 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 87 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 87 Branch',
            'currency' => 'EUR',
        ]);
    $areaNode = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Prompt 87 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($areaNode)
        ->create([
            'name' => 'Prompt 87 Table',
            'status' => ServicePointStatus::Free,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 87 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['name' => 'Prompt 87 Category']);
    $menuItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Prompt 87 Dish',
            'price_cents' => 800,
        ]);

    return [
        'menuItem' => $menuItem,
        'category' => $category,
        'menu' => $menu,
        'servicePoint' => $servicePoint,
        'areaNode' => $areaNode,
        'branch' => $branch,
        'brand' => $brand,
        'organization' => $organization,
    ];
}
