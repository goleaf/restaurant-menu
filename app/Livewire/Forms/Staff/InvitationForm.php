<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Staff;

use App\Support\Validation\RestaurantValidationRules;
use Livewire\Form;

class InvitationForm extends Form
{
    public string $email = '';

    public string $phone = '';

    public ?int $roleId = null;

    /** @return array{email: string, phone: string|null, roleId: int} */
    public function validated(mixed $roleRule): array
    {
        $this->email = str($this->email)->trim()->lower()->toString();
        $this->phone = trim($this->phone);

        /** @var array{email: string, phone: string|null, roleId: int} $validated */
        $validated = $this->validate(RestaurantValidationRules::staffInvitation($roleRule));

        return $validated;
    }

    public function clearRecipient(): void
    {
        $this->reset('email', 'phone');
    }
}
