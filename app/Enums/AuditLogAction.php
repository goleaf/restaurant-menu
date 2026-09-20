<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditLogAction: string
{
    case BranchSettingsChanged = 'branch_settings_changed';
    case TableSessionInactivityCleanup = 'table_session_inactivity_cleanup';

    case AreaNodeChanged = 'area_node_changed';
    case ServicePointChanged = 'service_point_changed';

    case McpTokenIssued = 'mcp_token_issued';
    case McpTokenRevoked = 'mcp_token_revoked';
    case DishConfigurationChanged = 'dish_configuration_changed';
    case MenuPriceChanged = 'menu_price_changed';
    case MenuAvailabilityChanged = 'menu_availability_changed';
    case RestaurantSetupCreated = 'restaurant_setup_created';
    case BranchAvailabilityChanged = 'branch_availability_changed';
    case MenuScheduleChanged = 'menu_schedule_changed';
    case MenuItemDeleted = 'menu_item_deleted';
    case ServicePointMoved = 'service_point_moved';
    case ServicePointDeleted = 'service_point_deleted';
    case QrDisabled = 'qr_disabled';
    case QrGenerated = 'qr_generated';
    case QrReissued = 'qr_reissued';
    case StaffPermissionChanged = 'staff_permission_changed';
    case StaffRoleChanged = 'staff_role_changed';
    case StaffDeactivated = 'staff_deactivated';
    case StaffReactivated = 'staff_reactivated';
    case InvitationCreated = 'invitation_created';
    case InvitationReissued = 'invitation_reissued';
    case InvitationAccepted = 'invitation_accepted';
    case InvitationCancelled = 'invitation_cancelled';
    case BranchSuspended = 'branch_suspended';
    case OrganizationSubscriptionChanged = 'organization_subscription_changed';
    case BackupDownloaded = 'backup_downloaded';
    case MediaBackupDownloaded = 'media_backup_downloaded';
    case BackupRestored = 'backup_restored';
    case DraftOrderEditedByWaiter = 'draft_order_edited_by_waiter';
    case OrderConfirmed = 'order_confirmed';
    case DraftOrderRejected = 'draft_order_rejected';
    case DepartmentItemReady = 'department_item_ready';
    case OrderItemVoided = 'order_item_voided';
    case OrderCancelled = 'order_cancelled';
    case TableSessionTransferred = 'table_session_transferred';
    case TableSessionServicePointLinked = 'table_session_service_point_linked';
    case TableSessionClosed = 'table_session_closed';
    case TableSessionGuestLeft = 'table_session_guest_left';
    case TableSessionGuestRemoved = 'table_session_guest_removed';
    case PaymentRecorded = 'payment_recorded';
    case PaymentCorrected = 'payment_corrected';

    public function label(): string
    {
        return match ($this) {
            self::BranchSettingsChanged => __('settings.audit.changed'),
            self::TableSessionInactivityCleanup => __('settings_center.audit.session_cleanup'),
            self::AreaNodeChanged => __('floor.audit.area_changed'),
            self::ServicePointChanged => __('floor.audit.point_changed'),
            self::McpTokenIssued => __('mcp.audit.token_issued'),
            self::McpTokenRevoked => __('mcp.audit.token_revoked'),
            self::DishConfigurationChanged => __('dish.audit.configuration_changed'),
            self::MenuPriceChanged => 'Price changed',
            self::MenuAvailabilityChanged => 'Dish availability changed',
            self::RestaurantSetupCreated => __('center.audit.created'),
            self::BranchAvailabilityChanged => __('availability.audit.branch_changed'),
            self::MenuScheduleChanged => __('availability.audit.menu_schedule_changed'),
            self::MenuItemDeleted => 'Dish deleted',
            self::ServicePointMoved => 'Service point moved',
            self::ServicePointDeleted => 'Service point deleted',
            self::QrDisabled => 'QR disabled',
            self::QrGenerated => __('floor.audit.qr_created'),
            self::QrReissued => 'QR reissued',
            self::StaffPermissionChanged => __('audit.actions.staff_permission_changed'),
            self::StaffRoleChanged => __('audit.actions.staff_role_changed'),
            self::StaffDeactivated => __('audit.actions.staff_deactivated'),
            self::StaffReactivated => __('audit.actions.staff_reactivated'),
            self::InvitationCreated => __('audit.actions.invitation_created'),
            self::InvitationReissued => __('audit.actions.invitation_reissued'),
            self::InvitationAccepted => __('audit.actions.invitation_accepted'),
            self::InvitationCancelled => __('audit.actions.invitation_cancelled'),
            self::BranchSuspended => 'Branch suspended',
            self::OrganizationSubscriptionChanged => 'Organization subscription changed',
            self::BackupDownloaded => 'Backup downloaded',
            self::MediaBackupDownloaded => __('audit.actions.media_backup_downloaded'),
            self::BackupRestored => 'Backup restored',
            self::DraftOrderEditedByWaiter => 'Waiter draft edited',
            self::OrderConfirmed => 'Order confirmed',
            self::DraftOrderRejected => 'Draft order rejected',
            self::DepartmentItemReady => 'Department item ready',
            self::OrderItemVoided => 'Order item voided',
            self::OrderCancelled => 'Order cancelled',
            self::TableSessionTransferred => 'Table session transferred',
            self::TableSessionServicePointLinked => 'Table session service point linked',
            self::TableSessionClosed => 'Table session closed',
            self::TableSessionGuestLeft => __('audit.actions.table_session_guest_left'),
            self::TableSessionGuestRemoved => __('audit.actions.table_session_guest_removed'),
            self::PaymentRecorded => 'Payment recorded',
            self::PaymentCorrected => 'Payment corrected',
        };
    }
}
