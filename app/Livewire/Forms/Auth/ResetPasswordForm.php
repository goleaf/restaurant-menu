<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Auth;

use App\Concerns\PasswordValidationRules;
use Livewire\Form;

final class ResetPasswordForm extends Form
{
    use PasswordValidationRules;

    public mixed $email = '';

    public mixed $password = '';

    public mixed $password_confirmation = '';

    /** @return array{email: string, password: string, password_confirmation: string} */
    public function credentials(): array
    {
        return $this->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
        ], [], ['email' => __('ui.auth.forgot_password.email_address'), 'password' => __('ui.auth.confirm_password.password')]);
    }

    public function clearSecrets(): void
    {
        $this->password = '';
        $this->password_confirmation = '';
    }
}
