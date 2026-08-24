<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\AcceptInvitationAction;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AcceptInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, AcceptInvitationAction $acceptInvitation): RedirectResponse
    {
        $recipient = $request->user();
        $invitationId = $request->session()->get('staff_invitation_id');
        $invitation = is_int($invitationId) ? Invitation::findAcceptableById($invitationId) : null;

        if (! $recipient instanceof User || ! $invitation instanceof Invitation) {
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

        $request->session()->forget(['staff_invitation_id', 'url.intended']);

        return redirect()->route('dashboard')->with('status', __('invitations.messages.accepted'));
    }
}
