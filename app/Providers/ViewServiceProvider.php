<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer(['dashboard', 'layouts.app.sidebar'], function (ViewInstance $view): void {
            $user = Auth::user();

            $view->with(
                'canAccessPlatformDashboard',
                $user instanceof User && $user->isSuperadmin(),
            );
        });
    }
}
