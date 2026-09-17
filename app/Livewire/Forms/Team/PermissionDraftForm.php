<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Team;

use App\Support\Validation\PermissionDraftRules;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class PermissionDraftForm extends Form
{
    public mixed $states = [];

    public mixed $reason = '';

    public mixed $confirmed = false;

    /** @return list<array{permission_id: int, state: string}> */
    public function validatedChanges(): array
    {
        $validated = $this->validate([
            'states' => ['required', 'array', 'min:1', 'max:100'],
            'states.*' => ['required', 'string', 'in:default,allow,deny'],
        ]);
        $changes = [];
        foreach ($validated['states'] as $permissionId => $state) {
            if (! ctype_digit((string) $permissionId) || (int) $permissionId < 1) {
                throw ValidationException::withMessages([$this->getPropertyName().'.states' => __('permissions.errors.invalid_state')]);
            }
            $changes[] = ['permission_id' => (int) $permissionId, 'state' => (string) $state];
        }

        return $changes;
    }

    /** @return array{reason: string|null, confirmed: bool} */
    public function validatedConfirmation(bool $critical): array
    {
        $this->reason = is_string($this->reason) ? trim($this->reason) : $this->reason;
        $validated = $this->validate(PermissionDraftRules::confirmation($critical));

        return ['reason' => $validated['reason'] === '' ? null : $validated['reason'], 'confirmed' => (bool) $validated['confirmed']];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['states' => __('permissions.labels.manage_permissions'), 'states.*' => __('permissions.labels.manage_permissions'), 'reason' => __('permissions.forms.change_reason'), 'confirmed' => __('permissions.draft.confirm')];
    }
}
