<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Support\Validation\Branches\BranchSettingsRules;
use Livewire\Form;

final class BranchOrderingForm extends Form
{
    public mixed $temporarilyClosed = false;

    public mixed $temporaryClosedReason = '';

    public mixed $temporaryClosedUntil = '';

    /** @return array{closed:bool,reason:?string,until:?string} */
    public function validatedClosure(): array
    {
        $values = $this->validate(BranchSettingsRules::temporaryClosure(in_array($this->temporarilyClosed, [true, 1, '1'], true)), [], [
            'temporarilyClosed' => __('dashboard.control.ordering.pause'),
            'temporaryClosedReason' => __('dashboard.control.ordering.reason'),
            'temporaryClosedUntil' => __('dashboard.control.ordering.until'),
        ]);

        return ['closed' => (bool) $values['temporarilyClosed'], 'reason' => $values['temporaryClosedReason'] ?: null, 'until' => $values['temporaryClosedUntil'] ?: null];
    }
}
