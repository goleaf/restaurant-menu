<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\ResolvedInvitationAccess;
use App\Actions\Invitations\ResolveInvitationAccessAction;
use App\Enums\InvitationAccessState;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Invitations\InvitationPagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShowInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        ResolveInvitationAccessAction $resolveInvitation,
        InvitationPagePresenter $presenter,
        ?string $token = null,
    ): RedirectResponse|Response {
        if ($token !== null) {
            $request->session()->forget(['staff_invitation_id', 'staff_invitation_state', 'staff_invitation_credential']);
            $access = $resolveInvitation->byToken($token, $this->recipient($request));

            $request->session()->put('staff_invitation_state', $access->state->sessionValue());

            if ($access->invitation instanceof Invitation) {
                $request->session()->put('staff_invitation_id', $access->invitation->id);
                $request->session()->put('staff_invitation_credential', hash('sha256', $token));
            }

            if ($access->state === InvitationAccessState::Pending && ! $this->recipient($request) instanceof User) {
                $request->session()->put('url.intended', route('invitations.pending'));
            }

            return redirect()->route('invitations.pending');
        }

        $access = $this->pendingAccess($request, $resolveInvitation);

        if ($access->state !== InvitationAccessState::Pending || ! $access->invitation instanceof Invitation) {
            return $this->stateResponse($request, $access->state);
        }

        $invitation = $access->invitation;
        $recipient = $this->recipient($request);

        if (! $recipient instanceof User) {
            $request->session()->put('url.intended', route('invitations.pending'));
        }

        return response()->view('invitations.show', $presenter->present($invitation, $recipient));
    }

    private function pendingAccess(
        Request $request,
        ResolveInvitationAccessAction $resolveInvitation,
    ): ResolvedInvitationAccess {
        $invitationId = $request->session()->get('staff_invitation_id');
        $credential = $request->session()->get('staff_invitation_credential');

        if (is_int($invitationId)) {
            return $resolveInvitation->byId($invitationId, $this->recipient($request), is_string($credential) ? $credential : null);
        }

        return new ResolvedInvitationAccess(
            InvitationAccessState::fromSession($request->session()->get('staff_invitation_state')),
        );
    }

    private function stateResponse(Request $request, InvitationAccessState $state): Response
    {
        $state = $state === InvitationAccessState::Pending
            ? InvitationAccessState::Unavailable
            : $state;
        $stateName = $state->sessionValue();
        $recipient = $this->recipient($request);

        return response()->view('invitations.status', [
            'title' => __(sprintf('invitations.states.%s_title', $stateName)),
            'message' => __(sprintf('invitations.states.%s_message', $stateName)),
            'actionUrl' => $recipient instanceof User ? route('dashboard') : route('login'),
            'actionLabel' => $recipient instanceof User
                ? __('navigation.dashboard')
                : __('ui.auth.login.log_in'),
            'switchAccountUrl' => $state === InvitationAccessState::EmailMismatch ? route('invitations.switch-account') : null,
        ], $state === InvitationAccessState::Accepted ? 200 : 410);
    }

    private function recipient(Request $request): ?User
    {
        $recipient = $request->user();

        return $recipient instanceof User ? $recipient : null;
    }
}
