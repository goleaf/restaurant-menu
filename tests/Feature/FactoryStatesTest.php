<?php

declare(strict_types=1);

use App\Enums\AreaNodeType;
use App\Enums\DraftOrderStatus;
use App\Enums\InvitationStatus;
use App\Enums\KitchenDepartmentType;
use App\Enums\KitchenTicketItemStatus;
use App\Enums\ManualPaymentMethod;
use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\MenuItemVariantType;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\OrganizationSubscriptionPaymentStatus;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Enums\WaiterCallStatus;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchUser;
use App\Models\DraftOrder;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicketItem;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationSubscription;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\TableSessionServicePoint;
use App\Models\User;
use App\Models\WaiterCall;

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
    $cancellingUser = User::factory()->create();
    $cancelledOrderItem = OrderItem::factory()
        ->cancelledBy($cancellingUser, 'Guest changed their mind.')
        ->create();

    $pendingReadiness = KitchenTicketItem::factory()->pending()->create();
    $preparingReadiness = KitchenTicketItem::factory()->preparing()->create();
    $readyReadiness = KitchenTicketItem::factory()->ready()->create();
    $cancelledReadiness = KitchenTicketItem::factory()->cancelled()->create();

    $cashPayment = ManualPayment::factory()->cash()->create();
    $cardPayment = ManualPayment::factory()->cardTerminal()->create();
    $otherPayment = ManualPayment::factory()->other()->create();

    $availableItem = MenuItem::factory()->available()->create();
    $unavailableItem = MenuItem::factory()->unavailable()->create();
    $itemWithAllergens = MenuItem::factory()->withAllergens(MenuAllergen::Eggs, MenuAllergen::Milk)->create();
    $itemWithDietaryLabels = MenuItem::factory()->withDietaryLabels(MenuDietaryLabel::Vegan, MenuDietaryLabel::GlutenFree)->create();
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
        ->and($cancelledOrderItem->cancelled_at)->not->toBeNull()
        ->and($cancelledOrderItem->cancelled_by_user_id)->toBe($cancellingUser->id)
        ->and($cancelledOrderItem->cancellation_reason)->toBe('Guest changed their mind.')
        ->and($pendingReadiness->status)->toBe(KitchenTicketItemStatus::New)
        ->and($preparingReadiness->status)->toBe(KitchenTicketItemStatus::InProgress)
        ->and($readyReadiness->status)->toBe(KitchenTicketItemStatus::Ready)
        ->and($cancelledReadiness->status)->toBe(KitchenTicketItemStatus::Cancelled)
        ->and($cashPayment->payment_method)->toBe(ManualPaymentMethod::Cash)
        ->and($cardPayment->payment_method)->toBe(ManualPaymentMethod::CardTerminal)
        ->and($otherPayment->payment_method)->toBe(ManualPaymentMethod::Other)
        ->and($availableItem->is_available)->toBeTrue()
        ->and($unavailableItem->is_available)->toBeFalse()
        ->and($itemWithAllergens->allergens)->toBe(['eggs', 'milk'])
        ->and($itemWithDietaryLabels->dietary_labels)->toBe(['vegan', 'gluten_free'])
        ->and($itemWithModifiers->modifierGroups()->count())->toBe(2)
        ->and($itemWithModifiers->modifierGroups()->withCount('options')->get()->pluck('options_count')->all())->toBe([2, 2])
        ->and($itemWithVariants->variants()->count())->toBe(2)
        ->and($itemWithVariants->variants()->where('is_default', true)->count())->toBe(1)
        ->and($departmentItem->kitchen_department_id)->toBe($department->id);
});

test('menu item variant factories expose portion availability and translation states', function () {
    $portion = MenuItemVariant::factory()->portion()->default()->available()->create();
    $unavailableVariant = MenuItemVariant::factory()->variant()->unavailable()->create();
    $translatedVariant = MenuItemVariant::factory()->withTranslations()->create();
    $lithuanianTranslation = MenuItemVariantTranslation::factory()->lithuanian()->create();

    expect($portion->type)->toBe(MenuItemVariantType::Portion)
        ->and($portion->is_default)->toBeTrue()
        ->and($portion->is_available)->toBeTrue()
        ->and($unavailableVariant->type)->toBe(MenuItemVariantType::Variant)
        ->and($unavailableVariant->is_available)->toBeFalse()
        ->and($translatedVariant->translations()->count())->toBe(3)
        ->and($lithuanianTranslation->language_code)->toBe('lt');
});

test('organization and invitation factories expose every lifecycle state', function () {
    $activeMembership = BranchUser::factory()->active()->create();
    $invitedMembership = BranchUser::factory()->invited()->create();
    $suspendedMembership = BranchUser::factory()->suspended()->create();
    $removedMembership = BranchUser::factory()->removed()->create();

    $pendingInvitation = Invitation::factory()->pending()->create();
    $acceptingUser = User::factory()->create();
    $acceptedInvitation = Invitation::factory()->acceptedBy($acceptingUser)->create();
    $expiredInvitation = Invitation::factory()->expired()->create();
    $cancelledInvitation = Invitation::factory()->cancelled()->create();
    $rejectedInvitation = Invitation::factory()->rejected()->create();

    $activeSubscription = OrganizationSubscription::factory()->active()->create();
    $inactiveSubscription = OrganizationSubscription::factory()->inactive()->create();
    $pendingSubscription = OrganizationSubscription::factory()->paymentPending()->create();
    $paidSubscription = OrganizationSubscription::factory()->paymentPaid()->create();
    $overdueSubscription = OrganizationSubscription::factory()->paymentOverdue()->create();
    $failedSubscription = OrganizationSubscription::factory()->paymentFailed()->create();

    expect($activeMembership->status)->toBe(OrganizationUserStatus::Active)
        ->and($invitedMembership->status)->toBe(OrganizationUserStatus::Invited)
        ->and($suspendedMembership->status)->toBe(OrganizationUserStatus::Suspended)
        ->and($removedMembership->status)->toBe(OrganizationUserStatus::Removed)
        ->and($pendingInvitation->status)->toBe(InvitationStatus::Pending)
        ->and($pendingInvitation->expires_at->isFuture())->toBeTrue()
        ->and($acceptedInvitation->status)->toBe(InvitationStatus::Accepted)
        ->and($acceptedInvitation->accepted_by_user_id)->toBe($acceptingUser->id)
        ->and($acceptedInvitation->accepted_at)->not->toBeNull()
        ->and($expiredInvitation->status)->toBe(InvitationStatus::Expired)
        ->and($expiredInvitation->expires_at->isPast())->toBeTrue()
        ->and($cancelledInvitation->status)->toBe(InvitationStatus::Cancelled)
        ->and($rejectedInvitation->status)->toBe(InvitationStatus::Rejected)
        ->and($activeSubscription->status)->toBe(OrganizationSubscriptionStatus::Active)
        ->and($inactiveSubscription->status)->toBe(OrganizationSubscriptionStatus::Inactive)
        ->and($pendingSubscription->payment_status)->toBe(OrganizationSubscriptionPaymentStatus::Pending)
        ->and($paidSubscription->payment_status)->toBe(OrganizationSubscriptionPaymentStatus::Paid)
        ->and($overdueSubscription->payment_status)->toBe(OrganizationSubscriptionPaymentStatus::Overdue)
        ->and($failedSubscription->payment_status)->toBe(OrganizationSubscriptionPaymentStatus::Failed);
});

test('menu factories expose lifecycle localization and modifier states', function () {
    $draftMenu = Menu::factory()->draft()->create();
    $activeMenu = Menu::factory()->active()->create();
    $archivedMenu = Menu::factory()->archived()->create();
    $parentCategory = MenuCategory::factory()->active()->create();
    $childCategory = MenuCategory::factory()->childOf($parentCategory)->create();
    $inactiveCategory = MenuCategory::factory()->inactive()->create();
    $categoryTranslation = MenuCategoryTranslation::factory()
        ->forLocale(SupportedLocale::Lithuanian)
        ->create();
    $itemTranslation = MenuItemTranslation::factory()
        ->forLocale(SupportedLocale::Russian)
        ->create();
    $requiredGroup = ModifierGroup::factory()->required(min: 1, max: 2)->create();
    $optionalGroup = ModifierGroup::factory()->optional(max: 3)->create();
    $availableOption = ModifierOption::factory()->available()->create();
    $unavailableOption = ModifierOption::factory()->unavailable()->create();
    $freeOption = ModifierOption::factory()->free()->create();
    $surchargeOption = ModifierOption::factory()->surcharge(250)->create();
    $discountOption = ModifierOption::factory()->discount(125)->create();

    expect($draftMenu->status)->toBe(MenuStatus::Draft)
        ->and($activeMenu->status)->toBe(MenuStatus::Active)
        ->and($archivedMenu->status)->toBe(MenuStatus::Archived)
        ->and($parentCategory->is_active)->toBeTrue()
        ->and($childCategory->menu_id)->toBe($parentCategory->menu_id)
        ->and($childCategory->parent_id)->toBe($parentCategory->id)
        ->and($inactiveCategory->is_active)->toBeFalse()
        ->and($categoryTranslation->language_code)->toBe(SupportedLocale::Lithuanian->value)
        ->and($itemTranslation->language_code)->toBe(SupportedLocale::Russian->value)
        ->and($requiredGroup->is_required)->toBeTrue()
        ->and($requiredGroup->min_select)->toBe(1)
        ->and($requiredGroup->max_select)->toBe(2)
        ->and($optionalGroup->is_required)->toBeFalse()
        ->and($optionalGroup->min_select)->toBe(0)
        ->and($optionalGroup->max_select)->toBe(3)
        ->and($availableOption->is_available)->toBeTrue()
        ->and($unavailableOption->is_available)->toBeFalse()
        ->and($freeOption->price_delta_cents)->toBe(0)
        ->and($surchargeOption->price_delta_cents)->toBe(250)
        ->and($discountOption->price_delta_cents)->toBe(-125);
});

test('service and table factories expose every operational state', function () {
    $area = AreaNode::factory()->forType(AreaNodeType::Terrace)->active()->create();
    $inactiveArea = AreaNode::factory()->inactive()->create();
    $openHours = BranchOpeningHour::factory()->open('09:00', '23:00')->create();
    $closedHours = BranchOpeningHour::factory()->closed()->create();
    $bar = KitchenDepartment::factory()->forType(KitchenDepartmentType::Bar)->active()->create();
    $inactiveDepartment = KitchenDepartment::factory()->inactive()->create();

    $servicePointStates = [
        [ServicePointStatus::Free, ServicePoint::factory()->free()->create()],
        [ServicePointStatus::Occupied, ServicePoint::factory()->occupied()->create()],
        [ServicePointStatus::Reserved, ServicePoint::factory()->reserved()->create()],
        [ServicePointStatus::WaitingWaiter, ServicePoint::factory()->waitingForWaiter()->create()],
        [ServicePointStatus::HasNewOrder, ServicePoint::factory()->withNewOrder()->create()],
        [ServicePointStatus::Cooking, ServicePoint::factory()->cooking()->create()],
        [ServicePointStatus::ReadyToServe, ServicePoint::factory()->readyToServe()->create()],
        [ServicePointStatus::PaymentRequested, ServicePoint::factory()->paymentRequested()->create()],
        [ServicePointStatus::Paid, ServicePoint::factory()->paid()->create()],
        [ServicePointStatus::Closed, ServicePoint::factory()->closed()->create()],
        [ServicePointStatus::Blocked, ServicePoint::factory()->blocked()->create()],
    ];

    foreach ($servicePointStates as [$status, $servicePoint]) {
        expect($servicePoint->status)->toBe($status);
    }

    $pendingSession = TableSession::factory()->pending()->create();
    $waitingSession = TableSession::factory()->waitingForWaiterConfirmation()->create();
    $cancelledSession = TableSession::factory()->cancelled()->create();

    expect($area->type)->toBe(AreaNodeType::Terrace)
        ->and($area->is_active)->toBeTrue()
        ->and($inactiveArea->is_active)->toBeFalse()
        ->and($openHours->is_closed)->toBeFalse()
        ->and($openHours->opens_at)->toBe('09:00')
        ->and($openHours->closes_at)->toBe('23:00')
        ->and($closedHours->is_closed)->toBeTrue()
        ->and($closedHours->opens_at)->toBeNull()
        ->and($closedHours->closes_at)->toBeNull()
        ->and($bar->type)->toBe(KitchenDepartmentType::Bar)
        ->and($bar->is_active)->toBeTrue()
        ->and($inactiveDepartment->is_active)->toBeFalse()
        ->and($pendingSession->status)->toBe(TableSessionStatus::Pending)
        ->and($waitingSession->status)->toBe(TableSessionStatus::WaitingWaiterConfirmation)
        ->and($cancelledSession->status)->toBe(TableSessionStatus::Cancelled)
        ->and($cancelledSession->ended_at)->not->toBeNull();
});

test('join request service point link waiter call and terminal order factories preserve actor state', function () {
    $tableSession = TableSession::factory()->active()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->active()->create();
    $user = User::factory()->create();

    $pendingRequest = TableSessionJoinRequest::factory()->forTableSession($tableSession)->pending()->create();
    $guestApprovedRequest = TableSessionJoinRequest::factory()->forTableSession($tableSession)->approvedByGuest($guest)->create();
    $userRejectedRequest = TableSessionJoinRequest::factory()->forTableSession($tableSession)->rejectedByUser($user)->create();
    $expiredRequest = TableSessionJoinRequest::factory()->forTableSession($tableSession)->expired()->create();

    $linkedPoint = ServicePoint::factory()->forBranch($tableSession->branch)->create();
    $activeLink = TableSessionServicePoint::factory()
        ->forTableSessionAndServicePoint($tableSession, $linkedPoint)
        ->linked()
        ->create();
    $inactiveLink = TableSessionServicePoint::factory()
        ->forTableSessionAndServicePoint($tableSession, ServicePoint::factory()->forBranch($tableSession->branch)->create())
        ->unlinkedBy($user)
        ->create();

    $pendingCall = WaiterCall::factory()->forTableSession($tableSession)->pending()->create();
    $handledCall = WaiterCall::factory()->forTableSession($tableSession)->handledBy($user)->create();

    $paymentRequestedOrder = Order::factory()->paymentRequested()->create();
    $paidOrder = Order::factory()->paid()->create();
    $closedOrder = Order::factory()->closed()->create();
    $localizedUser = User::factory()->forLocale(SupportedLocale::Lithuanian)->create();

    expect($pendingRequest->status)->toBe(TableSessionJoinRequestStatus::Pending)
        ->and($guestApprovedRequest->status)->toBe(TableSessionJoinRequestStatus::Approved)
        ->and($guestApprovedRequest->approved_by_guest_id)->toBe($guest->id)
        ->and($userRejectedRequest->status)->toBe(TableSessionJoinRequestStatus::Rejected)
        ->and($userRejectedRequest->rejected_by_user_id)->toBe($user->id)
        ->and($expiredRequest->status)->toBe(TableSessionJoinRequestStatus::Expired)
        ->and($expiredRequest->expires_at->isPast())->toBeTrue()
        ->and($activeLink->active_service_point_id)->toBe($linkedPoint->id)
        ->and($activeLink->unlinked_at)->toBeNull()
        ->and($inactiveLink->active_service_point_id)->toBeNull()
        ->and($inactiveLink->unlinked_by_user_id)->toBe($user->id)
        ->and($pendingCall->status)->toBe(WaiterCallStatus::Pending)
        ->and($pendingCall->handled_at)->toBeNull()
        ->and($handledCall->status)->toBe(WaiterCallStatus::Handled)
        ->and($handledCall->handled_by_user_id)->toBe($user->id)
        ->and($paymentRequestedOrder->status)->toBe(OrderStatus::PaymentRequested)
        ->and($paidOrder->status)->toBe(OrderStatus::Paid)
        ->and($closedOrder->status)->toBe(OrderStatus::Closed)
        ->and($localizedUser->locale)->toBe(SupportedLocale::Lithuanian->value);
});
