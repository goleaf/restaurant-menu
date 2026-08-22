<?php

declare(strict_types=1);

namespace App\Enums;

enum SystemPermission: string
{
    case ViewRestaurant = 'view_restaurant';
    case EditRestaurant = 'edit_restaurant';
    case ManageBranches = 'manage_branches';
    case ManageSettings = 'manage_settings';
    case ManageZones = 'manage_zones';
    case ManageServicePoints = 'manage_service_points';
    case ManageTableSessions = 'manage_table_sessions';
    case GenerateQr = 'generate_qr';
    case ManageQr = 'manage_qr';
    case ManageMenu = 'manage_menu';
    case ChangePrices = 'change_prices';
    case ChangeAvailability = 'change_availability';
    case ViewOrders = 'view_orders';
    case ConfirmOrders = 'confirm_orders';
    case EditPendingOrders = 'edit_pending_orders';
    case CancelOrders = 'cancel_orders';
    case SendToDepartments = 'send_to_departments';
    case MarkOrderServed = 'mark_order_served';
    case ViewDepartmentOrders = 'view_department_orders';
    case MarkDepartmentReady = 'mark_department_ready';
    case SendToKitchen = 'send_to_kitchen';
    case ViewKitchen = 'view_kitchen';
    case ViewReports = 'view_reports';
    case ManageStaff = 'manage_staff';
    case ManagePermissions = 'manage_permissions';
    case ManageSubscription = 'manage_subscription';
    case ViewPayments = 'view_payments';
    case ManagePayments = 'manage_payments';
    case CorrectPayments = 'correct_payments';
    case CloseTableSessions = 'close_table_sessions';
    case ExportData = 'export_data';
    case ViewOrderHistory = 'view_order_history';
    case ViewAuditLog = 'view_audit_log';

    public function label(): string
    {
        return match ($this) {
            self::ViewRestaurant => 'View restaurant',
            self::EditRestaurant => 'Edit restaurant',
            self::ManageBranches => 'Manage branches',
            self::ManageSettings => 'Manage settings',
            self::ManageZones => 'Manage zones',
            self::ManageServicePoints => 'Manage service points',
            self::ManageTableSessions => 'Manage table sessions',
            self::GenerateQr => 'Generate QR codes',
            self::ManageQr => 'Manage QR codes',
            self::ManageMenu => 'Manage menu',
            self::ChangePrices => 'Change prices',
            self::ChangeAvailability => 'Change availability',
            self::ViewOrders => 'View orders',
            self::ConfirmOrders => 'Confirm orders',
            self::EditPendingOrders => 'Edit pending orders',
            self::CancelOrders => 'Cancel orders',
            self::SendToDepartments => 'Send to departments',
            self::MarkOrderServed => 'Mark order served',
            self::ViewDepartmentOrders => 'View department orders',
            self::MarkDepartmentReady => 'Mark department ready',
            self::SendToKitchen => 'Send to kitchen',
            self::ViewKitchen => 'View kitchen screen',
            self::ViewReports => 'View reports',
            self::ManageStaff => 'Manage staff',
            self::ManagePermissions => 'Manage permissions',
            self::ManageSubscription => 'Manage subscription',
            self::ViewPayments => 'View payments',
            self::ManagePayments => 'Manage payments',
            self::CorrectPayments => 'Correct payments',
            self::CloseTableSessions => 'Close table sessions',
            self::ExportData => 'Export data',
            self::ViewOrderHistory => 'View order history',
            self::ViewAuditLog => 'View audit log',
        };
    }

    public function uiLabelKey(): string
    {
        return 'permissions.labels.'.$this->value;
    }

    public function uiDescriptionKey(): string
    {
        return 'permissions.descriptions.'.$this->value;
    }

    public function uiGroupKey(): string
    {
        return match ($this) {
            self::ViewRestaurant,
            self::EditRestaurant,
            self::ManageSubscription,
            self::ManageSettings => 'restaurant',

            self::ManageBranches => 'branches',

            self::ManageZones => 'zones',

            self::ManageServicePoints,
            self::ManageTableSessions,
            self::CloseTableSessions => 'service_points',

            self::GenerateQr,
            self::ManageQr => 'qr',

            self::ManageMenu,
            self::ChangePrices,
            self::ChangeAvailability => 'menu',

            self::ViewOrders,
            self::ConfirmOrders,
            self::EditPendingOrders,
            self::CancelOrders,
            self::SendToDepartments,
            self::MarkOrderServed => 'orders',

            self::SendToKitchen,
            self::ViewKitchen,
            self::ViewDepartmentOrders,
            self::MarkDepartmentReady => 'departments',

            self::ViewPayments,
            self::ManagePayments,
            self::CorrectPayments => 'payments',

            self::ViewReports,
            self::ExportData => 'reports',

            self::ManageStaff,
            self::ManagePermissions => 'staff',

            self::ViewOrderHistory,
            self::ViewAuditLog => 'history',
        };
    }

    public function uiGroupLabelKey(): string
    {
        return 'permissions.groups.'.$this->uiGroupKey();
    }

    public static function resolveCode(self|string $permission): string
    {
        return $permission instanceof self ? $permission->value : $permission;
    }

    public function isCritical(): bool
    {
        return in_array($this, [
            self::ManageStaff,
            self::ManagePermissions,
            self::EditPendingOrders,
            self::ManageSubscription,
            self::ManagePayments,
            self::CorrectPayments,
            self::CloseTableSessions,
            self::ManageTableSessions,
            self::ManageQr,
            self::ManageSettings,
            self::ExportData,
        ], true);
    }

    /**
     * @return list<self>
     */
    public static function baselineForRole(SystemRole $role): array
    {
        return match ($role) {
            SystemRole::Superadmin,
            SystemRole::Owner,
            SystemRole::Director => self::cases(),

            SystemRole::RestaurantAdmin => self::without([
                self::ManageSubscription,
            ]),

            SystemRole::ShiftManager => self::unique([
                self::ViewRestaurant,
                self::ManageZones,
                self::ManageServicePoints,
                self::ManageTableSessions,
                self::CloseTableSessions,
                self::ChangeAvailability,
                self::ViewOrders,
                self::ConfirmOrders,
                self::EditPendingOrders,
                self::CancelOrders,
                self::SendToDepartments,
                self::SendToKitchen,
                self::MarkOrderServed,
                self::ViewDepartmentOrders,
                self::ViewPayments,
            ]),

            SystemRole::Waiter => self::unique([
                self::ViewRestaurant,
                self::ManageTableSessions,
                self::CloseTableSessions,
                self::ViewOrders,
                self::ConfirmOrders,
                self::EditPendingOrders,
                self::SendToDepartments,
                self::SendToKitchen,
                self::MarkOrderServed,
            ]),

            SystemRole::HeadChef => self::unique([
                self::ViewRestaurant,
                self::ViewDepartmentOrders,
                self::MarkDepartmentReady,
                self::ViewKitchen,
                self::ChangeAvailability,
            ]),

            SystemRole::Cook => self::unique([
                self::ViewRestaurant,
                self::ViewDepartmentOrders,
                self::MarkDepartmentReady,
                self::ViewKitchen,
            ]),

            SystemRole::Bartender => self::unique([
                self::ViewRestaurant,
                self::ViewOrders,
                self::SendToKitchen,
                self::ViewDepartmentOrders,
                self::MarkDepartmentReady,
            ]),

            SystemRole::Cashier => self::unique([
                self::ViewRestaurant,
                self::ViewOrders,
                self::ViewPayments,
                self::ManagePayments,
                self::CorrectPayments,
            ]),

            SystemRole::Accountant => self::unique([
                self::ViewRestaurant,
                self::ViewReports,
                self::ViewPayments,
                self::ManagePayments,
                self::CorrectPayments,
                self::ExportData,
                self::ViewOrderHistory,
            ]),

            SystemRole::Marketer => self::unique([
                self::ViewRestaurant,
                self::ManageMenu,
                self::ChangeAvailability,
            ]),
        };
    }

    /**
     * @param  list<self>  $excludedPermissions
     * @return list<self>
     */
    private static function without(array $excludedPermissions): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission): bool => ! in_array($permission, $excludedPermissions, true),
        ));
    }

    /**
     * @param  list<self>  $permissions
     * @return list<self>
     */
    private static function unique(array $permissions): array
    {
        $uniquePermissions = [];

        foreach ($permissions as $permission) {
            $uniquePermissions[$permission->value] = $permission;
        }

        return array_values($uniquePermissions);
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
     * @return list<string>
     */
    public static function uiGroupOrder(): array
    {
        return [
            'restaurant',
            'branches',
            'zones',
            'service_points',
            'qr',
            'menu',
            'orders',
            'departments',
            'payments',
            'reports',
            'staff',
            'history',
        ];
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
