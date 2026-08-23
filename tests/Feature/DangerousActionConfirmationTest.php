<?php

use App\Enums\DangerousAction;

test('dangerous action registry covers prompt 346 actions', function () {
    expect(collect(DangerousAction::cases())->map->value->all())->toBe([
        'reissue_qr',
        'disable_qr',
        'suspend_organization',
        'suspend_branch',
        'deactivate_staff',
        'change_critical_permission',
        'cancel_order',
        'void_order_item',
        'payment_correction',
        'close_table_with_unpaid_amount',
        'delete_or_deactivate_menu_item',
        'clear_cache_all',
        'download_backup',
        'download_media_backup',
        'restore_backup',
        'delete_media_file',
    ]);
});

test('dangerous actions declare consequences and required extra confirmation', function () {
    expect(DangerousAction::ReissueQr->consequence())->not->toBeEmpty()
        ->and(DangerousAction::ReissueQr->requiresConfirmationText())->toBeTrue()
        ->and(DangerousAction::DisableQr->requiresReason())->toBeTrue()
        ->and(DangerousAction::CancelOrder->requiresReason())->toBeTrue()
        ->and(DangerousAction::PaymentCorrection->requiresReason())->toBeTrue()
        ->and(DangerousAction::DownloadBackup->requiresReason())->toBeTrue()
        ->and(DangerousAction::DownloadMediaBackup->requiresReason())->toBeTrue()
        ->and(DangerousAction::RestoreBackup->requiresReason())->toBeTrue()
        ->and(DangerousAction::CloseTableWithUnpaidAmount->requiresConfirmationText())->toBeTrue()
        ->and(DangerousAction::DownloadBackup->requiresConfirmationText())->toBeTrue()
        ->and(DangerousAction::DownloadMediaBackup->requiresConfirmationText())->toBeTrue()
        ->and(DangerousAction::RestoreBackup->requiresConfirmationText())->toBeTrue();
});

test('dangerous confirmations use prompt 410 semantic translation keys', function () {
    $requiredKeys = [
        'ui.confirmations.danger.title',
        'ui.confirmations.danger.description',
        'ui.confirmations.delete.title',
        'ui.confirmations.delete.description',
        'qr.confirmations.disable.title',
        'qr.confirmations.disable.description',
        'qr.confirmations.reissue.title',
        'qr.confirmations.reissue.description',
        'ui.confirmations.cancel_order.title',
        'ui.confirmations.cancel_order.description',
        'ui.confirmations.void_item.title',
        'ui.confirmations.void_item.description',
        'ui.confirmations.payment_correction.title',
        'ui.confirmations.payment_correction.description',
        'ui.confirmations.close_unpaid_session.title',
        'ui.confirmations.close_unpaid_session.description',
        'ui.confirmations.deactivate_staff.title',
        'ui.confirmations.deactivate_staff.description',
        'ui.confirmations.download_media_backup.title',
        'ui.confirmations.download_media_backup.description',
        'ui.confirmations.download_media_backup.confirmation_required',
        'ui.confirmations.download_media_backup.confirmation_match',
        'ui.actions.confirm',
        'ui.actions.cancel',
        'ui.actions.continue',
        'ui.actions.i_understand',
    ];

    foreach (['en', 'lt', 'ru'] as $locale) {
        $translations = json_decode(
            file_get_contents(base_path("lang/{$locale}.json")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($requiredKeys as $key) {
            expect($translations)->toHaveKey($key);
        }
    }

    expect(DangerousAction::DisableQr->title())->toBe(__('qr.confirmations.disable.title'))
        ->and(DangerousAction::ReissueQr->consequence())->toBe(__('qr.confirmations.reissue.description'))
        ->and(DangerousAction::CancelOrder->title())->toBe(__('ui.confirmations.cancel_order.title'))
        ->and(DangerousAction::PaymentCorrection->consequence())->toBe(__('ui.confirmations.payment_correction.description'))
        ->and(DangerousAction::CloseTableWithUnpaidAmount->title())->toBe(__('ui.confirmations.close_unpaid_session.title'))
        ->and(DangerousAction::DeactivateStaff->title())->toBe(__('ui.confirmations.deactivate_staff.title'));
});
