<?php

namespace App\Enums;

enum ApplicationErrorType: string
{
    case ValidationError = 'validation_error';
    case PermissionDenied = 'permission_denied';
    case BranchAccessDenied = 'branch_access_denied';
    case QrNotFound = 'qr_not_found';
    case QrDisabled = 'qr_disabled';
    case SessionClosed = 'session_closed';
    case GuestRejectedOrRemoved = 'guest_rejected_removed';
    case DraftLocked = 'draft_locked';
    case OrderInvalidTransition = 'order_invalid_transition';
    case PaymentInvalidAmount = 'payment_invalid_amount';
    case FileUploadError = 'file_upload_error';
    case SystemError = 'system_error';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $type): string => $type->value,
            self::cases(),
        );
    }

    public function statusCode(): int
    {
        return match ($this) {
            self::ValidationError,
            self::PaymentInvalidAmount,
            self::FileUploadError => 422,
            self::PermissionDenied,
            self::BranchAccessDenied,
            self::QrDisabled,
            self::GuestRejectedOrRemoved => 403,
            self::QrNotFound => 404,
            self::SessionClosed,
            self::DraftLocked,
            self::OrderInvalidTransition => 409,
            self::SystemError => 500,
        };
    }

    public function titleKey(): string
    {
        return 'errors.types.'.$this->value.'.title';
    }

    public function messageKey(): string
    {
        return 'errors.types.'.$this->value.'.message';
    }

    public function title(): string
    {
        return __($this->titleKey());
    }

    public function message(): string
    {
        return __($this->messageKey());
    }
}
