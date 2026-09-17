<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\CompleteTwoFactorLoginAction;
use App\Livewire\Forms\Auth\TwoFactorForm;
use App\Support\Auth\AuthRequestAdapter;
use App\Support\Auth\TwoFactorChallengeContext;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class TwoFactorChallenge extends Component
{
    use HandlesAuthResponses;

    public TwoFactorForm $form;

    public bool $recovery = false;

    #[Locked]
    public string $challengeId = '';

    public function mount(AuthRequestAdapter $adapter, TwoFactorChallengeContext $context, StatefulGuard $guard): void
    {
        abort_unless(Features::enabled(Features::twoFactorAuthentication()), 404);
        abort_if($guard->check(), 403);
        if (! $context->valid(request()) || ! $adapter->request(request(), [], TwoFactorLoginRequest::class)->hasChallengedUser()) {
            $this->redirectRoute('login', navigate: false);

            return;
        }
        $this->challengeId = $context->identifier(request()) ?? '';
    }

    public function authenticate(CompleteTwoFactorLoginAction $action): void
    {
        try {
            $this->attempt(fn () => $this->follow($action->handle(request(), $this->form->credentials(), $this->challengeId)), $this->recovery ? 'form.recovery_code' : 'form.code');
        } finally {
            $this->form->clearSecrets();
        }
    }

    public function toggleRecovery(): void
    {
        $this->recovery = ! $this->recovery;
        $this->form->clearSecrets();
        $this->resetErrorBag();
    }

    public function dehydrate(): void
    {
        $this->form->clearSecrets();
    }

    public function render(): View
    {
        return view('livewire.auth.two-factor-challenge')->layout('layouts.auth', ['title' => __('ui.auth.two_factor_challenge.two_factor_authentication')]);
    }
}
