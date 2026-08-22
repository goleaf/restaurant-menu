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
            $rules['email'][] = Rule::in([$invitationEmail]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.in' => __('invitations.validation.email_mismatch'),
        ];
    }

    public function invitation(): Invitation
    {
        if ($this->resolvedInvitation instanceof Invitation) {
            return $this->resolvedInvitation;
        }

        $invitationId = $this->session()->get('staff_invitation_id');
        $invitation = is_int($invitationId) ? Invitation::findAcceptableById($invitationId) : null;

        if (! $invitation instanceof Invitation) {
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
