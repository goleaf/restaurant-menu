<?php

declare(strict_types=1);

namespace App\Enums;

enum DangerousAction: string
{
    case ReissueQr = 'reissue_qr';
    case DisableQr = 'disable_qr';
    case SuspendOrganization = 'suspend_organization';
    case SuspendBranch = 'suspend_branch';
    case DeactivateStaff = 'deactivate_staff';
    case ChangeStaffRole = 'change_staff_role';
    case CancelInvitation = 'cancel_invitation';
    case ChangeCriticalPermission = 'change_critical_permission';
    case CancelOrder = 'cancel_order';
    case VoidOrderItem = 'void_order_item';
    case PaymentCorrection = 'payment_correction';
    case CloseTableWithUnpaidAmount = 'close_table_with_unpaid_amount';
    case DeleteOrDeactivateMenuItem = 'delete_or_deactivate_menu_item';
    case ClearCacheAll = 'clear_cache_all';
    case DownloadBackup = 'download_backup';
    case DownloadMediaBackup = 'download_media_backup';
    case RestoreBackup = 'restore_backup';
    case DeleteMediaFile = 'delete_media_file';

    public function title(): string
    {
        return match ($this) {
            self::ReissueQr => __('qr.confirmations.reissue.title'),
            self::DisableQr => __('qr.confirmations.disable.title'),
            self::SuspendOrganization => __('ui.confirmations.suspend_organization.title'),
            self::SuspendBranch => __('ui.confirmations.suspend_branch.title'),
            self::DeactivateStaff => __('ui.confirmations.deactivate_staff.title'),
            self::ChangeStaffRole => __('ui.confirmations.change_staff_role.title'),
            self::CancelInvitation => __('ui.confirmations.cancel_invitation.title'),
            self::ChangeCriticalPermission => __('ui.confirmations.change_critical_permission.title'),
            self::CancelOrder => __('ui.confirmations.cancel_order.title'),
            self::VoidOrderItem => __('ui.confirmations.void_item.title'),
            self::PaymentCorrection => __('ui.confirmations.payment_correction.title'),
            self::CloseTableWithUnpaidAmount => __('ui.confirmations.close_unpaid_session.title'),
            self::DeleteOrDeactivateMenuItem => __('ui.confirmations.delete_or_deactivate_menu_item.title'),
            self::ClearCacheAll => __('ui.confirmations.clear_cache_all.title'),
            self::DownloadBackup => __('ui.confirmations.download_backup.title'),
            self::DownloadMediaBackup => __('ui.confirmations.download_media_backup.title'),
            self::RestoreBackup => __('ui.confirmations.restore_backup.title'),
            self::DeleteMediaFile => __('ui.confirmations.delete_media_file.title'),
        };
    }

    public function consequence(): string
    {
        return match ($this) {
            self::ReissueQr => __('qr.confirmations.reissue.description'),
            self::DisableQr => __('qr.confirmations.disable.description'),
            self::SuspendOrganization => __('ui.confirmations.suspend_organization.description'),
            self::SuspendBranch => __('ui.confirmations.suspend_branch.description'),
            self::DeactivateStaff => __('ui.confirmations.deactivate_staff.description'),
            self::ChangeStaffRole => __('ui.confirmations.change_staff_role.description'),
            self::CancelInvitation => __('ui.confirmations.cancel_invitation.description'),
            self::ChangeCriticalPermission => __('ui.confirmations.change_critical_permission.description'),
            self::CancelOrder => __('ui.confirmations.cancel_order.description'),
            self::VoidOrderItem => __('ui.confirmations.void_item.description'),
            self::PaymentCorrection => __('ui.confirmations.payment_correction.description'),
            self::CloseTableWithUnpaidAmount => __('ui.confirmations.close_unpaid_session.description'),
            self::DeleteOrDeactivateMenuItem => __('ui.confirmations.delete_or_deactivate_menu_item.description'),
            self::ClearCacheAll => __('ui.confirmations.clear_cache_all.description'),
            self::DownloadBackup => __('ui.confirmations.download_backup.description'),
            self::DownloadMediaBackup => __('ui.confirmations.download_media_backup.description'),
            self::RestoreBackup => __('ui.confirmations.restore_backup.description'),
            self::DeleteMediaFile => __('ui.confirmations.delete_media_file.description'),
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [
            self::DisableQr,
            self::SuspendOrganization,
            self::SuspendBranch,
            self::DeactivateStaff,
            self::ChangeStaffRole,
            self::ChangeCriticalPermission,
            self::CancelOrder,
            self::VoidOrderItem,
            self::PaymentCorrection,
            self::DownloadBackup,
            self::DownloadMediaBackup,
            self::RestoreBackup,
        ], true);
    }

    public function requiresConfirmationText(): bool
    {
        return in_array($this, [
            self::ReissueQr,
            self::CloseTableWithUnpaidAmount,
            self::ClearCacheAll,
            self::DownloadBackup,
            self::DownloadMediaBackup,
            self::RestoreBackup,
        ], true);
    }
}
