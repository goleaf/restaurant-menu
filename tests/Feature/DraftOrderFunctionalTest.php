<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\DraftOrders\SendDraftOrderToWaiterAction;
use App\Actions\DraftOrders\UpdateGuestDraftOrderItemAction;
use App\Actions\Payments\BuildManualPaymentSummaryAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuItemVariantType;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrderComponent;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Assert;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Notification::fake();
});

test('active guest adds a menu item to the table draft and totals recalculate', function (): void {
    $context = createPrompt354DraftOrderContext();

    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['pizzaItem'],
        selectedModifierOptions: [],
        comment: 'First round',
    );

    $draftOrder = DraftOrder::query()
        ->with(['items.guest'])
        ->where('table_session_id', $context['tableSession']->id)
        ->firstOrFail();

    expect($draftOrder->status)->toBe(DraftOrderStatus::Draft)
        ->and($draftOrderItem->draft_order_id)->toBe($draftOrder->id)
        ->and($draftOrderItem->table_session_guest_id)->toBe($context['ana']->id)
        ->and($draftOrderItem->menu_item_id)->toBe($context['pizzaItem']->id)
        ->and($draftOrderItem->item_name)->toBe('Pizza Margherita')
        ->and($draftOrderItem->quantity)->toBe(1)
        ->and($draftOrderItem->unit_price_cents)->toBe(1250)
        ->and($draftOrderItem->total_price_cents)->toBe(1250)
        ->and($draftOrderItem->comment)->toBe('First round')
        ->and($draftOrder->totalAmount())->toBe('12.50')
        ->and($draftOrder->guestTotals())->toBe([
            [
                'guest_id' => $context['ana']->id,
                'guest_name' => 'Ana',
                'total' => '12.50',
            ],
        ])
        ->and(Order::query()->count())->toBe(0);
});

test('guest can update quantity comment and delete only own draft items', function (): void {
    $context = createPrompt354DraftOrderContext();

    $pizzaDraftItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['pizzaItem'],
        selectedModifierOptions: [],
    );
    $waterDraftItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['waterItem'],
        selectedModifierOptions: [],
    );

    $updatedItem = app(UpdateGuestDraftOrderItemAction::class)->handle(
        draftOrderItem: $pizzaDraftItem,
        guest: $context['ana'],
        quantity: 3,
        selectedModifierOptions: [],
        comment: 'Cut into small slices',
    );
    app(DeleteGuestDraftOrderItemAction::class)->handle($waterDraftItem, $context['ana']);

    $draftOrder = DraftOrder::query()
        ->with(['items.guest'])
        ->whereKey($pizzaDraftItem->draft_order_id)
        ->firstOrFail();

    expect($updatedItem->quantity)->toBe(3)
        ->and($updatedItem->comment)->toBe('Cut into small slices')
        ->and($updatedItem->total_price_cents)->toBe(3750)
        ->and(DraftOrderItem::query()->whereKey($waterDraftItem->id)->exists())->toBeFalse()
        ->and($draftOrder->items)->toHaveCount(1)
        ->and($draftOrder->totalAmount())->toBe('37.50')
        ->and($draftOrder->guestTotals())->toBe([
            [
                'guest_id' => $context['ana']->id,
                'guest_name' => 'Ana',
                'total' => '37.50',
            ],
        ]);
});

test('guest draft item prices are server calculated and keep price at add time', function (): void {
    $context = createPrompt354DraftOrderContext();
    $modifierGroup = ModifierGroup::factory()
        ->for($context['branch'])
        ->create([
            'name' => 'Size',
            'max_select' => 1,
        ]);
    $largeOption = ModifierOption::factory()
        ->for($modifierGroup, 'modifierGroup')
        ->create([
            'name' => 'Large',
            'price_delta_cents' => 325,
        ]);
    $context['pizzaItem']->modifierGroups()->attach($modifierGroup->id);

    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['pizzaItem'],
        selectedModifierOptions: [(string) $modifierGroup->id => [$largeOption->id]],
        comment: null,
        itemName: 'Frontend forged free pizza',
    );

    expect($draftOrderItem->item_name)->toBe('Pizza Margherita')
        ->and($draftOrderItem->unit_price_cents)->toBe(1250)
        ->and($draftOrderItem->modifier_total_cents)->toBe(325)
        ->and($draftOrderItem->total_price_cents)->toBe(1575);

    $context['pizzaItem']->forceFill(['price_cents' => 9900])->save();
    $largeOption->forceFill(['price_delta_cents' => 5000])->save();

    $updatedItem = app(UpdateGuestDraftOrderItemAction::class)->handle(
        draftOrderItem: $draftOrderItem,
        guest: $context['ana'],
        quantity: 2,
        selectedModifierOptions: [(string) $modifierGroup->id => [$largeOption->id]],
    );

    expect($updatedItem->unit_price_cents)->toBe(1250)
        ->and($updatedItem->modifier_total_cents)->toBe(325)
        ->and($updatedItem->total_price_cents)->toBe(3150);
});

test('guest selects an available dish variant and its price and name are snapshotted', function (): void {
    $context = createPrompt354DraftOrderContext();
    $small = MenuItemVariant::factory()
        ->for($context['pizzaItem'], 'item')
        ->portion()
        ->default()
        ->create(['name' => 'Small', 'price_cents' => 1250]);
    $large = MenuItemVariant::factory()
        ->for($context['pizzaItem'], 'item')
        ->portion()
        ->create(['name' => 'Large', 'price_cents' => 1800]);

    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['pizzaItem'],
        menuItemVariantId: $large->id,
        selectedModifierOptions: [],
    );

    expect($draftOrderItem->menu_item_variant_id)->toBe($large->id)
        ->and($draftOrderItem->variant_name)->toBe('Large')
        ->and($draftOrderItem->variant_type)->toBe(MenuItemVariantType::Portion)
        ->and($draftOrderItem->unit_price_cents)->toBe(1800)
        ->and($draftOrderItem->total_price_cents)->toBe(1800);

    $large->updateOrFail(['name' => 'Renamed', 'price_cents' => 2400]);

    $updatedItem = app(UpdateGuestDraftOrderItemAction::class)->handle(
        draftOrderItem: $draftOrderItem,
        guest: $context['ana'],
        quantity: 2,
        menuItemVariantId: $large->id,
        selectedModifierOptions: [],
    );

    expect($updatedItem->variant_name)->toBe('Large')
        ->and($updatedItem->unit_price_cents)->toBe(1800)
        ->and($updatedItem->total_price_cents)->toBe(3600)
        ->and($small->is_default)->toBeTrue();
});

test('server requires an available variant and rejects ids from another dish', function (): void {
    $context = createPrompt354DraftOrderContext();
    $unavailable = MenuItemVariant::factory()
        ->for($context['pizzaItem'], 'item')
        ->portion()
        ->unavailable()
        ->create();
    $foreignVariant = MenuItemVariant::factory()
        ->for($context['waterItem'], 'item')
        ->portion()
        ->create();

    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(AddGuestDraftOrderItemAction::class)->handle(
            tableSession: $context['tableSession'],
            guest: $context['ana'],
            menuItem: $context['pizzaItem'],
            selectedModifierOptions: [],
        ),
        'selectedItemVariantId',
    );
    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(AddGuestDraftOrderItemAction::class)->handle(
            tableSession: $context['tableSession'],
            guest: $context['ana'],
            menuItem: $context['pizzaItem'],
            menuItemVariantId: $unavailable->id,
            selectedModifierOptions: [],
        ),
        'selectedItemVariantId',
    );
    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(AddGuestDraftOrderItemAction::class)->handle(
            tableSession: $context['tableSession'],
            guest: $context['ana'],
            menuItem: $context['pizzaItem'],
            menuItemVariantId: $foreignVariant->id,
            selectedModifierOptions: [],
        ),
        'selectedItemVariantId',
    );

    expect(DraftOrderItem::query()->exists())->toBeFalse();
});

test('waiter confirmation preserves variant snapshot in immutable order history', function (): void {
    $context = createPrompt354DraftOrderContext();
    $waiter = createPrompt354Waiter($context['organization']);
    $large = MenuItemVariant::factory()
        ->for($context['pizzaItem'], 'item')
        ->portion()
        ->default()
        ->create(['name' => 'Large', 'price_cents' => 1800]);
    $draftItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['pizzaItem'],
        menuItemVariantId: $large->id,
        selectedModifierOptions: [],
    );
    $sentDraft = app(SendDraftOrderToWaiterAction::class)->handle($draftItem->draftOrder, $context['ana']);

    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($sentDraft, $waiter);
    $orderItem = $order->items()->firstOrFail();

    expect($orderItem->menu_item_variant_id)->toBe($large->id)
        ->and($orderItem->variant_name)->toBe('Large')
        ->and($orderItem->variant_type)->toBe(MenuItemVariantType::Portion)
        ->and($orderItem->unit_price_cents)->toBe(1800)
        ->and($orderItem->unit_price_snapshot_cents)->toBe(1800)
        ->and($orderItem->total_price_cents)->toBe(1800);

    $large->deleteOrFail();

    expect($orderItem->refresh()->menu_item_variant_id)->toBeNull()
        ->and($orderItem->variant_name)->toBe('Large')
        ->and($orderItem->variant_type)->toBe(MenuItemVariantType::Portion)
        ->and($orderItem->total_price_cents)->toBe(1800);
});

test('server rejects negative menu or modifier totals for draft prices', function (): void {
    $context = createPrompt354DraftOrderContext();

    $context['pizzaItem']->forceFill(['price_cents' => -1250])->save();

    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(AddGuestDraftOrderItemAction::class)->handle(
            tableSession: $context['tableSession'],
            guest: $context['ana'],
            menuItem: $context['pizzaItem'],
            selectedModifierOptions: [],
        ),
        'menu_item',
    );

    $context = createPrompt354DraftOrderContext();
    $modifierGroup = ModifierGroup::factory()
        ->for($context['branch'])
        ->create(['max_select' => 1]);
    $discountOption = ModifierOption::factory()
        ->for($modifierGroup, 'modifierGroup')
        ->create(['price_delta_cents' => -2000]);
    $context['pizzaItem']->modifierGroups()->attach($modifierGroup->id);

    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(AddGuestDraftOrderItemAction::class)->handle(
            tableSession: $context['tableSession'],
            guest: $context['ana'],
            menuItem: $context['pizzaItem'],
            selectedModifierOptions: [(string) $modifierGroup->id => [$discountOption->id]],
        ),
        'selectedModifierOptions',
    );
});

test('server-side guard forbids editing or deleting another guest draft item', function (): void {
    $context = createPrompt354DraftOrderContext();

    $borisDraftItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['boris'],
        menuItem: $context['pizzaItem'],
        selectedModifierOptions: [],
    );

    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(UpdateGuestDraftOrderItemAction::class)->handle(
            draftOrderItem: $borisDraftItem,
            guest: $context['ana'],
            quantity: 2,
            selectedModifierOptions: [],
            comment: 'Trying to change somebody else item',
        ),
        'draft_item',
    );
    expectPrompt354ValidationError(
        fn () => app(DeleteGuestDraftOrderItemAction::class)->handle($borisDraftItem, $context['ana']),
        'draft_item',
    );

    $borisDraftItem = $borisDraftItem->fresh();

    expect($borisDraftItem)->toBeInstanceOf(DraftOrderItem::class)
        ->and($borisDraftItem->table_session_guest_id)->toBe($context['boris']->id)
        ->and($borisDraftItem->quantity)->toBe(1)
        ->and($borisDraftItem->total_price_cents)->toBe(1250);
});

test('shared item allocations split one item between selected guests and keep table totals correct', function (): void {
    //
})->todo(issue: 10);

test('any active guest can send the draft to waiter and guests cannot edit it anymore', function (): void {
    $context = createPrompt354DraftOrderContext();
    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['pizzaItem'],
        selectedModifierOptions: [],
    );
    $draftOrder = $draftOrderItem->draftOrder()->firstOrFail();

    $sentDraftOrder = app(SendDraftOrderToWaiterAction::class)->handle($draftOrder, $context['boris']);

    expect($sentDraftOrder->status)->toBe(DraftOrderStatus::SentToWaiter)
        ->and($sentDraftOrder->sent_by_guest_id)->toBe($context['boris']->id)
        ->and($sentDraftOrder->sent_to_waiter_at)->not->toBeNull()
        ->and(Order::query()->count())->toBe(0);

    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(AddGuestDraftOrderItemAction::class)->handle(
            tableSession: $context['tableSession'],
            guest: $context['ana'],
            menuItem: $context['waterItem'],
            selectedModifierOptions: [],
        ),
        'draft_order',
    );
    expectPrompt354ValidationError(
        fn (): DraftOrderItem => app(UpdateGuestDraftOrderItemAction::class)->handle(
            draftOrderItem: $draftOrderItem,
            guest: $context['ana'],
            quantity: 2,
            selectedModifierOptions: [],
            comment: 'Too late',
        ),
        'draft_order',
    );
    expectPrompt354ValidationError(
        fn () => app(DeleteGuestDraftOrderItemAction::class)->handle($draftOrderItem, $context['ana']),
        'draft_order',
    );

    Livewire::withCookie(prompt354GuestTokenCookieName($context['publicToken']), $context['ana']->guest_token)
        ->test(GuestDraftOrderComponent::class, [
            'tableSessionId' => $context['tableSession']->id,
            'currentGuestId' => $context['ana']->id,
            'publicToken' => $context['publicToken'],
            'currency' => 'EUR',
        ])
        ->assertSet('canEditDraft', false)
        ->assertSet('canSendDraftToWaiter', false)
        ->call('editItem', $draftOrderItem->id)
        ->assertHasErrors(['draft_order'])
        ->call('deleteItem', $draftOrderItem->id)
        ->assertHasErrors(['draft_order']);

    expect($draftOrderItem->fresh())->toBeInstanceOf(DraftOrderItem::class)
        ->and($sentDraftOrder->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter);
});

test('rejected draft is not billable and a fresh draft can be started', function (): void {
    $context = createPrompt354DraftOrderContext();
    $waiter = createPrompt354Waiter($context['organization']);
    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['ana'],
        menuItem: $context['pizzaItem'],
        selectedModifierOptions: [],
    );
    $sentDraftOrder = app(SendDraftOrderToWaiterAction::class)->handle(
        $draftOrderItem->draftOrder()->firstOrFail(),
        $context['ana'],
    );

    $rejectedDraftOrder = app(RejectDraftOrderByWaiterAction::class)->handle(
        draftOrder: $sentDraftOrder,
        rejectedBy: $waiter,
        reason: 'Pizza is sold out',
    );
    $paymentSummary = app(BuildManualPaymentSummaryAction::class)->handle($context['tableSession']);

    expect($rejectedDraftOrder->status)->toBe(DraftOrderStatus::Rejected)
        ->and($rejectedDraftOrder->rejection_reason)->toBe('Pizza is sold out')
        ->and($paymentSummary['confirmed_total'])->toBe('0.00 EUR')
        ->and($paymentSummary['has_payable_total'])->toBeFalse()
        ->and($paymentSummary['has_open_draft'])->toBeFalse()
        ->and(Order::query()->count())->toBe(0);

    $newDraftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $context['tableSession'],
        guest: $context['boris'],
        menuItem: $context['waterItem'],
        selectedModifierOptions: [],
        comment: 'Fresh draft after rejection',
    );
    $newDraftOrder = $newDraftOrderItem->draftOrder()->with(['items.guest'])->firstOrFail();

    expect($newDraftOrder->id)->not->toBe($rejectedDraftOrder->id)
        ->and($rejectedDraftOrder->fresh()->status)->toBe(DraftOrderStatus::Rejected)
        ->and($newDraftOrder->status)->toBe(DraftOrderStatus::Draft)
        ->and($newDraftOrderItem->table_session_guest_id)->toBe($context['boris']->id)
        ->and($newDraftOrder->totalAmount())->toBe('4.00')
        ->and($newDraftOrder->guestTotals())->toBe([
            [
                'guest_id' => $context['boris']->id,
                'guest_name' => 'Boris',
                'total' => '4.00',
            ],
        ])
        ->and(Order::query()->count())->toBe(0);
});

/**
 * @return array{
 *     organization: Organization,
 *     branch: Branch,
 *     servicePoint: ServicePoint,
 *     tableSession: TableSession,
 *     ana: TableSessionGuest,
 *     boris: TableSessionGuest,
 *     pizzaItem: MenuItem,
 *     waterItem: MenuItem,
 *     publicToken: string
 * }
 */
function createPrompt354DraftOrderContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 354 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 354 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 354 Branch',
            'currency' => 'EUR',
        ]);

    BranchSetting::factory()->for($branch)->create();

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 354 Table',
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->waiterOpened()
        ->active()
        ->create([
            'status' => TableSessionStatus::Active,
        ]);
    $ana = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $boris = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Boris',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 354 Menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Prompt 354 Mains',
            'is_active' => true,
        ]);
    $pizzaItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Pizza Margherita',
            'price_cents' => 1250,
            'is_available' => true,
        ]);
    $waterItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Still Water',
            'price_cents' => 400,
            'is_available' => true,
        ]);

    return [
        'organization' => $organization,
        'branch' => $branch,
        'servicePoint' => $servicePoint,
        'tableSession' => $tableSession,
        'ana' => $ana,
        'boris' => $boris,
        'pizzaItem' => $pizzaItem,
        'waterItem' => $waterItem,
        'publicToken' => 'prompt354publictoken'.str_repeat('x', 44),
    ];
}

function createPrompt354Waiter(Organization $organization): User
{
    $waiter = User::factory()->create(['name' => 'Prompt 354 Waiter']);
    $role = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    foreach ([SystemPermission::ViewOrders, SystemPermission::ConfirmOrders] as $permissionCode) {
        $permission = Permission::query()
            ->where('code', $permissionCode->value)
            ->firstOrFail();

        $role->permissions()->updateExistingPivot($permission->id, ['enabled' => true]);
    }

    $organization->users()->syncWithoutDetachingOrFail([
        $waiter->id => [
            'role_id' => $role->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $waiter;
}

function expectPrompt354ValidationError(Closure $callback, string $field): void
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);

        return;
    }

    Assert::fail('Expected validation error for ['.$field.'].');
}

function prompt354GuestTokenCookieName(string $publicToken): string
{
    return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
}
