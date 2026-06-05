<?php

namespace App\Enums;

enum AuditLogAction: string
{
    case MenuPriceChanged = 'menu_price_changed';
    case MenuAvailabilityChanged = 'menu_availability_changed';
    case MenuItemDeleted = 'menu_item_deleted';
    case ServicePointMoved = 'service_point_moved';
    case QrDisabled = 'qr_disabled';
    case QrReissued = 'qr_reissued';
    case StaffPermissionChanged = 'staff_permission_changed';
    case StaffDeactivated = 'staff_deactivated';
    case BranchSuspended = 'branch_suspended';
    case OrganizationSubscriptionChanged = 'organization_subscription_changed';
    case BackupDownloaded = 'backup_downloaded';
    case DraftOrderEditedByWaiter = 'draft_order_edited_by_waiter';
    case OrderConfirmed = 'order_confirmed';
    case DraftOrderRejected = 'draft_order_rejected';
    case DepartmentItemReady = 'department_item_ready';
    case OrderItemVoided = 'order_item_voided';
    case OrderCancelled = 'order_cancelled';
    case TableSessionTransferred = 'table_session_transferred';
    case TableSessionServicePointLinked = 'table_session_service_point_linked';
    case TableSessionClosed = 'table_session_closed';
    case PaymentRecorded = 'payment_recorded';
    case PaymentCorrected = 'payment_corrected';

    public function label(): string
    {
        return match ($this) {
            self::MenuPriceChanged => 'Price changed',
            self::MenuAvailabilityChanged => 'Dish availability changed',
            self::MenuItemDeleted => 'Dish deleted',
            self::ServicePointMoved => 'Service point moved',
            self::QrDisabled => 'QR disabled',
            self::QrReissued => 'QR reissued',
            self::StaffPermissionChanged => 'Staff permission changed',
            self::StaffDeactivated => 'Staff deactivated',
            self::BranchSuspended => 'Branch suspended',
            self::OrganizationSubscriptionChanged => 'Organization subscription changed',
            self::BackupDownloaded => 'Backup downloaded',
            self::DraftOrderEditedByWaiter => 'Waiter draft edited',
            self::OrderConfirmed => 'Order confirmed',
            self::DraftOrderRejected => 'Draft order rejected',
            self::DepartmentItemReady => 'Department item ready',
            self::OrderItemVoided => 'Order item voided',
            self::OrderCancelled => 'Order cancelled',
            self::TableSessionTransferred => 'Table session transferred',
            self::TableSessionServicePointLinked => 'Table session service point linked',
            self::TableSessionClosed => 'Table session closed',
            self::PaymentRecorded => 'Payment recorded',
            self::PaymentCorrected => 'Payment corrected',
        };
    }
}
