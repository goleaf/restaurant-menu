<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\RegisterInvitationRecipientAction;
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
    ): RedirectResponse {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->safe()->only(['name', 'email', 'password']);

        try {
            $recipient = $registerRecipient->handle($request->invitation(), $data);
        } catch (DomainException) {
            abort(410);
        }

        event(new Registered($recipient));
        Auth::login($recipient);
        $request->session()->regenerate();
        $request->session()->forget(['staff_invitation_id', 'url.intended']);

        return redirect()->route('dashboard')->with('status', __('invitations.messages.accepted'));
    }
}
