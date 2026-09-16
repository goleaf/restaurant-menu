<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\ResolveInvitationDestinationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\AcceptInvitationRequest;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;

class AcceptInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AcceptInvitationRequest $request, AcceptInvitationAction $acceptInvitation, ResolveInvitationDestinationAction $destination): RedirectResponse
    {
        $recipient = $request->user();
        abort_unless($recipient instanceof User, 403);
        $invitation = $request->invitation();

        try {
            $acceptInvitation->handle($invitation, $recipient);
        } catch (DomainException) {
            abort(410);
        }

        $request->session()->forget(['staff_invitation_id', 'staff_invitation_state', 'staff_invitation_credential', 'url.intended']);

        return redirect()->to($destination->handle($invitation, $recipient))->with('status', __('invitations.messages.accepted'));
    }
}
