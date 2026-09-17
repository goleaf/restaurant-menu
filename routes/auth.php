<?php

declare(strict_types=1);

use App\Livewire\Auth\ConfirmPassword;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Auth\VerifyEmail;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\RoutePath;

Route::middleware(config('fortify.middleware', ['web']))
    ->prefix(config('fortify.prefix', ''))
    ->domain(config('fortify.domain'))
    ->name('')
    ->group(function (): void {
        Route::middleware('guest:'.config('fortify.guard'))->group(function (): void {
            Route::livewire(RoutePath::for('login', '/login'), Login::class)->name('login');

            if (Features::enabled(Features::resetPasswords())) {
                Route::livewire(RoutePath::for('password.request', '/forgot-password'), ForgotPassword::class)->name('password.request');
                Route::get(RoutePath::for('password.reset', '/reset-password/{token}'), [NewPasswordController::class, 'create'])->name('password.reset');
                Route::livewire('/reset-password', ResetPassword::class)->name('password.reset.form');
            }

            if (Features::enabled(Features::twoFactorAuthentication())) {
                Route::livewire(RoutePath::for('two-factor.login', '/two-factor-challenge'), TwoFactorChallenge::class)->name('two-factor.login');
            }
        });

        Route::middleware(config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'))->group(function (): void {
            Route::livewire(RoutePath::for('password.confirm', '/user/confirm-password'), ConfirmPassword::class)->name('password.confirm');

            if (Features::enabled(Features::emailVerification())) {
                Route::livewire(RoutePath::for('verification.notice', '/email/verify'), VerifyEmail::class)->name('verification.notice');
            }
        });
    });
