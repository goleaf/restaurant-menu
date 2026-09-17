<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Auth;

use Livewire\Form;

final class TwoFactorForm extends Form
{
    public mixed $code = '';

    public mixed $recovery_code = '';

    /** @return array{code: ?string, recovery_code: ?string} */
    public function credentials(): array
    {
        return $this->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'regex:/^[0-9]{6}$/D'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:255'],
        ], [], ['code' => __('ui.auth.two_factor_challenge.authentication_code'), 'recovery_code' => __('ui.auth.two_factor_challenge.recovery_code')]);
    }

    public function clearSecrets(): void
    {
        $this->code = '';
        $this->recovery_code = '';
    }
}
