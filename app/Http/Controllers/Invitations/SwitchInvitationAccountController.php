<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SwitchInvitationAccountController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $invitationId = $request->session()->get('staff_invitation_id');
        $credential = $request->session()->get('staff_invitation_credential');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (is_int($invitationId) && is_string($credential) && strlen($credential) === 64) {
            $request->session()->put([
                'staff_invitation_id' => $invitationId,
                'staff_invitation_credential' => $credential,
                'url.intended' => route('invitations.pending'),
            ]);
        }

        return redirect()->route('login')->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
