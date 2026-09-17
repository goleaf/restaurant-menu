<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\InvitationAccessState;
use App\Support\Invitations\InvitationSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final readonly class SwitchInvitationAccountAction
{
    public function __construct(private InvitationSession $invitationSession) {}

    public function handle(Request $request): void
    {
        abort_unless($this->invitationSession->recipient($request) !== null, 403);
        abort_unless($this->invitationSession->resolve($request)->state === InvitationAccessState::EmailMismatch, 410);
        $credential = $this->invitationSession->credential($request);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put($credential);
        $request->session()->put('staff_invitation_context', Str::random(32));
        $request->session()->put('url.intended', route('invitations.pending'));
    }
}
