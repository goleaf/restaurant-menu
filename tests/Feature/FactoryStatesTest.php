<?php

use App\Enums\DraftOrderStatus;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\Branch;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;

test('branch service point and qr factories expose operational setup states', function () {
    $activeBranch = Branch::factory()->active()->create();
    $inactiveBranch = Branch::factory()->inactive()->create();
    $branchWithSettings = Branch::factory()->withDefaultSettings()->create();
    $freePoint = ServicePoint::factory()->free()->create();
    $occupiedPoint = ServicePoint::factory()->occupied()->create();
    $blockedPoint = ServicePoint::factory()->blocked()->create();
    $pointWithQr = ServicePoint::factory()->withQr()->create();
    $pointWithoutQr = ServicePoint::factory()->withoutQr()->create();
    $activeQr = QrCode::factory()->active()->create();
    $disabledQr = QrCode::factory()->disabled()->create();
    $revokedQr = QrCode::factory()->revoked()->create();

    expect($activeBranch->is_active)->toBeTrue()
        ->and($inactiveBranch->is_active)->toBeFalse()
        ->and($branchWithSettings->settings)->not->toBeNull()
        ->and($freePoint->status)->toBe(ServicePointStatus::Free)
        ->and($occupiedPoint->status)->toBe(ServicePointStatus::Occupied)
        ->and($blockedPoint->status)->toBe(ServicePointStatus::Blocked)
        ->and($blockedPoint->is_active)->toBeFalse()
        ->and($pointWithQr->qrCodes()->count())->toBe(1)
        ->and($pointWithoutQr->qrCodes()->count())->toBe(0)
        ->and($activeQr->status)->toBe(QrCodeStatus::Active)
        ->and($disabledQr->status)->toBe(QrCodeStatus::Disabled)
        ->and($disabledQr->revoked_at)->toBeNull()
        ->and($revokedQr->status)->toBe(QrCodeStatus::Revoked)
        ->and($revokedQr->revoked_at)->not->toBeNull();
});

test('session guest and draft factories expose lifecycle states', function () {
    $activeSession = TableSession::factory()->active()->create();
    $paymentRequestedSession = TableSession::factory()->paymentRequested()->create();
    $paidSession = TableSession::factory()->paid()->create();
    $closedSession = TableSession::factory()->closed()->create();
    $guestCreatedSession = TableSession::factory()->guestCreated()->create();
    $waiterOpenedSession = TableSession::factory()->waiterOpened()->create();

    $activeGuest = TableSessionGuest::factory()->active()->create();
    $pendingGuest = TableSessionGuest::factory()->pendingApproval()->create();
    $rejectedGuest = TableSessionGuest::factory()->rejected()->create();
    $removedGuest = TableSessionGuest::factory()->removed()->create();
    $leftGuest = TableSessionGuest::factory()->left()->create();

    $draft = DraftOrder::factory()->draft()->create();
    $sentToWaiter = DraftOrder::factory()->sentToWaiter()->create();
    $waiterReview = DraftOrder::factory()->waiterReview()->create();
    $rejected = DraftOrder::factory()->rejected()->create();
    $converted = DraftOrder::factory()->convertedToOrder()->create();

    expect($activeSession->status)->toBe(TableSessionStatus::Active)
        ->and($activeSession->started_at)->not->toBeNull()
        ->and($paymentRequestedSession->status)->toBe(TableSessionStatus::PaymentRequested)
        ->and($paidSession->status)->toBe(TableSessionStatus::Paid)
        ->and($closedSession->status)->toBe(TableSessionStatus::Closed)
        ->and($closedSession->ended_at)->not->toBeNull()
        ->and($guestCreatedSession->source)->toBe(TableSessionSource::GuestCreated)
        ->and($guestCreatedSession->opened_by_guest_id)->not->toBeNull()
        ->and($waiterOpenedSession->source)->toBe(TableSessionSource::WaiterOpened)
        ->and($waiterOpenedSession->opened_by_user_id)->not->toBeNull()
        ->and($activeGuest->status)->toBe(TableSessionGuestStatus::Active)
        ->and($pendingGuest->status)->toBe(TableSessionGuestStatus::PendingApproval)
        ->and($rejectedGuest->status)->toBe(TableSessionGuestStatus::Rejected)
        ->and($removedGuest->status)->toBe(TableSessionGuestStatus::Removed)
        ->and($leftGuest->status)->toBe(TableSessionGuestStatus::Left)
        ->and($draft->status)->toBe(DraftOrderStatus::Draft)
        ->and($sentToWaiter->status)->toBe(DraftOrderStatus::SentToWaiter)
        ->and($sentToWaiter->sent_to_waiter_at)->not->toBeNull()
        ->and($waiterReview->status)->toBe(DraftOrderStatus::WaiterReview)
        ->and($rejected->status)->toBe(DraftOrderStatus::Rejected)
        ->and($rejected->rejected_at)->not->toBeNull()
        ->and($converted->status)->toBe(DraftOrderStatus::ConvertedToOrder)
        ->and($converted->converted_to_order_at)->not->toBeNull();
});

test('order readiness payment and menu item factories expose core states', function () {
    $confirmed = Order::factory()->confirmedByWaiter()->create();
    $sentToDepartments = Order::factory()->sentToDepartments()->create();
    $preparing = Order::factory()->preparing()->create();
    $ready = Order::factory()->ready()->create();
    $served = Order::factory()->served()->create();
    $cancelled = Order::factory()->cancelled()->create();

    $pendingReadiness = KitchenTicketItem::factory()->pending()->create();
    $preparingReadiness = KitchenTicketItem::factory()->preparing()->create();
    $readyReadiness = KitchenTicketItem::factory()->ready()->create();

    $cashPayment = ManualPayment::factory()->cash()->create();
    $cardPayment = ManualPayment::factory()->cardTerminal()->create();
    $otherPayment = ManualPayment::factory()->other()->create();

    $availableItem = MenuItem::factory()->available()->create();
    $unavailableItem = MenuItem::factory()->unavailable()->create();
    $itemWithModifiers = MenuItem::factory()->withModifiers(groups: 2, optionsPerGroup: 2)->create();
    $itemWithVariants = MenuItem::factory()->withVariants()->create();
    $department = KitchenDepartment::factory()->create();
    $departmentItem = MenuItem::factory()->assignedToDepartment($department)->create();

    expect($confirmed->status)->toBe(OrderStatus::ConfirmedByWaiter)
        ->and($sentToDepartments->status)->toBe(OrderStatus::SentToKitchenBar)
        ->and($preparing->status)->toBe(OrderStatus::InProgress)
        ->and($ready->status)->toBe(OrderStatus::Ready)
        ->and($served->status)->toBe(OrderStatus::Served)
        ->and($cancelled->status)->toBe(OrderStatus::Cancelled)
        ->and($pendingReadiness->status)->toBe(KitchenTicketItemStatus::New)
        ->and($preparingReadiness->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and($readyReadiness->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($cashPayment->payment_method)->toBe(ManualPaymentMethod::Cash)
        ->and($cardPayment->payment_method)->toBe(ManualPaymentMethod::CardTerminal)
        ->and($otherPayment->payment_method)->toBe(ManualPaymentMethod::Other)
        ->and($availableItem->is_available)->toBeTrue()
        ->and($unavailableItem->is_available)->toBeFalse()
        ->and($itemWithModifiers->modifierGroups()->count())->toBe(2)
        ->and($itemWithModifiers->modifierGroups()->withCount('options')->get()->pluck('options_count')->all())->toBe([2, 2])
        ->and($itemWithVariants->modifierGroups()->count())->toBe(1)
        ->and($departmentItem->kitchen_department_id)->toBe($department->id);
});
