<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Navigation\ApplicationNavigationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
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
    public function boot(
        Request $request,
        ApplicationNavigationPresenter $buildApplicationNavigation,
    ): void {
        Blade::componentNamespace('App\\View\\Components\\Layouts', 'layouts');

        View::composer('dashboard', function (ViewInstance $view) use ($request, $buildApplicationNavigation): void {
            $view->with($buildApplicationNavigation->handle($request->user(), $request));
        });
    }
}
