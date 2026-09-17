<?php

declare(strict_types=1);

namespace App\Livewire\Invitations;

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\RegisterInvitationRecipientAction;
use App\Actions\Invitations\ResolveInvitationDestinationAction;
use App\Actions\Invitations\SwitchInvitationAccountAction;
use App\Enums\InvitationAccessState;
use App\Livewire\Forms\Invitations\RegistrationForm;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Invitations\InvitationPagePresenter;
use App\Support\Auth\AuthRequestAdapter;
use App\Support\Invitations\InvitationSession;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    public RegistrationForm $form;

    #[Locked]
    public string $context = '';

    #[Locked]
    public string $audience = '';

    private InvitationSession $invitationSession;

    private AuthRequestAdapter $authRequestAdapter;

    public function boot(InvitationSession $invitationSession, AuthRequestAdapter $authRequestAdapter): void
    {
        $this->invitationSession = $invitationSession;
        $this->authRequestAdapter = $authRequestAdapter;
    }

    public function mount(): void
    {
        $this->context = $this->invitationSession->context(request());
        $this->audience = $this->invitationSession->audience(request());
        $access = $this->invitationSession->resolve(request());
        if ($access->state === InvitationAccessState::Pending && $this->invitationSession->recipient(request()) === null) {
            $this->form->email = $access->invitation->email ?? '';
        }
    }

    public function hydrate(): void
    {
        $this->invitationSession->assertContext(request(), $this->context, $this->audience);
    }

    public function dehydrate(): void
    {
        $this->form->clearPasswords();
    }

    public function accept(AcceptInvitationAction $acceptInvitation, ResolveInvitationDestinationAction $destination): void
    {
        $this->guardAndThrottle();
        $recipient = $this->invitationSession->recipient(request());
        abort_unless($recipient instanceof User, 403);
        $invitation = $this->pendingInvitation();
        abort_unless(Gate::forUser($recipient)->allows('accept', $invitation), 410);

        try {
            $acceptInvitation->handle($invitation, $recipient);
        } catch (DomainException) {
            abort(410);
        }

        $this->complete($invitation, $recipient, $destination);
    }

    public function register(RegisterInvitationRecipientAction $registerRecipient, ResolveInvitationDestinationAction $destination): void
    {
        try {
            $this->guardAndThrottle();
            abort_unless($this->invitationSession->recipient(request()) === null, 403);
            $invitation = $this->pendingInvitation();
            $data = $this->form->validatedFor($invitation);

            try {
                $recipient = $registerRecipient->handle($invitation, $data);
            } catch (DomainException) {
                abort(410);
            }

            if ($recipient->wasRecentlyCreated) {
                event(new Registered($recipient));
            }
            Auth::guard('web')->login($recipient);
            request()->session()->regenerate();
            $this->complete($invitation, $recipient, $destination);
        } finally {
            $this->form->clearPasswords();
        }
    }

    public function switchAccount(SwitchInvitationAccountAction $switchAccount): void
    {
        $this->guardAndThrottle();
        $switchAccount->handle(request());
        $this->redirectRoute('login', navigate: false);
    }

    public function render(InvitationPagePresenter $presenter): View
    {
        $access = $this->invitationSession->resolve(request());
        $recipient = $this->invitationSession->recipient(request());
        $pending = $access->state === InvitationAccessState::Pending && $access->invitation instanceof Invitation;
        $data = $pending ? $presenter->present($access->invitation, $recipient) : $presenter->status($access->state, $recipient);
        $status = $pending || $access->state === InvitationAccessState::Accepted ? 200 : 410;

        return view('livewire.invitations.show', ['isPending' => $pending, ...$data])
            ->layout('layouts::auth', ['title' => $data['title']])
            ->response(fn (Response $response) => $response->setStatusCode($status));
    }

    private function guardAndThrottle(): void
    {
        $this->invitationSession->assertContext(request(), $this->context, $this->audience);
        $this->authRequestAdapter->throttle(request(), fn () => response()->noContent(), 'staff-invitations');
    }

    private function pendingInvitation(): Invitation
    {
        $access = $this->invitationSession->resolve(request());
        abort_unless($access->state === InvitationAccessState::Pending && $access->invitation instanceof Invitation, 410);

        return $access->invitation;
    }

    private function complete(Invitation $invitation, User $recipient, ResolveInvitationDestinationAction $destination): void
    {
        $this->invitationSession->clear(request());
        session()->flash('status', __('invitations.messages.accepted'));
        $this->redirect($destination->handle($invitation, $recipient), navigate: false);
    }
}
