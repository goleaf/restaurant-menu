<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\RegisterInvitationRecipientAction;
use App\Actions\Invitations\ResolveInvitationDestinationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\RegisterInvitationRequest;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterInvitationController extends Controller
{
    public function __invoke(
        RegisterInvitationRequest $request,
        RegisterInvitationRecipientAction $registerRecipient,
        ResolveInvitationDestinationAction $destination,
    ): RedirectResponse {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->safe()->only(['name', 'email', 'password']);

        try {
            $recipient = $registerRecipient->handle($request->invitation(), $data);
        } catch (DomainException) {
            abort(410);
        }

        if ($recipient->wasRecentlyCreated) {
            event(new Registered($recipient));
        }
        Auth::login($recipient);
        $request->session()->regenerate();
        $request->session()->forget(['staff_invitation_id', 'staff_invitation_state', 'staff_invitation_credential', 'url.intended']);

        return redirect()->to($destination->handle($request->invitation(), $recipient))->with('status', __('invitations.messages.accepted'))->withHeaders([
            'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
