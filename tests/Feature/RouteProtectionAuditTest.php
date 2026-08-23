<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;

test('public guest routes are GET only and isolated from staff surfaces', function (): void {
    $publicRoutes = prompt334NamedRoutes(['home', 'guest.home', 'public.qr.show']);

    expect($publicRoutes)->toHaveCount(3);

    $publicRoutes->each(function (Route $route): void {
        expect(prompt334RouteMethods($route))
            ->each->toBeIn(['GET', 'HEAD'])
            ->and(prompt334RouteMiddleware($route))->toContain('web')
            ->and(prompt334RouteMiddleware($route))->not->toContain('auth')
            ->and(prompt334RouteMiddleware($route))->not->toContain('superadmin');
    });

    expect(prompt334RouteByName('public.qr.show')?->uri())->toBe('q/{token}');
    expect(prompt334RouteByName('guest.home')?->uri())->toBe('guest');
});

test('staff and admin route groups require authenticated sessions', function (string $routeName): void {
    $route = prompt334RouteByName($routeName);

    expect($route)->not->toBeNull()
        ->and(prompt334RouteMiddleware($route))->toContain('web')
        ->and(prompt334RouteMiddleware($route))->toContain('auth');
})->with([
    'dashboard',
    'onboarding.restaurant',
    'organizations.index',
    'organizations.staff.index',
    'organizations.staff.permissions',
    'organizations.brands.index',
    'organizations.brands.branches.index',
    'organizations.brands.branches.areas.index',
    'organizations.brands.branches.menu.index',
    'organizations.brands.branches.qr.print',
    'organizations.brands.branches.service-points.index',
    'organizations.brands.branches.service-points.qr.show',
    'organizations.brands.branches.service-points.qr.print',
    'organizations.brands.branches.staff.index',
    'organizations.brands.branches.settings.index',
    'restaurant.dashboard',
    'restaurant.qr-lookup.index',
    'restaurant.audit-log.index',
    'restaurant.exports.index',
    'restaurant.kitchen.dashboard',
    'restaurant.bar.dashboard',
    'restaurant.waiter.dashboard',
    'restaurant.waiter.tables.show',
    'profile.edit',
    'appearance.edit',
    'security.edit',
]);

test('auth routes stay inside the web session middleware group', function (string $routeName): void {
    $route = prompt334RouteByName($routeName);

    expect($route)->not->toBeNull()
        ->and(prompt334RouteMiddleware($route))->toContain('web');
})->with([
    'login',
    'login.store',
    'logout',
    'password.request',
    'password.email',
    'password.reset',
    'password.update',
    'password.confirm',
    'password.confirm.store',
    'register',
    'register.store',
]);

test('download routes have the required access middleware boundary', function (): void {
    $exportRoute = prompt334RouteByName('restaurant.exports.download');
    $backupRoute = prompt334RouteByName('superadmin.backups.sqlite.download');

    expect($exportRoute)->not->toBeNull()
        ->and(prompt334RouteMethods($exportRoute))->toContain('GET')
        ->and(prompt334RouteMiddleware($exportRoute))->toContain('web')
        ->and(prompt334RouteMiddleware($exportRoute))->toContain('auth')
        ->and($exportRoute?->getActionName())->toBe('App\Http\Controllers\Restaurant\DownloadBranchCsvExportController');

    expect($backupRoute)->not->toBeNull()
        ->and(prompt334RouteMethods($backupRoute))->toContain('GET')
        ->and(prompt334RouteMiddleware($backupRoute))->toContain('web')
        ->and(prompt334RouteMiddleware($backupRoute))->toContain('auth')
        ->and(prompt334RouteMiddleware($backupRoute))->toContain('superadmin')
        ->and($backupRoute?->getActionName())->toBe('App\Http\Controllers\Superadmin\DownloadSqliteBackupController');
});

test('sqlite restore routes require superadmin password confirmation', function (): void {
    $showRoute = prompt334RouteByName('superadmin.backups.sqlite.restore');
    $storeRoute = prompt334RouteByName('superadmin.backups.sqlite.restore.store');

    expect($showRoute)->not->toBeNull()
        ->and(prompt334RouteMethods($showRoute))->toContain('GET')
        ->and(prompt334RouteMiddleware($showRoute))->toContain('web', 'auth', 'superadmin', 'password.confirm')
        ->and($showRoute?->getActionName())->toBe('App\Http\Controllers\Superadmin\ShowSqliteBackupRestoreController');

    expect($storeRoute)->not->toBeNull()
        ->and(prompt334RouteMethods($storeRoute))->toContain('POST')
        ->and(prompt334RouteMiddleware($storeRoute))->toContain('web', 'auth', 'superadmin', 'password.confirm')
        ->and($storeRoute?->getActionName())->toBe('App\Http\Controllers\Superadmin\RestoreSqliteBackupController');
});

test('private local storage is not exposed as an unauthenticated file route', function (): void {
    expect(prompt334RouteByName('storage.local'))->toBeNull()
        ->and(prompt334RouteByName('storage.local.upload'))->toBeNull()
        ->and(config('filesystems.disks.local.serve'))->toBeFalse();
});

test('first party web route file does not disable csrf protection', function (): void {
    $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

    expect($bootstrap)->not->toContain('preventRequestForgery(except:')
        ->and($bootstrap)->not->toContain('withoutMiddleware')
        ->and(prompt334RouteByName('default-livewire.update')?->gatherMiddleware())->toContain('web');
});

/**
 * @param  list<string>  $names
 * @return Collection<int, Route>
 */
function prompt334NamedRoutes(array $names): Collection
{
    return collect($names)
        ->map(fn (string $name): ?Route => prompt334RouteByName($name))
        ->filter();
}

function prompt334RouteByName(string $name): ?Route
{
    return app('router')->getRoutes()->getByName($name);
}

/**
 * @return list<string>
 */
function prompt334RouteMiddleware(?Route $route): array
{
    return $route instanceof Route ? array_values($route->gatherMiddleware()) : [];
}

/**
 * @return list<string>
 */
function prompt334RouteMethods(?Route $route): array
{
    return $route instanceof Route ? array_values($route->methods()) : [];
}
