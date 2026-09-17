<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Auth;

use Livewire\Form;

final class LoginForm extends Form
{
    public mixed $email = '';

    public mixed $password = '';

    public mixed $remember = false;

    /** @return array{email: string, password: string, remember: bool} */
    public function credentials(): array
    {
        $values = $this->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ], [], ['email' => __('ui.auth.forgot_password.email_address'), 'password' => __('ui.auth.confirm_password.password')]);

        return ['email' => $values['email'], 'password' => $values['password'], 'remember' => (bool) $values['remember']];
    }

    public function clearSecrets(): void
    {
        $this->password = '';
    }
}
