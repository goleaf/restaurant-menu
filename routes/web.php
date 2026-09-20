<?php

declare(strict_types=1);

use App\Enums\DataExportType;
use App\Http\Controllers\Invitations\ShowInvitationController;
use App\Http\Controllers\Restaurant\DownloadPreparedFileController;
use App\Http\Controllers\Superadmin\RestoreSqliteBackupController;
use App\Livewire\AuditLogs\Index as AuditLogIndex;
use App\Livewire\Bar\Dashboard as BarDashboard;
use App\Livewire\Departments\Dashboard as PreparationDashboard;
use App\Livewire\Departments\TicketPrint as DepartmentTicketPrint;
use App\Livewire\Exports\Index as DataExportsIndex;
use App\Livewire\Guest\Home as GuestHome;
use App\Livewire\Invitations\Show as InvitationPage;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Livewire\Local\ComponentReference;
use App\Livewire\Local\DemoLogin;
use App\Livewire\Onboarding\RestaurantSetup as RestaurantOnboarding;
use App\Livewire\Organizations\Brands\Branches\Areas as OrganizationBrandBranchAreas;
use App\Livewire\Organizations\Brands\Branches\Availability\Index;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Livewire\Organizations\Brands\Branches\Menu\Index as OrganizationBrandBranchMenuIndex;
use App\Livewire\Organizations\Brands\Branches\Qr\BulkPrint as OrganizationBrandBranchQrBulkPrint;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as OrganizationBrandBranchServicePointsIndex;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\PrintTemplate as OrganizationBrandBranchServicePointQrPrintTemplate;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Qr\Show as OrganizationBrandBranchServicePointQrShow;
use App\Livewire\Organizations\Brands\Branches\Settings as OrganizationBrandBranchSettings;
use App\Livewire\Organizations\Brands\Branches\Staff\Index as OrganizationBrandBranchStaffIndex;
use App\Livewire\Organizations\Staff\Index as OrganizationStaffIndex;
use App\Livewire\Organizations\Staff\Show;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Livewire\QrCodes\ShortCodeLookup as QrShortCodeLookup;
use App\Livewire\Restaurant\Dashboard as RestaurantDashboard;
use App\Livewire\Superadmin\Backups\RestoreSqlite;
use App\Livewire\Superadmin\Dashboard as SuperadminDashboard;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Livewire\Waiter\TableDetail as WaiterTableDetail;
use App\Livewire\Workspace\Entry;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['web'])
    ->prefix('guest')
    ->name('guest.')
    ->group(function () {
        Route::livewire('/', GuestHome::class)->name('home');
    });

Route::middleware(['web', 'throttle:public-qr'])
    ->prefix('q')
    ->name('public.qr.')
    ->group(function () {
        Route::livewire('{token}', PublicQrShow::class)
            ->where('token', '[A-Za-z0-9]+')
            ->name('show');
    });

Route::middleware(['demo-login', 'guest', 'throttle:demo-login'])
    ->prefix('demo-login')
    ->name('demo-login.')
    ->group(function (): void {
        Route::livewire('/', DemoLogin::class)->name('index');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('dashboard', Entry::class)->name('dashboard');
});

Route::middleware(['auth', 'superadmin'])
    ->prefix('local')
    ->name('local.')
    ->group(function (): void {
        Route::livewire('components', ComponentReference::class)->name('components');
    });

Route::middleware(['throttle:staff-invitations'])
    ->prefix('invite')
    ->name('invitations.')
    ->group(function () {
        Route::livewire('pending', InvitationPage::class)->name('pending');
        Route::get('{token}', ShowInvitationController::class)
            ->where('token', '[A-Za-z0-9]{1,128}')
            ->name('show');
    });

Route::middleware(['auth'])
    ->prefix('restaurants')
    ->name('restaurants.')
    ->group(function (): void {
        Route::livewire('/', App\Livewire\Restaurants\Index::class)->name('index');
        Route::livewire('create', RestaurantOnboarding::class)->name('create');
        Route::livewire('setup/{setup}', RestaurantOnboarding::class)->whereNumber('setup')->name('setup');
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
        Route::livewire('/', App\Livewire\Restaurants\Index::class)->name('index');

        Route::livewire('{organization}/staff', OrganizationStaffIndex::class)->name('staff.index');
        Route::livewire('{organization}/staff/members/{member}', Show::class)->name('staff.show');
        Route::livewire('{organization}/staff/{staffMember}/permissions', Show::class)->name('staff.permissions');

        Route::prefix('{organization}/brands')
            ->name('brands.')
            ->scopeBindings()
            ->group(function () {
                Route::livewire('/', App\Livewire\Restaurants\Index::class)->name('index');

                Route::prefix('{brand}/branches')
                    ->name('branches.')
                    ->group(function () {
                        Route::livewire('/', App\Livewire\Restaurants\Index::class)->name('index');

                        Route::prefix('{branch}/areas')
                            ->name('areas.')
                            ->group(function () {
                                Route::livewire('/', OrganizationBrandBranchAreas::class)->name('index');
                            });

                        Route::prefix('{branch}/menu')
                            ->name('menu.')
                            ->group(function () {
                                Route::livewire('/', OrganizationBrandBranchMenuIndex::class)->name('index');
                                Route::livewire('items/create', Dish::class)->name('dish.create');
                                Route::livewire('items/{item}', Dish::class)->withoutScopedBindings()->whereNumber('item')->name('dish.edit');
                            });

                        Route::prefix('{branch}/availability')
                            ->name('availability.')
                            ->group(function () {
                                Route::livewire('/', Index::class)->name('index');
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
                                Route::livewire('members/{member}', Show::class)->withoutScopedBindings()->name('show');
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
        Route::get('files/{grant}', DownloadPreparedFileController::class)
            ->where('grant', '[A-Za-z0-9]{64}')
            ->name('files.download');
        Route::livewire('qr-lookup', QrShortCodeLookup::class)->name('qr-lookup.index');
        Route::livewire('audit-log', AuditLogIndex::class)->name('audit-log.index');

        Route::prefix('preparation')->name('preparation.')->group(function () {
            Route::livewire('/', PreparationDashboard::class)->name('dashboard');
        });

        Route::prefix('departments')
            ->name('departments.')
            ->group(function () {
                Route::livewire('tickets/{kitchenTicket}/print', DepartmentTicketPrint::class)->name('tickets.print');
            });

        Route::prefix('exports')
            ->name('exports.')
            ->group(function () {
                Route::livewire('/', DataExportsIndex::class)->name('index');
                Route::livewire('branches/{branch}/{export}', DataExportsIndex::class)
                    ->whereIn('export', DataExportType::values())
                    ->name('download');
                Route::livewire('pdf/branches/{branch}/{export}', DataExportsIndex::class)
                    ->whereIn('export', DataExportType::values())
                    ->name('pdf');
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
                        Route::redirect('sqlite', '/superadmin/dashboard')
                            ->middleware('password.confirm')
                            ->name('sqlite.download');
                        Route::redirect('media', '/superadmin/dashboard')
                            ->middleware('password.confirm')
                            ->name('media.download');
                        Route::livewire('sqlite/restore', RestoreSqlite::class)
                            ->middleware('password.confirm')
                            ->name('sqlite.restore');
                        Route::post('sqlite/restore', RestoreSqliteBackupController::class)
                            ->middleware('password.confirm')
                            ->name('sqlite.restore.store');
                    });
            });
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
