<?php

declare(strict_types=1);

use App\Http\Controllers;
use App\Livewire as Screens;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\ViewController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Mechanisms\HandleRouting\LivewirePageController;

test('every product page route uses its explicit class based Livewire screen', function (): void {
    $expected = [
        'dashboard' => Screens\Workspace\Entry::class,
        'guest.home' => Screens\Guest\Home::class,
        'public.qr.show' => Screens\PublicQr\Show::class,
        'local.components' => Screens\Local\ComponentReference::class,
        'onboarding.restaurant' => Screens\Onboarding\RestaurantSetup::class,
        'organizations.index' => Screens\Organizations\Index::class,
        'organizations.staff.index' => Screens\Organizations\Staff\Index::class,
        'organizations.staff.show' => Screens\Organizations\Staff\Show::class,
        'organizations.staff.permissions' => Screens\Organizations\Staff\Show::class,
        'organizations.brands.index' => Screens\Organizations\Brands\Index::class,
        'organizations.brands.branches.index' => Screens\Organizations\Brands\Branches\Index::class,
        'organizations.brands.branches.areas.index' => Screens\Organizations\Brands\Branches\Areas::class,
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
        'restaurant.departments.tickets.print' => Screens\Departments\TicketPrint::class,
        'restaurant.exports.index' => Screens\Exports\Index::class,
        'restaurant.kitchen.dashboard' => Screens\Kitchen\Dashboard::class,
        'restaurant.bar.dashboard' => Screens\Bar\Dashboard::class,
        'restaurant.waiter.dashboard' => Screens\Waiter\Dashboard::class,
        'restaurant.waiter.tables.show' => Screens\Waiter\TableDetail::class,
        'superadmin.dashboard' => Screens\Superadmin\Dashboard::class,
        'profile.edit' => Screens\Settings\Profile::class,
        'security.edit' => Screens\Settings\Security::class,
    ];
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

        if (! in_array($route->getName(), ['guest.home', 'public.qr.show'], true)) {
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
        'demo-login.index' => Controllers\Auth\ShowDemoLoginController::class,
        'demo-login.authenticate' => Controllers\Auth\LoginAsDemoRoleController::class,
        'local-login.authenticate' => Controllers\Auth\LoginAsLocalUserController::class,
        'invitations.show' => Controllers\Invitations\ShowInvitationController::class,
        'invitations.pending' => Controllers\Invitations\ShowInvitationController::class,
        'invitations.register' => Controllers\Invitations\RegisterInvitationController::class,
        'invitations.accept' => Controllers\Invitations\AcceptInvitationController::class,
        'invitations.switch-account' => Controllers\Invitations\SwitchInvitationAccountController::class,
        'organizations.brands.branches.qr.pdf' => Controllers\Organizations\DownloadBranchQrPdfController::class,
        'restaurant.exports.download' => Controllers\Restaurant\DownloadBranchCsvExportController::class,
        'restaurant.exports.pdf' => Controllers\Restaurant\DownloadBranchPdfReportController::class,
        'superadmin.backups.sqlite.download' => Controllers\Superadmin\DownloadSqliteBackupController::class,
        'superadmin.backups.media.download' => Controllers\Superadmin\DownloadMediaBackupController::class,
        'superadmin.backups.sqlite.restore' => Controllers\Superadmin\ShowSqliteBackupRestoreController::class,
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

test('native forms stay limited to CSRF protected authentication download and restore requests', function (): void {
    $expected = [
        'auth/demo-login.blade.php' => ["{{ route('demo-login.authenticate', ['role' => \$account['role']]) }}"],
        'components/account-menu.blade.php' => ["{{ route('logout') }}"],
        'components/auth/local-user-directory.blade.php' => ["{{ route('local-login.authenticate', ['user' => \$user['id']]) }}"],
        'invitations/show.blade.php' => ['{{ $acceptUrl }}', '{{ $registerUrl }}'],
        'invitations/status.blade.php' => ['{{ $switchAccountUrl }}'],
        'livewire/auth/confirm-password.blade.php' => ["{{ route('password.confirm.store') }}"],
        'livewire/auth/forgot-password.blade.php' => ["{{ route('password.email') }}"],
        'livewire/auth/login.blade.php' => ["{{ route('login.store') }}"],
        'livewire/auth/reset-password.blade.php' => ["{{ route('password.update') }}"],
        'livewire/auth/two-factor-challenge.blade.php' => ["{{ route('two-factor.login.store') }}"],
        'livewire/auth/verify-email.blade.php' => ["{{ route('verification.send') }}", "{{ route('logout') }}"],
        'livewire/organizations/brands/branches/qr/bulk-print.blade.php' => ['{{ $pdfDownloadUrl }}'],
        'livewire/organizations/brands/branches/service-points/qr/print-template.blade.php' => ['{{ $pdfDownloadUrl }}'],
        'superadmin/backups/restore-sqlite.blade.php' => ["{{ route('superadmin.backups.sqlite.restore.store') }}"],
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
