<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Livewire\Forms\Auth\LoginForm;
use App\Support\DemoLogin\DemoEnvironment;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use Livewire\Component;

final class Login extends Component
{
    use HandlesAuthResponses;

    public LoginForm $form;

    private DemoEnvironment $environment;

    public function boot(DemoEnvironment $environment): void
    {
        $this->environment = $environment;
    }

    public function mount(StatefulGuard $guard): void
    {
        if ($guard->check()) {
            $this->redirect(config('fortify.home'), navigate: false);
        }
    }

    public function login(AuthenticateUserAction $action): void
    {
        try {
            $this->attempt(fn () => $this->follow($action->handle(request(), $this->form->credentials())));
        } finally {
            $this->form->clearSecrets();
        }
    }

    public function dehydrate(): void
    {
        $this->form->clearSecrets();
    }

    public function render(): View
    {
        $localDirectoryAvailable = $this->environment->allowsLocalRequest(request());

        return view('livewire.auth.login', [
            'sessionStatus' => session('status'),
            'localDirectoryAvailable' => $localDirectoryAvailable,
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canUsePasskeys' => Features::canManagePasskeys(),
        ])->layout('layouts.auth.simple', ['title' => __('ui.auth.login.log_in'), 'directory' => $localDirectoryAvailable]);
    }
}
