<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Auth\StartTwoFactorChallengeAction;
use App\Actions\Fortify\ResetUserPassword;
use App\Support\Auth\PasswordResetContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RedirectsIfTwoFactorAuthenticatable::class, StartTwoFactorChallengeAction::class);
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
        Fortify::resetPasswordView(function (Request $request) {
            if ($request->isMethod('HEAD') || str_contains(strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '').' '.$request->header('X-Moz', '')), 'prefetch')) {
                return response()->noContent();
            }
            $token = $request->route('token');
            $email = $request->query('email');
            abort_unless(is_string($token) && is_string($email), 404);
            $this->app->make(PasswordResetContext::class)->store($request, $token, $email);

            return to_route('password.reset.form');
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->session()->get('login.id').'|'.$request->ip()));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
