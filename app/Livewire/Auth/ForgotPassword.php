<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\SendPasswordResetLinkAction;
use App\Livewire\Forms\Auth\EmailForm;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ForgotPassword extends Component
{
    use HandlesAuthResponses;

    public EmailForm $form;

    #[Locked]
    public string $status = '';

    public function mount(StatefulGuard $guard): void
    {
        abort_unless(Features::enabled(Features::resetPasswords()), 404);
        abort_if($guard->check(), 403);
    }

    public function sendResetLink(SendPasswordResetLinkAction $action): void
    {
        $this->status = '';
        $this->attempt(function () use ($action): void {
            $action->handle($this->form->emailAddress());
            $this->status = __('passwords.sent');
        });
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password')->layout('layouts.auth', ['title' => __('ui.auth.forgot_password.forgot_password')]);
    }
}
