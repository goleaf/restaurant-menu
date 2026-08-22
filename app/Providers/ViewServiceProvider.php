<?php

namespace App\Providers;

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Exports\BuildDataExportsIndexAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\BuildWaiterDashboardAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
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

            $view->with(
                'canAccessWaiterDashboard',
                $user instanceof User && app(BuildWaiterDashboardAction::class)->userHasAccess($user),
            );

            $view->with(
                'canAccessKitchenDashboard',
                $user instanceof User && app(ResolveKitchenAccessibleDepartmentIdsAction::class)->userHasAccess($user),
            );

            $view->with(
                'canAccessBarDashboard',
                $user instanceof User && app(ResolveBarAccessibleDepartmentIdsAction::class)->userHasAccess($user),
            );

            $view->with(
                'canAccessAuditLog',
                $user instanceof User && app(BuildAuditLogIndexAction::class)->userHasAccess($user),
            );

            $view->with(
                'canAccessDataExports',
                $user instanceof User && app(BuildDataExportsIndexAction::class)->userHasAccess($user),
            );

            $view->with(
                'canAccessQrLookup',
                $user instanceof User && app(ResolveWaiterAccessibleBranchIdsAction::class)
                    ->handle($user, SystemPermission::GenerateQr)
                    ->isNotEmpty(),
            );
        });
    }
}
