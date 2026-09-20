<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\MenuStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\OrganizationUser;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;

final class PreparationWorkflowFixture
{
    /** @return array<string, mixed> */
    public static function create(bool $confirm = true): array
    {
        $owner = User::factory()->create(['email' => 'preparation.owner@example.test']);
        $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Preparation group']);
        $brand = Brand::factory()->for($organization)->create();
        $branch = Branch::factory()->for($organization)->for($brand)->withDefaultSettings()->create(['name' => 'Preparation restaurant']);
        $point = ServicePoint::factory()->for($branch)->create(['name' => 'Preparation table', 'display_number' => 'T-10']);
        $session = TableSession::factory()->forServicePoint($point)->active()->create();
        $guest = TableSessionGuest::factory()->for($session)->active()->create(['guest_name' => 'Test guest']);
        $actors = [];
        foreach (['cook' => SystemRole::Cook, 'bar' => SystemRole::Bartender, 'chef' => SystemRole::HeadChef, 'waiter' => SystemRole::Waiter] as $name => $role) {
            $actor = User::factory()->create(['email' => 'preparation.'.$name.'@example.test']);
            OrganizationUser::factory()->forOrganization($organization)->forUser($actor)->forSystemRole($role)->active()->create();
            $actors[$name] = $actor;
        }
        $kitchen = KitchenDepartment::factory()->for($branch)->forType(KitchenDepartmentType::Kitchen)->active()->create(['name' => 'Preparation kitchen']);
        $bar = KitchenDepartment::factory()->for($branch)->forType(KitchenDepartmentType::Bar)->active()->create(['name' => 'Preparation bar']);
        $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
        $category = MenuCategory::factory()->for($menu)->create();
        $draft = DraftOrder::factory()->for($session)->create(['status' => DraftOrderStatus::SentToWaiter, 'sent_to_waiter_at' => now(), 'sent_by_guest_id' => $guest->id]);
        $group = ModifierGroup::factory()->for($branch)->create(['name' => 'Preparation addition']);
        $option = ModifierOption::factory()->for($group, 'modifierGroup')->available()->create(['name' => 'Extra herbs', 'price_delta_cents' => 0]);
        foreach (['Soup' => $kitchen, 'Pasta' => $kitchen, 'Coffee' => $bar] as $name => $department) {
            $menuItem = MenuItem::factory()->for($menu)->for($category, 'category')->for($department, 'kitchenDepartment')
                ->create(['name' => $name, 'price_cents' => 700, 'allergens' => ['milk']]);
            $variant = MenuItemVariant::factory()->for($menuItem, 'item')->portion()->default()->create(['name' => 'Large portion', 'price_cents' => 700]);
            $menuItem->modifierGroups()->attach($group);
            DraftOrderItem::factory()->for($draft)->for($guest, 'guest')->for($menuItem, 'menuItem')->create([
                'item_name' => $name, 'menu_item_variant_id' => $variant->id, 'variant_name' => 'Large portion',
                'quantity' => 2, 'unit_price_cents' => 700, 'modifier_total_cents' => 0, 'total_price_cents' => 1400,
                'comment' => 'Keep the guest instruction exactly',
                'selected_modifiers' => [['group_id' => $group->id, 'option_id' => $option->id, 'group_name' => $group->name, 'option_name' => $option->name, 'price_delta_cents' => 0]],
            ]);
        }
        $order = $confirm ? app(ConfirmDraftOrderByWaiterAction::class)->handle($draft, $actors['waiter']) : null;
        $items = $order === null ? collect() : KitchenTicketItem::query()->with('kitchenTicket')->whereHas('kitchenTicket', fn ($query) => $query->where('order_id', $order->id))->orderBy('id')->get();

        return compact('owner', 'organization', 'branch', 'point', 'session', 'guest', 'actors', 'kitchen', 'bar', 'draft', 'order', 'items');
    }
}
