<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShowInvitationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $token, Translator $translator): View
    {
        $invitation = Invitation::findAcceptableByToken($token);

        if (! $invitation instanceof Invitation) {
            abort(410);
        }

        Gate::authorize('view', $invitation);
        $request->session()->put('staff_invitation_id', $invitation->id);

        $invitation->loadMissing([
            'organization:id,name',
            'branch:id,name',
            'role:id,code,name',
        ]);
        $role = $invitation->role?->code;

        return view('invitations.show', [
            'title' => __('invitations.title'),
            'organizationName' => (string) $invitation->organization?->name,
            'branchName' => $invitation->branch?->name,
            'roleName' => $role?->localizedLabel() ?? (string) $invitation->role?->name,
            'expiresAt' => $invitation->expires_at->locale($translator->getLocale())->isoFormat('LLL'),
            'acceptUrl' => route('invitations.accept'),
        ]);
    }
}
