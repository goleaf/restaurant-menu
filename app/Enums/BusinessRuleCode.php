<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessRuleCode: string
{
    case SessionClosed = 'session_closed';
    case DraftLocked = 'draft_locked';
    case GuestNotActive = 'guest_not_active';
    case GuestNotApproved = 'guest_not_approved';
    case OrderAlreadyCancelled = 'order_already_cancelled';
    case OrderItemAlreadyCancelled = 'order_item_already_cancelled';
    case OrderItemNotCancellable = 'order_item_not_cancellable';
    case PaymentAlreadyRecorded = 'payment_already_recorded';
    case DepartmentAlreadyReady = 'department_already_ready';
    case PaymentExceedsRemaining = 'payment_exceeds_remaining';
    case PaymentNotAllowed = 'payment_not_allowed';
    case QrDisabled = 'qr_disabled';
    case BranchInaccessible = 'branch_inaccessible';
    case ItemUnavailable = 'item_unavailable';
    case RequiredModifierMissing = 'required_modifier_missing';
    case ServicePointHasActiveSession = 'service_point_has_active_session';
    case StructureHasActiveOrder = 'structure_has_active_order';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $code): string => $code->value,
            self::cases(),
        );
    }

    public function message(): string
    {
        return match ($this) {
            self::SessionClosed => __('ui.enums.businessrulecode.nelzia_vypolnit_deistvie_dlia_zakrytogo_stola'),
            self::DraftLocked => __('ui.enums.businessrulecode.cernovik_seicas_nelzia_izmenit'),
            self::GuestNotActive => __('ui.actions.waiter.adddraftorderitembywaiteraction.vyberite_aktivnogo_gostia'),
            self::GuestNotApproved => __('ui.actions.waiter.adddraftorderitembywaiteraction.gost_eshhe_ne_podtverzden'),
            self::OrderAlreadyCancelled => __('ui.actions.orders.changeorderstatusaction.zakaz_uze_otmenen'),
            self::OrderItemAlreadyCancelled => __('orders.items.errors.already_cancelled'),
            self::OrderItemNotCancellable => __('orders.items.errors.not_cancellable'),
            self::PaymentAlreadyRecorded => __('orders.items.errors.payment_recorded'),
            self::DepartmentAlreadyReady => __('ui.actions.departments.updatedepartmentticketitemstatusaction.poziciia_uze'),
            self::PaymentExceedsRemaining => __('payments.errors.amount_exceeds_remaining'),
            self::PaymentNotAllowed => __('payments.errors.session_not_payable'),
            self::QrDisabled => __('qr.errors.disabled.title'),
            self::BranchInaccessible => __('ui.enums.businessrulecode.u_vas_net_dostupa_k_etomu_filialu'),
            self::ItemUnavailable => __('ui.enums.businessrulecode.eta_poziciia_seicas_nedostupna'),
            self::RequiredModifierMissing => __('ui.enums.businessrulecode.vyberite_obiazatelnyi_variant'),
            self::ServicePointHasActiveSession => __('service_points.errors.active_session_delete'),
            self::StructureHasActiveOrder => __('structure.errors.active_order_delete'),
        };
    }

    public function errorType(): ApplicationErrorType
    {
        return match ($this) {
            self::SessionClosed => ApplicationErrorType::SessionClosed,
            self::DraftLocked => ApplicationErrorType::DraftLocked,
            self::GuestNotActive,
            self::GuestNotApproved => ApplicationErrorType::GuestRejectedOrRemoved,
            self::OrderAlreadyCancelled,
            self::OrderItemAlreadyCancelled,
            self::OrderItemNotCancellable,
            self::PaymentAlreadyRecorded,
            self::DepartmentAlreadyReady => ApplicationErrorType::OrderInvalidTransition,
            self::PaymentExceedsRemaining => ApplicationErrorType::PaymentInvalidAmount,
            self::PaymentNotAllowed => ApplicationErrorType::OrderInvalidTransition,
            self::QrDisabled => ApplicationErrorType::QrDisabled,
            self::BranchInaccessible => ApplicationErrorType::BranchAccessDenied,
            self::ItemUnavailable,
            self::RequiredModifierMissing => ApplicationErrorType::ValidationError,
            self::ServicePointHasActiveSession,
            self::StructureHasActiveOrder => ApplicationErrorType::ValidationError,
        };
    }
}
