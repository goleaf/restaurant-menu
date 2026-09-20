<?php

declare(strict_types=1);

use App\Http\Controllers;
use App\Livewire as Screens;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\ViewController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Livewire\Component;
use Livewire\Mechanisms\HandleRouting\LivewirePageController;

test('every product page route uses its explicit class based Livewire screen', function (): void {
    $expected = [
        'login' => Screens\Auth\Login::class,
        'password.confirm' => Screens\Auth\ConfirmPassword::class,
        'demo-login.index' => Screens\Local\DemoLogin::class,
        'invitations.pending' => Screens\Invitations\Show::class,
        'dashboard' => Screens\Workspace\Entry::class,
        'guest.home' => Screens\Guest\Home::class,
        'public.qr.show' => Screens\PublicQr\Show::class,
        'local.components' => Screens\Local\ComponentReference::class,
        'restaurants.index' => Screens\Restaurants\Index::class,
        'restaurants.create' => Screens\Onboarding\RestaurantSetup::class,
        'restaurants.setup' => Screens\Onboarding\RestaurantSetup::class,
        'onboarding.restaurant' => Screens\Onboarding\RestaurantSetup::class,
        'organizations.index' => Screens\Restaurants\Index::class,
        'organizations.staff.index' => Screens\Organizations\Staff\Index::class,
        'organizations.staff.show' => Screens\Organizations\Staff\Show::class,
        'organizations.staff.permissions' => Screens\Organizations\Staff\Show::class,
        'organizations.brands.index' => Screens\Restaurants\Index::class,
        'organizations.brands.branches.index' => Screens\Restaurants\Index::class,
        'organizations.brands.branches.areas.index' => Screens\Organizations\Brands\Branches\Areas::class,
        'organizations.brands.branches.availability.index' => Screens\Organizations\Brands\Branches\Availability\Index::class,
        'organizations.brands.branches.menu.dish.create' => Screens\Organizations\Brands\Branches\Menu\Dish::class,
        'organizations.brands.branches.menu.dish.edit' => Screens\Organizations\Brands\Branches\Menu\Dish::class,
        'organizations.brands.branches.menu.index' => Screens\Organizations\Brands\Branches\Menu\Index::class,
        'organizations.brands.branches.qr.print' => Screens\Organizations\Brands\Branches\Qr\BulkPrint::class,
        'organizations.brands.branches.service-points.index' => Screens\Organizations\Brands\Branches\ServicePoints\Index::class,
        'organizations.brands.branches.service-points.qr.show' => Screens\Organizations\Brands\Branches\ServicePoints\Qr\Show::class,
        'organizations.brands.branches.service-points.qr.print' => Screens\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate::class,
        'organizations.brands.branches.staff.index' => Screens\Organizations\Brands\Branches\Staff\Index::class,
        'organizations.brands.branches.staff.show' => Screens\Organizations\Staff\Show::class,
        'organizations.brands.branches.settings.index' => Screens\Organizations\Brands\Branches\Settings::class,
        'restaurant.dashboard' => Screens\Restaurant\Dashboard::class,
        'restaurant.qr-lookup.index' => Screens\QrCodes\ShortCodeLookup::class,
        'restaurant.audit-log.index' => Screens\AuditLogs\Index::class,
        'restaurant.preparation.dashboard' => Screens\Departments\Dashboard::class,
        'restaurant.departments.tickets.print' => Screens\Departments\TicketPrint::class,
        'restaurant.exports.index' => Screens\Exports\Index::class,
        'restaurant.exports.download' => Screens\Exports\Index::class,
        'restaurant.exports.pdf' => Screens\Exports\Index::class,
        'restaurant.kitchen.dashboard' => Screens\Kitchen\Dashboard::class,
        'restaurant.bar.dashboard' => Screens\Bar\Dashboard::class,
        'restaurant.waiter.dashboard' => Screens\Waiter\Dashboard::class,
        'restaurant.waiter.tables.show' => Screens\Waiter\TableDetail::class,
        'superadmin.dashboard' => Screens\Superadmin\Dashboard::class,
        'superadmin.backups.sqlite.restore' => Screens\Superadmin\Backups\RestoreSqlite::class,
        'profile.edit' => Screens\Settings\Profile::class,
        'security.edit' => Screens\Settings\Security::class,
    ];
    if (Features::enabled(Features::resetPasswords())) {
        $expected['password.request'] = Screens\Auth\ForgotPassword::class;
        $expected['password.reset.form'] = Screens\Auth\ResetPassword::class;
    }
    if (Features::enabled(Features::twoFactorAuthentication())) {
        $expected['two-factor.login'] = Screens\Auth\TwoFactorChallenge::class;
    }
    if (Features::enabled(Features::emailVerification())) {
        $expected['verification.notice'] = Screens\Auth\VerifyEmail::class;
    }
    $actual = [];

    foreach (Route::getRoutes() as $route) {
        if ($route->getActionName() !== LivewirePageController::class) {
            continue;
        }

        $component = $route->getAction('livewire_component');

        expect($component)->toBeString()
            ->and(is_subclass_of($component, Component::class))->toBeTrue()
            ->and((new ReflectionClass($component))->isAnonymous())->toBeFalse()
            ->and((new ReflectionClass($component))->getFileName())->toStartWith(app_path('Livewire').DIRECTORY_SEPARATOR)
            ->and($route->methods())->toBe(['GET', 'HEAD'])
            ->and($route->gatherMiddleware())->toContain('web');

        if (in_array($route->getName(), ['login', 'password.request', 'password.reset.form', 'two-factor.login'], true)) {
            expect($route->gatherMiddleware())->toContain('guest:'.config('fortify.guard'));
        } elseif (in_array($route->getName(), ['password.confirm', 'verification.notice'], true)) {
            expect($route->gatherMiddleware())->toContain('auth:'.config('fortify.guard'));
        } elseif ($route->getName() === 'demo-login.index') {
            expect($route->gatherMiddleware())->toContain('guest', 'demo-login', 'throttle:demo-login');
        } elseif ($route->getName() === 'invitations.pending') {
            expect($route->gatherMiddleware())->toContain('throttle:staff-invitations');
        } elseif (! in_array($route->getName(), ['guest.home', 'public.qr.show'], true)) {
            expect($route->gatherMiddleware())->toContain('auth');
        }

        $actual[$route->getName()] = $component;
    }

    ksort($actual);
    ksort($expected);

    expect($actual)->toBe($expected);
});

test('ordinary first party HTTP routes are limited to explicit protocol and document boundaries', function (): void {
    $expected = [
        'home' => ViewController::class,
        'settings.index' => RedirectController::class,
        'appearance.edit' => RedirectController::class,
        'invitations.show' => Controllers\Invitations\ShowInvitationController::class,
        'restaurant.files.download' => Controllers\Restaurant\DownloadPreparedFileController::class,
        'superadmin.backups.sqlite.download' => RedirectController::class,
        'superadmin.backups.media.download' => RedirectController::class,
        'superadmin.backups.sqlite.restore.store' => Controllers\Superadmin\RestoreSqliteBackupController::class,
    ];
    $actual = [];

    foreach (Route::getRoutes() as $route) {
        if (! interactionBoundaryIsFirstPartyHttpRoute($route)) {
            continue;
        }

        $actual[$route->getName() ?? $route->uri()] = ltrim($route->getControllerClass() ?? $route->getActionName(), '\\');
    }

    ksort($actual);
    ksort($expected);

    expect($actual)->toBe($expected);
});

test('native forms stay limited to the CSRF protected prepared restore finalization', function (): void {
    $expected = [
        'livewire/superadmin/backups/restore-sqlite.blade.php' => ["{{ route('superadmin.backups.sqlite.restore.store') }}"],
    ];
    $actual = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = File::get($file->getPathname());
        preg_match_all('/<form\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)<\/form>/s', $source, $forms, PREG_SET_ORDER);

        foreach ($forms as $form) {
            if (preg_match('/\bwire:submit(?:\.[\w.-]+)?\s*=/', $form[1]) === 1) {
                continue;
            }

            expect($form[1], $file->getRelativePathname())->toMatch('/\bmethod="POST"/i')
                ->and($form[2], $file->getRelativePathname())->toContain('@csrf');
            preg_match('/\baction="([^"]+)"/', $form[1], $action);
            $actual[$file->getRelativePathname()][] = $action[1] ?? '';
            expect($form[1])->not->toContain('multipart/form-data');
            preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"/', $form[2], $fields);
            expect($fields[1])->toBe(['grant']);
        }
    }

    ksort($actual);
    ksort($expected);

    expect($actual)->toBe($expected);
});

function interactionBoundaryIsFirstPartyHttpRoute(RoutingRoute $route): bool
{
    $action = ltrim($route->getControllerClass() ?? $route->getActionName(), '\\');

    if (str_starts_with($action, 'App\\') || in_array($action, [ViewController::class, RedirectController::class], true)) {
        return true;
    }

    $handler = $route->getAction('uses');

    return $handler instanceof Closure
        && str_starts_with((string) (new ReflectionFunction($handler))->getFileName(), base_path('routes').DIRECTORY_SEPARATOR);
}

test('first party controllers are exactly the three retained transport boundaries', function (): void {
    $files = collect(File::allFiles(app_path('Http/Controllers')))
        ->map(fn ($file): string => $file->getRelativePathname())->sort()->values()->all();
    expect($files)->toBe([
        'Controller.php',
        'Invitations/ShowInvitationController.php',
        'Restaurant/DownloadPreparedFileController.php',
        'Superadmin/RestoreSqliteBackupController.php',
    ]);

    foreach ([
        'invitations.show' => [['GET', 'HEAD'], 'invite/{token}', ['web', 'throttle:staff-invitations']],
        'restaurant.files.download' => [['GET', 'HEAD'], 'restaurant/files/{grant}', ['web', 'auth']],
        'superadmin.backups.sqlite.restore.store' => [['POST'], 'superadmin/backups/sqlite/restore', ['web', 'auth', 'superadmin', 'password.confirm']],
    ] as $name => [$methods, $uri, $middleware]) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull()
            ->and($route->methods())->toBe($methods)
            ->and($route->uri())->toBe($uri);
        foreach ($middleware as $required) {
            expect($route->gatherMiddleware())->toContain($required);
        }
    }

    foreach (['invitations.accept', 'invitations.register', 'invitations.switch-account', 'demo-login.authenticate', 'local-login.authenticate', 'organizations.brands.branches.qr.pdf'] as $removed) {
        expect(Route::has($removed), $removed)->toBeFalse();
    }
});

test('Fortify retains its protocol endpoints while reset entry uses the vendor credential exchange callback', function (): void {
    $protocols = ['login.store', 'logout', 'password.confirm.store'];
    if (Features::enabled(Features::resetPasswords())) {
        $protocols = [...$protocols, 'password.email', 'password.update'];
        $entry = Route::getRoutes()->getByName('password.reset');
        expect($entry->getControllerClass())->toBe(NewPasswordController::class)
            ->and($entry->getActionMethod())->toBe('create')
            ->and($entry->methods())->toBe(['GET', 'HEAD'])
            ->and($entry->uri())->toContain('{token}');
    }
    foreach ($protocols as $name) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull()
            ->and($route->methods())->toBe(['POST'])
            ->and($route->getControllerClass())->toStartWith('Laravel\\Fortify\\Http\\Controllers\\');
    }
});
