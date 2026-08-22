<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class ShowInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Translator $translator, ?string $token = null): RedirectResponse|Response
    {
        if ($token !== null) {
            $request->session()->forget('staff_invitation_id');
            $invitation = Invitation::findAcceptableByToken($token);

            if (! $invitation instanceof Invitation) {
                abort(410);
            }

            $recipient = $request->user();

            if ($recipient instanceof User) {
                Gate::forUser($recipient)->authorize('view', $invitation);
            } else {
                $request->session()->put('url.intended', route('invitations.pending'));
            }

            $request->session()->put('staff_invitation_id', $invitation->id);

            return redirect()
                ->route('invitations.pending')
                ->withHeaders($this->securityHeaders());
        }

        $invitation = $this->pendingInvitation($request);

        if (! $invitation instanceof Invitation) {
            abort(410);
        }

        $recipient = $request->user();

        if ($recipient instanceof User) {
            Gate::forUser($recipient)->authorize('view', $invitation);
        } else {
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
            'expiresAt' => $invitation->expires_at->locale($translator->getLocale())->isoFormat('LLL'),
            'isAuthenticated' => $recipient instanceof User,
            'invitationEmail' => $invitation->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'acceptUrl' => route('invitations.accept'),
            'registerUrl' => route('invitations.register'),
            'loginUrl' => route('login'),
        ])->withHeaders($this->securityHeaders());
    }

    private function pendingInvitation(Request $request): ?Invitation
    {
        $invitationId = $request->session()->get('staff_invitation_id');

        return is_int($invitationId)
            ? Invitation::findAcceptableById($invitationId)
            : null;
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
