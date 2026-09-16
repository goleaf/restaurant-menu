<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitations;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AcceptInvitationRequest extends FormRequest
{
    private ?Invitation $resolvedInvitation = null;

    public function authorize(): bool
    {
        $recipient = $this->user();
        if (! $recipient instanceof User) {
            return false;
        }

        $id = $this->session()->get('staff_invitation_id');
        $credential = $this->session()->get('staff_invitation_credential');
        $invitation = is_int($id) ? Invitation::findAcceptableById($id) : null;
        abort_unless($invitation instanceof Invitation && $invitation->matchesCredential($credential), 410);
        abort_unless(Gate::forUser($recipient)->allows('accept', $invitation), 410);
        $this->resolvedInvitation = $invitation;

        return true;
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return array_intersect_key($this->request->all(), $this->rules());
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['invitation_version' => ['required', 'string', 'size:64']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['invitation_version' => __('invitations.title')];
    }

    public function invitation(): Invitation
    {
        $invitation = $this->resolvedInvitation;
        abort_unless($invitation instanceof Invitation, 410);
        abort_unless(hash_equals($invitation->credentialVersion(), $this->validated('invitation_version')), 410);

        return $invitation;
    }
}
