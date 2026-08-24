<?php

declare(strict_types=1);

use App\Actions\Departments\UpdateDepartmentTicketItemStatusAction;
use App\Actions\DraftOrders\DeleteGuestDraftOrderItemAction;
use App\Actions\Orders\ChangeOrderStatusAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\TableSessions\CloseTableSessionAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\MarkKitchenTicketItemServedAction;
use App\Enums\DraftOrderStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderStatusLogEvent;
use App\Enums\OrganizationUserStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('order and department item states expose one centralized transition contract', function (): void {
    expect(TableSessionStatus::Closed->isTerminal())->toBeTrue()
        ->and(TableSessionStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(TableSessionStatus::Active->isTerminal())->toBeFalse()
        ->and(TableSessionStatus::Pending->allowsGuestParticipation())->toBeTrue()
        ->and(TableSessionStatus::Active->allowsGuestParticipation())->toBeTrue()
        ->and(TableSessionStatus::PaymentRequested->allowsGuestParticipation())->toBeFalse()
        ->and(TableSessionStatus::Paid->locksOrderChanges())->toBeTrue()
        ->and(TableSessionStatus::Closed->locksOrderChanges())->toBeTrue()
        ->and(TableSessionStatus::Cancelled->locksOrderChanges())->toBeTrue()
        ->and(TableSessionStatus::Active->locksOrderChanges())->toBeFalse()
        ->and(DraftOrderStatus::Draft->isGuestEditable())->toBeTrue()
        ->and(DraftOrderStatus::SentToWaiter->isGuestEditable())->toBeFalse()
        ->and(DraftOrderStatus::SentToWaiter->isWaiterEditable())->toBeTrue()
        ->and(DraftOrderStatus::WaiterReview->isWaiterEditable())->toBeTrue()
        ->and(DraftOrderStatus::Rejected->isWaiterEditable())->toBeFalse()
        ->and(DraftOrderStatus::Draft->canTransitionTo(DraftOrderStatus::SentToWaiter))->toBeTrue()
        ->and(DraftOrderStatus::SentToWaiter->canTransitionTo(DraftOrderStatus::WaiterReview))->toBeTrue()
        ->and(DraftOrderStatus::SentToWaiter->canTransitionTo(DraftOrderStatus::Draft))->toBeFalse()
        ->and(DraftOrderStatus::Rejected->canTransitionTo(DraftOrderStatus::Draft))->toBeTrue()
        ->and(DraftOrderStatus::ConvertedToOrder->canTransitionTo(DraftOrderStatus::Draft))->toBeFalse()
        ->and(OrderStatus::ConfirmedByWaiter->canTransitionTo(OrderStatus::SentToKitchenBar))->toBeTrue()
        ->and(OrderStatus::ConfirmedByWaiter->canTransitionTo(OrderStatus::Ready))->toBeFalse()
        ->and(OrderStatus::Ready->canTransitionTo(OrderStatus::Served))->toBeTrue()
        ->and(OrderStatus::Served->canTransitionTo(OrderStatus::InProgress))->toBeFalse()
        ->and(OrderStatus::Cancelled->canTransitionTo(OrderStatus::SentToKitchenBar))->toBeFalse()
        ->and(KitchenTicketItemStatus::New->canTransitionTo(KitchenTicketItemStatus::InProgress))->toBeTrue()
        ->and(KitchenTicketItemStatus::New->canTransitionTo(KitchenTicketItemStatus::Ready))->toBeTrue()
        ->and(KitchenTicketItemStatus::InProgress->canTransitionTo(KitchenTicketItemStatus::Ready))->toBeTrue()
        ->and(KitchenTicketItemStatus::Ready->canTransitionTo(KitchenTicketItemStatus::InProgress))->toBeFalse()
        ->and(KitchenTicketItemStatus::Cancelled->canTransitionTo(KitchenTicketItemStatus::Ready))->toBeFalse();
});

test('waiter confirmation atomically dispatches one immutable order and is replay safe', function (): void {
    [$organization, , $draftOrder] = createOrderLifecycleDraft();
    $waiter = attachOrderLifecycleWaiter($organization);

    $firstResult = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);
    $secondResult = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder->fresh(), $waiter);

    expect($firstResult->id)->toBe($secondResult->id)
        ->and($firstResult->fresh()->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and(Order::query()->where('draft_order_id', $draftOrder->id)->count())->toBe(1)
        ->and(KitchenTicket::query()->where('order_id', $firstResult->id)->count())->toBe(1)
        ->and(OrderStatusLog::query()
            ->where('order_id', $firstResult->id)
            ->where('event', OrderStatusLogEvent::DraftConfirmed->value)
            ->count())->toBe(1)
        ->and(OrderStatusLog::query()
            ->where('order_id', $firstResult->id)
            ->where('event', OrderStatusLogEvent::OrderSentToKitchenBar->value)
            ->count())->toBe(1);
});

test('table cannot close while an order still requires fulfilment', function (): void {
    [$organization, $tableSession, $draftOrder] = createOrderLifecycleDraft();
    $waiter = attachOrderLifecycleWaiter($organization);
    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);

    expect($order->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and(fn () => app(CloseTableSessionAction::class)->handle($tableSession, $waiter))
        ->toThrow(ValidationException::class)
        ->and($tableSession->fresh()->status)->toBe(TableSessionStatus::Active);
});

test('forbidden order and department regressions fail without overwriting history', function (): void {
    [$organization, , $draftOrder] = createOrderLifecycleDraft();
    $waiter = attachOrderLifecycleWaiter($organization);
    $order = app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter);
    $ticketItem = KitchenTicketItem::query()
        ->whereHas('kitchenTicket', fn ($query) => $query->where('order_id', $order->id))
        ->firstOrFail();
    $chef = attachOrderLifecycleStaff($organization, SystemRole::HeadChef, 'Lifecycle Chef');

    app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::Ready,
        user: $chef,
        departmentTypes: [KitchenDepartmentType::Kitchen],
        roleCodes: [SystemRole::HeadChef],
        permissionCodes: [SystemPermission::ViewKitchen],
    );

    expect(fn () => app(UpdateDepartmentTicketItemStatusAction::class)->handle(
        itemId: $ticketItem->id,
        status: KitchenTicketItemStatus::InProgress,
        user: $chef,
        departmentTypes: [KitchenDepartmentType::Kitchen],
        roleCodes: [SystemRole::HeadChef],
        permissionCodes: [SystemPermission::ViewKitchen],
    ))->toThrow(ValidationException::class)
        ->and($ticketItem->fresh()->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and(OrderStatusLog::query()
            ->where('order_id', $order->id)
            ->where('event', OrderStatusLogEvent::TicketItemStatusChanged->value)
            ->count())->toBe(1);

    app(MarkKitchenTicketItemServedAction::class)->handle($ticketItem->fresh(), $waiter);

    expect($order->fresh()->status)->toBe(OrderStatus::Served)
        ->and(OrderStatusLog::query()
            ->where('order_id', $order->id)
            ->where('event', OrderStatusLogEvent::TicketItemServed->value)
            ->where('actor_user_id', $waiter->id)
            ->count())->toBe(1)
        ->and(fn () => app(ChangeOrderStatusAction::class)->handle(
            order: $order->fresh(),
            newStatus: OrderStatus::InProgress,
            changedBy: $waiter,
        ))->toThrow(ValidationException::class)
        ->and($order->fresh()->status)->toBe(OrderStatus::Served);
});

test('waiter cannot confirm a draft from another tenant', function (): void {
    [$firstOrganization] = createOrderLifecycleDraft();
    [, , $foreignDraft] = createOrderLifecycleDraft();
    $waiter = attachOrderLifecycleWaiter($firstOrganization);

    expect(fn () => app(ConfirmDraftOrderByWaiterAction::class)->handle($foreignDraft, $waiter))
        ->toThrow(ValidationException::class)
        ->and(Order::query()->where('draft_order_id', $foreignDraft->id)->exists())->toBeFalse()
        ->and($foreignDraft->fresh()->status)->toBe(DraftOrderStatus::SentToWaiter);
});

test('paid table session rejects guest edits and waiter confirmation', function (): void {
    [$organization, $tableSession, $draftOrder] = createOrderLifecycleDraft();
    $waiter = attachOrderLifecycleWaiter($organization);
    $draftItem = $draftOrder->items()->firstOrFail();
    $guest = $draftItem->guest()->firstOrFail();

    $tableSession->forceFill(['status' => TableSessionStatus::Paid])->save();

    expect(fn () => app(DeleteGuestDraftOrderItemAction::class)->handle($draftItem, $guest))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(ConfirmDraftOrderByWaiterAction::class)->handle($draftOrder, $waiter))
        ->toThrow(ValidationException::class)
        ->and($draftOrder->items()->count())->toBe(1)
        ->and(Order::query()->where('draft_order_id', $draftOrder->id)->exists())->toBeFalse();
});

/**
 * @return array{Organization, TableSession, DraftOrder}
 */
function createOrderLifecycleDraft(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Lifecycle Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Lifecycle Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Lifecycle Restaurant']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Lifecycle Table',
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
        ->create([
            'menu_item_id' => null,
            'item_name' => 'Lifecycle Soup',
            'quantity' => 1,
            'unit_price_cents' => 900,
            'modifier_total_cents' => 0,
            'total_price_cents' => 900,
            'selected_modifiers' => [],
        ]);

    return [$organization, $tableSession, $draftOrder];
}

function attachOrderLifecycleWaiter(Organization $organization): User
{
    return attachOrderLifecycleStaff($organization, SystemRole::Waiter, 'Lifecycle Waiter');
}

function attachOrderLifecycleStaff(Organization $organization, SystemRole $roleCode, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    $role = Role::query()->where('code', $roleCode->value)->firstOrFail();

    OrganizationUser::factory()
        ->forOrganization($organization)
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['status' => OrganizationUserStatus::Active]);

    return $user;
}
