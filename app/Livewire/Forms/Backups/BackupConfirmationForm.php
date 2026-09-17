<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Backups;

use App\Support\PlainText;
use App\Support\Validation\Common\AuditReasonRules;
use Livewire\Form;

final class BackupConfirmationForm extends Form
{
    public mixed $reason = '';

    public mixed $confirmation = '';

    public function reasonFor(string $kind): string
    {
        [$word, $required, $mismatch] = match ($kind) {
            'sqlite' => ['BACKUP', __('ui.confirmations.download_backup.confirmation_required'), __('ui.confirmations.download_backup.confirmation_match')],
            'media' => ['MEDIA', __('ui.confirmations.download_media_backup.confirmation_required'), __('ui.confirmations.download_media_backup.confirmation_match')],
            'restore' => ['RESTORE', __('ui.confirmations.restore_backup.confirmation_required'), __('ui.confirmations.restore_backup.confirmation_match')],
            default => throw new \InvalidArgumentException('Unsupported backup confirmation kind.'),
        };
        if (is_string($this->reason)) {
            $this->reason = trim($this->reason);
        }
        $data = $this->validate([...AuditReasonRules::auditReason('reason'), 'confirmation' => ['required', 'string', 'in:'.$word]], [
            'reason.required' => __('ui.confirmations.reason.required'),
            'reason.min' => __('ui.confirmations.reason.min'),
            'confirmation.required' => $required,
            'confirmation.in' => $mismatch,
        ]);

        return PlainText::required($data['reason'], 500);
    }
}
