<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Support\Invitations\InvitationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShowInvitationController extends Controller
{
    public function __invoke(Request $request, InvitationSession $invitationSession, string $token): RedirectResponse
    {
        if ($request->isMethod('GET') && ! $request->prefetch()
            && ! str_contains(strtolower($request->header('Sec-Purpose', '')), 'prefetch')) {
            $invitationSession->exchange($request, $token);
        }

        return redirect()->route('invitations.pending');
    }
}
