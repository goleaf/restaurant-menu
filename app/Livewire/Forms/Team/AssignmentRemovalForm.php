<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Team;

use App\Support\Validation\Common\AuditReasonRules;
use Livewire\Form;

class AssignmentRemovalForm extends Form
{
    public mixed $reason = '';

    public mixed $confirmed = false;

    /** @return array{reason: string, confirmed: bool} */
    public function validatedRemoval(): array
    {
        $this->reason = is_string($this->reason) ? trim($this->reason) : $this->reason;
        $values = $this->validate([...AuditReasonRules::auditReason('reason'), 'confirmed' => ['accepted']]);

        return ['reason' => $values['reason'], 'confirmed' => (bool) $values['confirmed']];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['reason' => __('validation.attributes.reason'), 'confirmed' => __('team.card.confirm_scope')];
    }
}
