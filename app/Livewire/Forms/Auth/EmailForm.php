<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Auth;

use Livewire\Form;

final class EmailForm extends Form
{
    public mixed $email = '';

    public function emailAddress(): string
    {
        return $this->validate(['email' => ['required', 'string', 'email', 'max:255']], [], [
            'email' => __('ui.auth.forgot_password.email_address'),
        ])['email'];
    }
}
