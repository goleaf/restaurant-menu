<?php

declare(strict_types=1);

use App\Enums\DataExportType;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\ShowInvitationController;
use App\Http\Controllers\Restaurant\DownloadBranchCsvExportController;
use App\Http\Controllers\Superadmin\DownloadSqliteBackupController;
use App\Livewire\AuditLogs\Index as AuditLogIndex;
use App\Livewire\Bar\Dashboard as BarDashboard;
use App\Livewire\Departments\TicketPrint as DepartmentTicketPrint;
use App\Livewire\Exports\Index as DataExportsIndex;
use App\Livewire\Guest\Home as GuestHome;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Livewire\Onboarding\RestaurantSetup as RestaurantOnboarding;
use App\Livewire\Organizations\Brands\Branches\Areas as OrganizationBrandBranchAreas;
use App\Livewire\Organizations\Brands\Branches\Index as OrganizationBrandBranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Menu\Index as OrganizationBrandBranchMenuIndex;
use App\Livewire\Organizations\Brands\Branches\Qr\BulkPrint as OrganizationBrandBranchQrBulkPrint;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as OrganizationBrandBranchServicePointsIndex;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate as OrganizationBrandBranchServicePointQrPrintTemplate;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\Show as OrganizationBrandBranchServicePointQrShow;
use App\Livewire\Organizations\Brands\Branches\Settings as OrganizationBrandBranchSettings;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as OrganizationBrandBranchStaffIndex;
use App\Livewire\Organizations\Brands\Index as OrganizationBrandsIndex;
use App\Livewire\Organizations\Index as OrganizationsIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
use App\Livewire\Organizations\Staff\Permissions as OrganizationStaffPermissions;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Livewire\QrCodes\ShortCodeLookup as QrShortCodeLookup;
use App\Livewire\Restaurant\Dashboard as RestaurantDashboard;
use App\Livewire\Superadmin\Dashboard as SuperadminDashboard;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Livewire\Waiter\TableDetail as WaiterTableDetail;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['web'])
    ->prefix('guest')
    ->name('guest.')
    ->group(function () {
        Route::livewire('/', GuestHome::class)->name('home');
    });

Route::middleware(['web'])
    ->prefix('q')
    ->name('public.qr.')
    ->group(function () {
        Route::livewire('{token}', PublicQrShow::class)
            ->where('token', '[A-Za-z0-9]+')
            ->name('show');
    });

Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'throttle:staff-invitations'])
    ->prefix('invite')
    ->name('invitations.')
    ->group(function () {
        Route::get('{token}', ShowInvitationController::class)
            ->where('token', '[A-Za-z0-9]{64}')
            ->name('show');
        Route::post('accept', AcceptInvitationController::class)->name('accept');
    });

Route::middleware(['auth'])
    ->prefix('onboarding')
    ->name('onboarding.')
    ->group(function () {
        Route::livewire('restaurant', RestaurantOnboarding::class)->name('restaurant');
    });

Route::middleware(['auth'])
    ->prefix('organizations')
    ->name('organizations.')
    ->group(function () {
        Route::livewire('/', OrganizationsIndex::class)->name('index');

        Route::livewire('{organization}/staff', OrganizationStaffIndex::class)->name('staff.index');
        Route::livewire('{organization}/staff/{staffMember}/permissions', OrganizationStaffPermissions::class)->name('staff.permissions');

        Route::prefix('{organization}/brands')
            ->name('brands.')
            ->scopeBindings()
            ->group(function () {
                Route::livewire('/', OrganizationBrandsIndex::class)->name('index');

                Route::prefix('{brand}/branches')
                    ->name('branches.')
                    ->group(function () {
                        Route::livewire('/', OrganizationBrandBranchesIndex::class)->name('index');

                        Route::prefix('{branch}/areas')
                            ->name('areas.')
                            ->group(function () {
                                Route::livewire('/', OrganizationBrandBranchAreas::class)->name('index');
                            });

                        Route::prefix('{branch}/menu')
                            ->name('menu.')
                            ->group(function () {
                                Route::livewire('/', OrganizationBrandBranchMenuIndex::class)->name('index');
                            });

                        Route::prefix('{branch}/qr')
                            ->name('qr.')
                            ->group(function () {
                                Route::livewire('print', OrganizationBrandBranchQrBulkPrint::class)->name('print');
                            });

                        Route::prefix('{branch}/service-points')
                            ->name('service-points.')
                            ->group(function () {
                                Route::livewire('/', OrganizationBrandBranchServicePointsIndex::class)->name('index');
                                Route::livewire('{servicePoint}/qr/{qrCode}', OrganizationBrandBranchServicePointQrShow::class)->name('qr.show');
                                Route::livewire('{servicePoint}/qr/{qrCode}/print', OrganizationBrandBranchServicePointQrPrintTemplate::class)->name('qr.print');
                            });

                        Route::prefix('{branch}/staff')
                            ->name('staff.')
                            ->group(function () {
                                Route::livewire('/', OrganizationBrandBranchStaffIndex::class)->name('index');
                            });

                        Route::prefix('{branch}/settings')
                            ->name('settings.')
                            ->group(function () {
                                Route::livewire('/', OrganizationBrandBranchSettings::class)->name('index');
                            });
                    });
            });
    });

Route::middleware(['auth'])
    ->prefix('restaurant')
    ->name('restaurant.')
    ->group(function () {
        Route::livewire('dashboard', RestaurantDashboard::class)->name('dashboard');
        Route::livewire('qr-lookup', QrShortCodeLookup::class)->name('qr-lookup.index');
        Route::livewire('audit-log', AuditLogIndex::class)->name('audit-log.index');

        Route::prefix('departments')
            ->name('departments.')
            ->group(function () {
                Route::livewire('tickets/{kitchenTicket}/print', DepartmentTicketPrint::class)->name('tickets.print');
            });

        Route::prefix('exports')
            ->name('exports.')
            ->group(function () {
                Route::livewire('/', DataExportsIndex::class)->name('index');
                Route::get('branches/{branch}/{export}', DownloadBranchCsvExportController::class)
                    ->whereIn('export', DataExportType::values())
                    ->name('download');
            });

        Route::prefix('kitchen')
            ->name('kitchen.')
            ->group(function () {
                Route::livewire('dashboard', KitchenDashboard::class)->name('dashboard');
            });

        Route::prefix('bar')
            ->name('bar.')
            ->group(function () {
                Route::livewire('dashboard', BarDashboard::class)->name('dashboard');
            });

        Route::prefix('waiter')
            ->name('waiter.')
            ->group(function () {
                Route::livewire('dashboard', WaiterDashboard::class)->name('dashboard');
                Route::livewire('tables/{tableSession}', WaiterTableDetail::class)->name('tables.show');
            });
    });

Route::middleware(['auth'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::middleware(['superadmin'])
            ->group(function () {
                Route::livewire('dashboard', SuperadminDashboard::class)->name('dashboard');

                Route::prefix('backups')
                    ->name('backups.')
                    ->group(function () {
                        Route::get('sqlite', DownloadSqliteBackupController::class)
                            ->middleware('password.confirm')
                            ->name('sqlite.download');
                    });
            });
    });

require __DIR__.'/settings.php';
