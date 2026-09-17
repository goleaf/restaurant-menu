<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\ConfirmPasswordAction;
use App\Livewire\Forms\Auth\PasswordForm;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\View\View;
use Livewire\Component;

final class ConfirmPassword extends Component
{
    use HandlesAuthResponses;

    public PasswordForm $form;

    public function mount(StatefulGuard $guard): void
    {
        abort_unless($guard->check(), 401);
    }

    public function confirm(ConfirmPasswordAction $action): void
    {
        try {
            $this->attempt(fn () => $this->follow($action->handle(request(), $this->form->passwordValue())), 'form.password');
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
        return view('livewire.auth.confirm-password')->layout('layouts.auth', ['title' => __('ui.auth.confirm_password.confirm_password')]);
    }
}
