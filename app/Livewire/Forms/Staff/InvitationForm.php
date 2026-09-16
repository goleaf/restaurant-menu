<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Staff;

use App\Support\Validation\Staff\InvitationRules;
use Livewire\Form;

class InvitationForm extends Form
{
    public mixed $email = '';

    public mixed $phone = '';

    public mixed $roleId = null;

    public mixed $expiresInDays = '7';

    /** @return array{email: string, phone: string|null, roleId: int, expiresInDays: int} */
    public function validated(mixed $roleRule): array
    {
        $this->email = is_string($this->email) ? str($this->email)->trim()->lower()->toString() : $this->email;
        $this->phone = is_string($this->phone) ? trim($this->phone) : $this->phone;

        /** @var array{email: string, phone: string|null, roleId: int, expiresInDays: int} $validated */
        $validated = $this->validate(InvitationRules::staffInvitation($roleRule));
        $validated['roleId'] = (int) $validated['roleId'];
        $validated['expiresInDays'] = (int) $validated['expiresInDays'];

        return $validated;
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['email' => __('ui.auth.forgot_password.email_address'), 'phone' => __('validation.attributes.phone'), 'roleId' => __('staff.role'), 'expiresInDays' => __('staff.fields.invitation_expiry_days')];
    }

    public function clearRecipient(): void
    {
        $this->reset('email', 'phone');
    }
}
