<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\SendEmailVerificationAction;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class VerifyEmail extends Component
{
    use HandlesAuthResponses;

    #[Locked]
    public bool $verificationLinkSent = false;

    public function mount(StatefulGuard $guard): void
    {
        abort_unless(Features::enabled(Features::emailVerification()), 404);
        $user = $guard->user();
        abort_if($user === null, 401);
        if ($user->hasVerifiedEmail()) {
            $this->redirect(config('fortify.home'), navigate: false);
        }
    }

    public function resend(SendEmailVerificationAction $action): void
    {
        $this->verificationLinkSent = false;
        $this->attempt(function () use ($action): void {
            $action->handle(request());
            $this->verificationLinkSent = true;
        }, 'verificationLinkSent');
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email')->layout('layouts.auth', ['title' => __('ui.auth.verify_email.email_verification')]);
    }
}
