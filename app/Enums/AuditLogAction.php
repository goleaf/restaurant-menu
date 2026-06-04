<?php

namespace App\Enums;

enum AuditLogAction: string
{
    case MenuPriceChanged = 'menu_price_changed';
    case MenuAvailabilityChanged = 'menu_availability_changed';
    case MenuItemDeleted = 'menu_item_deleted';
    case ServicePointMoved = 'service_point_moved';
    case QrReissued = 'qr_reissued';
    case StaffPermissionChanged = 'staff_permission_changed';
    case OrderConfirmed = 'order_confirmed';
    case OrderCancelled = 'order_cancelled';
    case TableSessionClosed = 'table_session_closed';
    case PaymentRecorded = 'payment_recorded';

    public function label(): string
    {
        return match ($this) {
            self::MenuPriceChanged => 'Price changed',
            self::MenuAvailabilityChanged => 'Dish availability changed',
            self::MenuItemDeleted => 'Dish deleted',
            self::ServicePointMoved => 'Service point moved',
            self::QrReissued => 'QR reissued',
            self::StaffPermissionChanged => 'Staff permission changed',
            self::OrderConfirmed => 'Order confirmed',
            self::OrderCancelled => 'Order cancelled',
            self::TableSessionClosed => 'Table session closed',
            self::PaymentRecorded => 'Payment recorded',
        };
    }
}
