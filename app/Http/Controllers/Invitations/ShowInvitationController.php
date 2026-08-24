<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\ResolvedInvitationAccess;
use App\Actions\Invitations\ResolveInvitationAccessAction;
use App\Enums\InvitationAccessState;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;

class ShowInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        ResolveInvitationAccessAction $resolveInvitation,
        ?string $token = null,
    ): RedirectResponse|Response {
        if ($token !== null) {
            $request->session()->forget(['staff_invitation_id', 'staff_invitation_state']);
            $access = $resolveInvitation->byToken($token, $this->recipient($request));

            $request->session()->put('staff_invitation_state', $access->state->sessionValue());

            if ($access->invitation instanceof Invitation) {
                $request->session()->put('staff_invitation_id', $access->invitation->id);
            }

            if ($access->state === InvitationAccessState::Pending && ! $this->recipient($request) instanceof User) {
                $request->session()->put('url.intended', route('invitations.pending'));
            }

            return redirect()
                ->route('invitations.pending')
                ->withHeaders($this->securityHeaders());
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

        $invitation->loadMissing([
            'organization:id,name',
            'branch:id,name',
            'role:id,code,name',
        ]);
        $role = $invitation->role?->code;

        return response()->view('invitations.show', [
            'title' => __('invitations.title'),
            'organizationName' => (string) $invitation->organization?->name,
            'branchName' => $invitation->branch?->name,
            'roleName' => $role?->localizedLabel() ?? (string) $invitation->role?->name,
            'expiresAt' => LocalizedDateFormatter::dateTime($invitation->expires_at),
            'isAuthenticated' => $recipient instanceof User,
            'invitationEmail' => $invitation->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'acceptUrl' => route('invitations.accept'),
            'registerUrl' => route('invitations.register'),
            'loginUrl' => route('login'),
        ])->withHeaders($this->securityHeaders());
    }

    private function pendingAccess(
        Request $request,
        ResolveInvitationAccessAction $resolveInvitation,
    ): ResolvedInvitationAccess {
        $invitationId = $request->session()->get('staff_invitation_id');

        if (is_int($invitationId)) {
            return $resolveInvitation->byId($invitationId, $this->recipient($request));
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
        ], $state === InvitationAccessState::Accepted ? 200 : 410)
            ->withHeaders($this->securityHeaders());
    }

    private function recipient(Request $request): ?User
    {
        $recipient = $request->user();

        return $recipient instanceof User ? $recipient : null;
    }

    /**
     * @return array<string, string>
     */
    private function securityHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}
