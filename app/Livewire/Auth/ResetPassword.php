<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Livewire\Forms\Auth\ResetPasswordForm;
use App\Support\Auth\PasswordResetContext;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ResetPassword extends Component
{
    use HandlesAuthResponses;

    public ResetPasswordForm $form;

    #[Locked]
    public string $contextId = '';

    public function mount(PasswordResetContext $context, StatefulGuard $guard): void
    {
        abort_unless(Features::enabled(Features::resetPasswords()), 404);
        abort_if($guard->check(), 403);
        if ($context->token(request()) === null) {
            $this->redirectRoute('password.request', navigate: false);

            return;
        }
        $this->contextId = $context->identifier(request()) ?? '';
        $this->form->email = $context->email(request());
    }

    public function resetPassword(ResetPasswordAction $action): void
    {
        try {
            $this->attempt(fn () => $this->follow($action->handle(request(), $this->form->credentials(), $this->contextId)));
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
        return view('livewire.auth.reset-password', ['passwordRules' => Password::defaults()->toPasswordRulesString()])
            ->layout('layouts.auth', ['title' => __('ui.auth.reset_password.reset_password')]);
    }
}
