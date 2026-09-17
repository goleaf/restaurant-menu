<?php

declare(strict_types=1);

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\DraftOrders\UpdateGuestDraftOrderItemAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Waiter\AddManualWaiterOrderItemAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\UpdateDraftOrderItemByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Notification::fake();
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Availability integration']);
    $this->branch = Branch::factory()->for($this->organization)->create(['timezone' => 'UTC']);
    BranchSetting::factory()->for($this->branch)->create();
    $this->point = ServicePoint::factory()->for($this->branch)->create(['is_active' => true]);
    $this->session = TableSession::factory()->forServicePoint($this->point)->waiterOpened()->active()->create();
    $this->guest = TableSessionGuest::factory()->for($this->session)->active()->create();
    $this->menu = Menu::factory()->for($this->branch)->create(['status' => MenuStatus::Active]);
    $this->category = MenuCategory::factory()->for($this->menu)->create(['is_active' => true]);
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['is_available' => true, 'price_cents' => 1000]);
});

test('new manual waiter additions enforce the same restaurant and dish stops as guest additions', function (string $stop): void {
    if ($stop === 'restaurant') {
        $this->branch->update(['is_temporarily_closed' => true]);
    } else {
        $this->item->update(['hidden_until' => now()->addHour()]);
    }
    expect(fn () => app(AddManualWaiterOrderItemAction::class)->handle($this->session, $this->actor, $this->guest, null, $this->item, 1, []))->toThrow(ValidationException::class);
    expect(DraftOrderItem::query()->count())->toBe(0);
})->with(['restaurant', 'hidden dish']);

test('increasing an existing draft line rechecks restaurant availability for guests and waiters', function (string $role): void {
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, []);
    if ($role === 'waiter') {
        app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest);
    }
    $this->branch->update(['is_temporarily_closed' => true]);
    $action = $role === 'guest' ? app(UpdateGuestDraftOrderItemAction::class) : app(UpdateDraftOrderItemByWaiterAction::class);
    expect(fn () => $action->handle($line, $role === 'guest' ? $this->guest : $this->actor, 2, []))->toThrow(ValidationException::class);
    expect($line->fresh()->quantity)->toBe(1);
})->with(['guest', 'waiter']);

test('reducing a stopped draft preserves selection snapshots and prices', function (string $role): void {
    $group = ModifierGroup::factory()->for($this->branch)->required()->create();
    $option = ModifierOption::factory()->for($group, 'modifierGroup')->available()->create(['price_delta_cents' => 100]);
    $this->item->modifierGroups()->attach($group);
    $selection = [(string) $group->id => [$option->id]];
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, $selection);
    $line = app(UpdateGuestDraftOrderItemAction::class)->handle($line, $this->guest, 3, $selection);
    if ($role === 'waiter') {
        app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest);
    }
    $snapshots = $line->selected_modifiers;
    $this->branch->update(['is_temporarily_closed' => true]);
    $this->item->update(['is_available' => false, 'price_cents' => 2000]);
    $option->update(['is_available' => false, 'price_delta_cents' => 300]);
    $action = $role === 'guest' ? app(UpdateGuestDraftOrderItemAction::class) : app(UpdateDraftOrderItemByWaiterAction::class);
    $updated = $action->handle($line, $role === 'guest' ? $this->guest : $this->actor, 2, $selection);
    expect($updated->quantity)->toBe(2)->and($updated->unit_price_cents)->toBe(1000)
        ->and($updated->total_price_cents)->toBe(2200)->and($updated->selected_modifiers)->toBe($snapshots);
})->with(['guest', 'waiter']);

test('increases revalidate the currently retained variant rather than only changed variants', function (string $role): void {
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->available()->create();
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, [], $variant->id);
    if ($role === 'waiter') {
        app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest);
    }
    MenuItemVariant::factory()->for($this->item, 'item')->available()->create();
    $variant->update(['is_available' => false]);
    $action = $role === 'guest' ? app(UpdateGuestDraftOrderItemAction::class) : app(UpdateDraftOrderItemByWaiterAction::class);
    expect(fn () => $action->handle($line, $role === 'guest' ? $this->guest : $this->actor, 2, [], $variant->id))->toThrow(ValidationException::class);
    expect($line->fresh()->quantity)->toBe(1);
})->with(['guest', 'waiter']);

test('submission revalidates selected variants and modifier options without changing a draft', function (string $selectionType): void {
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->available()->create();
    $group = ModifierGroup::factory()->for($this->branch)->required()->create();
    $option = ModifierOption::factory()->for($group, 'modifierGroup')->available()->create();
    $this->item->modifierGroups()->attach($group);
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, [(string) $group->id => [$option->id]], $variant->id);
    MenuItemVariant::factory()->for($this->item, 'item')->available()->create();
    ModifierOption::factory()->for($group, 'modifierGroup')->available()->create();
    ($selectionType === 'variant' ? $variant : $option)->update(['is_available' => false]);
    expect(fn () => app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest))->toThrow(ValidationException::class);
    expect($line->draftOrder->fresh()->status)->toBe(DraftOrderStatus::Draft)->and(Order::query()->count())->toBe(0);
})->with(['variant', 'modifier']);

test('confirmation revalidates sent but unaccepted orders without cancelling them', function (string $stop): void {
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->available()->create();
    $group = ModifierGroup::factory()->for($this->branch)->required()->create();
    $option = ModifierOption::factory()->for($group, 'modifierGroup')->available()->create();
    $this->item->modifierGroups()->attach($group);
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, [(string) $group->id => [$option->id]], $variant->id);
    $draft = app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest);
    MenuItemVariant::factory()->for($this->item, 'item')->available()->create();
    ModifierOption::factory()->for($group, 'modifierGroup')->available()->create();
    match ($stop) {
        'restaurant' => $this->branch->update(['is_temporarily_closed' => true]),
        'dish' => $this->item->update(['is_available' => false]),
        'variant' => $variant->update(['is_available' => false]),
        'modifier' => $option->update(['is_available' => false]),
    };
    expect(fn () => app(ConfirmDraftOrderByWaiterAction::class)->handle($draft, $this->actor))->toThrow(ValidationException::class);
    expect($draft->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter)->and(Order::query()->count())->toBe(0)->and($line->fresh())->not->toBeNull();
})->with(['restaurant', 'dish', 'variant', 'modifier']);

test('accepted order replay retains historical selection and does not require a currently sellable dish', function (): void {
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, []);
    $draft = app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest);
    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draft, $this->actor);
    $this->branch->update(['is_temporarily_closed' => true]);
    $this->item->update(['is_available' => false, 'price_cents' => 2000]);
    $replayed = app(ConfirmDraftOrderByWaiterAction::class)->handle($draft, $this->actor);
    expect($replayed->id)->toBe($order->id)->and(Order::query()->count())->toBe(1)->and($order->items()->sole()->unit_price_cents)->toBe(1000);
});

test('removing an unavailable unaccepted dish remains permitted', function (): void {
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, []);
    $this->branch->update(['is_temporarily_closed' => true]);
    $this->item->update(['is_available' => false]);
    app(DeleteGuestDraftOrderItemAction::class)->handle($line, $this->guest);
    expect($line->fresh())->toBeNull();
});

test('reducing quantity cannot smuggle a new selection through a restaurant pause', function (string $role): void {
    $group = ModifierGroup::factory()->for($this->branch)->required()->create();
    $first = ModifierOption::factory()->for($group, 'modifierGroup')->available()->create();
    $other = ModifierOption::factory()->for($group, 'modifierGroup')->available()->create();
    $this->item->modifierGroups()->attach($group);
    $selection = [(string) $group->id => [$first->id]];
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, $selection);
    $line = app(UpdateGuestDraftOrderItemAction::class)->handle($line, $this->guest, 3, $selection);
    if ($role === 'waiter') {
        app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest);
    }
    $this->branch->update(['is_temporarily_closed' => true]);
    $action = $role === 'guest' ? app(UpdateGuestDraftOrderItemAction::class) : app(UpdateDraftOrderItemByWaiterAction::class);
    expect(fn () => $action->handle($line, $role === 'guest' ? $this->guest : $this->actor, 2, [(string) $group->id => [$other->id]]))->toThrow(ValidationException::class);
    expect($line->fresh()->quantity)->toBe(3)->and($line->fresh()->selected_modifiers)->toBe($line->selected_modifiers);
})->with(['guest', 'waiter']);

test('replaying an already recorded addition after a pause does not create a new obligation', function (string $role): void {
    $key = (string) Str::uuid();
    $add = $role === 'guest'
        ? fn () => app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, [], idempotencyKey: $key)
        : fn () => app(AddManualWaiterOrderItemAction::class)->handle($this->session, $this->actor, $this->guest, null, $this->item, 1, [], idempotencyKey: $key);
    $line = $add();
    $this->branch->update(['is_temporarily_closed' => true]);
    $this->item->update(['is_available' => false]);
    expect($add()->id)->toBe($line->id)->and(DraftOrderItem::query()->count())->toBe(1);
})->with(['guest', 'waiter']);

test('an archived restaurant produces a safe availability refusal rather than a broken new order', function (): void {
    $this->branch->delete();
    expect(fn () => app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, []))->toThrow(ValidationException::class);
    expect(DraftOrderItem::query()->count())->toBe(0);
});

test('unresolved modifier snapshots fail safely before a new order is accepted', function (string $operation, array $snapshot): void {
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, []);
    if ($operation === 'confirm') {
        app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest);
    }
    $line->update(['selected_modifiers' => [$snapshot]]);
    expect(fn () => $operation === 'submit'
        ? app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest)
        : app(ConfirmDraftOrderByWaiterAction::class)->handle($line->draftOrder, $this->actor))->toThrow(ValidationException::class);
    expect(Order::query()->count())->toBe(0)->and($line->fresh()->selected_modifiers)->toBe([$snapshot]);
})->with(['submit', 'confirm'])->with([
    'names only' => [['group_name' => 'Sauce', 'option_name' => 'Pepper']],
    'missing option' => [['group_id' => 10]],
    'malformed identity' => [['group_id' => [], 'option_id' => 'invalid']],
]);

test('invalid modifier collection values fail safely without rewriting stored history', function (mixed $snapshot): void {
    $line = app(AddGuestDraftOrderItemAction::class)->handle($this->session, $this->guest, $this->item, []);
    $line->update(['selected_modifiers' => $snapshot]);
    expect(fn () => app(SendDraftOrderToWaiterAction::class)->handle($line->draftOrder, $this->guest))->toThrow(ValidationException::class);
    expect(Order::query()->count())->toBe(0)->and($line->fresh()->selected_modifiers)->toBe($snapshot);
})->with(['scalar string' => ['broken'], 'scalar number' => [123], 'scalar boolean' => [false]]);
