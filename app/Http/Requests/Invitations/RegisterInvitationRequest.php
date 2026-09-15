<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitations;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Invitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Stringable;

class RegisterInvitationRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    private ?Invitation $resolvedInvitation = null;

    public function authorize(): bool
    {
        return $this->user() === null;
    }

    /**
     * @return array<string, array<int, Stringable|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $rules = [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ];
        $invitationEmail = $this->invitation()->email;

        if ($invitationEmail !== null) {
            $emailRules = ['bail'];
            foreach ($rules['email'] as $rule) {
                if ($rule instanceof Unique) {
                    $emailRules[] = Rule::in([$invitationEmail]);
                }
                $emailRules[] = $rule;
            }
            $rules['email'] = $emailRules;
        }

        return $rules;
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
    public function attributes(): array
    {
        return [
            'name' => __('ui.auth.register.full_name'),
            'email' => __('ui.auth.forgot_password.email_address'),
            'password' => __('ui.auth.confirm_password.password'),
        ];
    }

    public function invitation(): Invitation
    {
        if ($this->resolvedInvitation instanceof Invitation) {
            return $this->resolvedInvitation;
        }

        $invitationId = $this->session()->get('staff_invitation_id');
        $credential = $this->session()->get('staff_invitation_credential');
        $invitation = is_int($invitationId) ? Invitation::findAcceptableById($invitationId) : null;

        if (! $invitation instanceof Invitation || ! $invitation->matchesCredential($credential)) {
            abort(410);
        }

        $version = $this->input('invitation_version');
        if (! is_string($version) || ! hash_equals($invitation->credentialVersion(), $version)) {
            abort(410);
        }

        return $this->resolvedInvitation = $invitation;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $email = $this->input('email');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
        ]);
    }
}
