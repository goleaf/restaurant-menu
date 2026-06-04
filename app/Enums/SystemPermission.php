<?php

namespace App\Enums;

enum SystemPermission: string
{
    case ViewRestaurant = 'view_restaurant';
    case EditRestaurant = 'edit_restaurant';
    case ManageBranches = 'manage_branches';
    case ManageZones = 'manage_zones';
    case ManageServicePoints = 'manage_service_points';
    case GenerateQr = 'generate_qr';
    case ManageMenu = 'manage_menu';
    case ChangePrices = 'change_prices';
    case ChangeAvailability = 'change_availability';
    case ViewOrders = 'view_orders';
    case ConfirmOrders = 'confirm_orders';
    case EditPendingOrders = 'edit_pending_orders';
    case CancelOrders = 'cancel_orders';
    case SendToKitchen = 'send_to_kitchen';
    case ViewKitchen = 'view_kitchen';
    case ViewReports = 'view_reports';
    case ManageStaff = 'manage_staff';
    case ManageSubscription = 'manage_subscription';
    case ViewPayments = 'view_payments';
    case ExportData = 'export_data';
    case ManageSettings = 'manage_settings';
    case ViewAuditLog = 'view_audit_log';

    public function label(): string
    {
        return match ($this) {
            self::ViewRestaurant => 'View restaurant',
            self::EditRestaurant => 'Edit restaurant',
            self::ManageBranches => 'Manage branches',
            self::ManageZones => 'Manage zones',
            self::ManageServicePoints => 'Manage service points',
            self::GenerateQr => 'Generate QR codes',
            self::ManageMenu => 'Manage menu',
            self::ChangePrices => 'Change prices',
            self::ChangeAvailability => 'Change availability',
            self::ViewOrders => 'View orders',
            self::ConfirmOrders => 'Confirm orders',
            self::EditPendingOrders => 'Edit pending orders',
            self::CancelOrders => 'Cancel orders',
            self::SendToKitchen => 'Send to kitchen',
            self::ViewKitchen => 'View kitchen screen',
            self::ViewReports => 'View reports',
            self::ManageStaff => 'Manage staff',
            self::ManageSubscription => 'Manage subscription',
            self::ViewPayments => 'View payments',
            self::ExportData => 'Export data',
            self::ManageSettings => 'Manage settings',
            self::ViewAuditLog => 'View audit log',
        };
    }

    public static function resolveCode(self|string $permission): string
    {
        return $permission instanceof self ? $permission->value : $permission;
    }

    public function isCritical(): bool
    {
        return in_array($this, [
            self::ManageStaff,
            self::EditPendingOrders,
            self::ManageSubscription,
            self::ManageSettings,
            self::ExportData,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(
            fn (self $permission): string => $permission->label(),
            self::cases(),
        );
    }

    /**
     * @return array<int, array{code: string, name: string, sort_order: int}>
     */
    public static function seedRows(): array
    {
        return array_map(
            fn (self $permission, int $index): array => [
                'code' => $permission->value,
                'name' => $permission->label(),
                'sort_order' => $index + 1,
            ],
            self::cases(),
            array_keys(self::cases()),
        );
    }
}
