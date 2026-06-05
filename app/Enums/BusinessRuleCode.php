<?php

namespace App\Enums;

enum BusinessRuleCode: string
{
    case SessionClosed = 'session_closed';
    case DraftLocked = 'draft_locked';
    case GuestNotActive = 'guest_not_active';
    case GuestNotApproved = 'guest_not_approved';
    case OrderAlreadyCancelled = 'order_already_cancelled';
    case DepartmentAlreadyReady = 'department_already_ready';
    case PaymentExceedsRemaining = 'payment_exceeds_remaining';
    case QrDisabled = 'qr_disabled';
    case BranchInaccessible = 'branch_inaccessible';
    case ItemUnavailable = 'item_unavailable';
    case RequiredModifierMissing = 'required_modifier_missing';

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
            self::SessionClosed => __('Нельзя выполнить действие для закрытого стола.'),
            self::DraftLocked => __('Черновик сейчас нельзя изменить.'),
            self::GuestNotActive => __('Выберите активного гостя за этим столом.'),
            self::GuestNotApproved => __('Гость ещё не подтверждён для этого стола.'),
            self::OrderAlreadyCancelled => __('Заказ уже отменён.'),
            self::DepartmentAlreadyReady => __('Позиция уже отмечена готовой.'),
            self::PaymentExceedsRemaining => __('payments.errors.amount_exceeds_remaining'),
            self::QrDisabled => __('qr.errors.disabled.title'),
            self::BranchInaccessible => __('У вас нет доступа к этому филиалу.'),
            self::ItemUnavailable => __('Эта позиция сейчас недоступна.'),
            self::RequiredModifierMissing => __('Выберите обязательный вариант.'),
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
            self::DepartmentAlreadyReady => ApplicationErrorType::OrderInvalidTransition,
            self::PaymentExceedsRemaining => ApplicationErrorType::PaymentInvalidAmount,
            self::QrDisabled => ApplicationErrorType::QrDisabled,
            self::BranchInaccessible => ApplicationErrorType::BranchAccessDenied,
            self::ItemUnavailable,
            self::RequiredModifierMissing => ApplicationErrorType::ValidationError,
        };
    }
}
