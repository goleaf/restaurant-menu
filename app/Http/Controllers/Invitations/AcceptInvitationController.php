<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\ResolveInvitationDestinationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AcceptInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AcceptInvitationRequest $request, AcceptInvitationAction $acceptInvitation, ResolveInvitationDestinationAction $destination): RedirectResponse
    {
        $recipient = $request->user();
        $invitationId = $request->session()->get('staff_invitation_id');
        $credential = $request->session()->get('staff_invitation_credential');
        $invitation = is_int($invitationId) ? Invitation::findAcceptableById($invitationId) : null;

        if (! $recipient instanceof User || ! $invitation instanceof Invitation || ! $invitation->matchesCredential($credential)) {
            abort(410);
        }

        if (! hash_equals($invitation->credentialVersion(), $request->validated('invitation_version'))) {
            abort(410);
        }

        if (Gate::forUser($recipient)->denies('accept', $invitation)) {
            abort(410);
        }

        try {
            $acceptInvitation->handle($invitation, $recipient);
        } catch (DomainException) {
            abort(410);
        }

        $request->session()->forget(['staff_invitation_id', 'staff_invitation_state', 'staff_invitation_credential', 'url.intended']);

        return redirect()->to($destination->handle($invitation, $recipient))->with('status', __('invitations.messages.accepted'));
    }
}
