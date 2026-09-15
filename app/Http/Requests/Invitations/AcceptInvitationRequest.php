<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitations;

use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['invitation_version' => ['required', 'string', 'size:64']];
    }
}
