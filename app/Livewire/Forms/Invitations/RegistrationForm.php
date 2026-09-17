<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Invitations;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RegistrationForm extends Form
{
    use PasswordValidationRules, ProfileValidationRules;

    public mixed $name = '';

    public mixed $email = '';

    public mixed $password = '';

    public mixed $password_confirmation = '';

    /** @return array{name: string, email: string, password: string} */
    public function validatedFor(Invitation $invitation): array
    {
        $this->name = is_string($this->name) ? trim($this->name) : $this->name;
        $this->email = is_string($this->email) ? Str::lower(trim($this->email)) : $this->email;
        $data = $this->validate([
            'name' => $this->nameRules(),
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::in([$invitation->email]), Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ], $this->messages(), $this->validationAttributes());

        return ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']];
    }

    public function clearPasswords(): void
    {
        $this->password = '';
        $this->password_confirmation = '';
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('invitations.validation.required'),
            'name.string' => __('invitations.validation.string'),
            'name.max' => __('invitations.validation.max'),
            'email.required' => __('invitations.validation.required'),
            'email.string' => __('invitations.validation.string'),
            'email.max' => __('invitations.validation.max'),
            'email.email' => __('invitations.validation.email'),
            'email.unique' => __('invitations.validation.email_unique'),
            'email.in' => __('invitations.validation.email_mismatch'),
            'password.required' => __('invitations.validation.required'),
            'password.string' => __('invitations.validation.string'),
            'password.confirmed' => __('invitations.validation.password_confirmed'),
            'password.min' => __('invitations.validation.password_min'),
            'password.max' => __('invitations.validation.password_max'),
            'password.password.letters' => __('invitations.validation.password_letters'),
            'password.password.mixed' => __('invitations.validation.password_mixed'),
            'password.password.numbers' => __('invitations.validation.password_numbers'),
            'password.password.symbols' => __('invitations.validation.password_symbols'),
            'password.password.uncompromised' => __('invitations.validation.password_uncompromised'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('ui.auth.register.full_name'),
            'email' => __('ui.auth.forgot_password.email_address'),
            'password' => __('ui.auth.confirm_password.password'),
        ];
    }
}
