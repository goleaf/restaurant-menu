<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Staff;

use App\Support\Validation\Common\AuditReasonRules;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffMemberForm extends Form
{
    public mixed $roleId = '';

    public mixed $status = 'active';

    public mixed $reason = '';

    public mixed $organizationMemberId = '';

    /** @param list<int> $roleIds @return array{roleId:int,status:string,reason:string} */
    public function validatedChange(array $roleIds): array
    {
        $values = $this->validate([
            'roleId' => ['required', 'numeric', 'integer', Rule::in($roleIds)],
            'status' => ['required', 'string', Rule::in(['active', 'suspended'])],
            ...AuditReasonRules::auditReason('reason'),
        ]);

        return ['roleId' => (int) $values['roleId'], 'status' => $values['status'], 'reason' => trim($values['reason'])];
    }

    /** @param list<int> $roleIds @return array{roleId:int,organizationMemberId:int} */
    public function validatedAssignment(array $roleIds): array
    {
        $values = $this->validate([
            'roleId' => ['required', 'numeric', 'integer', Rule::in($roleIds)],
            'organizationMemberId' => ['required', 'numeric', 'integer', 'min:1'],
        ]);

        return ['roleId' => (int) $values['roleId'], 'organizationMemberId' => (int) $values['organizationMemberId']];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['roleId' => __('staff.role'), 'status' => __('staff.workspace.status'),
            'reason' => __('validation.attributes.reason'), 'organizationMemberId' => __('staff.workspace.existing_colleague')];
    }
}
