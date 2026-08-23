<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => view('livewire.auth.login', $this->authViewData($request)));
        Fortify::verifyEmailView(fn (Request $request) => view('livewire.auth.verify-email', [
            ...$this->authViewData($request),
            'verificationLinkSent' => $request->session()->get('status') === 'verification-link-sent',
        ]));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn (Request $request) => view('livewire.auth.confirm-password', $this->authViewData($request)));
        Fortify::resetPasswordView(fn (Request $request) => view('livewire.auth.reset-password', [
            ...$this->authViewData($request),
            'resetToken' => (string) $request->route('token'),
            'resetEmail' => (string) $request->query('email', ''),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));
        Fortify::requestPasswordResetLinkView(fn (Request $request) => view('livewire.auth.forgot-password', $this->authViewData($request)));
    }

    /**
     * @return array{sessionStatus: mixed}
     */
    private function authViewData(Request $request): array
    {
        return [
            'sessionStatus' => $request->session()->get('status'),
        ];
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
