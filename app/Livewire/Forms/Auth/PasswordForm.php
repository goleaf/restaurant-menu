<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Auth;

use Livewire\Form;

final class PasswordForm extends Form
{
    public mixed $password = '';

    public function passwordValue(): string
    {
        return $this->validate(['password' => ['required', 'string']], [], [
            'password' => __('ui.auth.confirm_password.password'),
        ])['password'];
    }

    public function clearSecrets(): void
    {
        $this->password = '';
    }
}
