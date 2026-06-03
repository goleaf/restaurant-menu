<?php

use App\Livewire\Organizations\Brands\Branches\Areas as OrganizationBrandBranchAreas;
use App\Livewire\Organizations\Brands\Branches\Index as OrganizationBrandBranchesIndex;
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
use App\Livewire\Superadmin\Dashboard as SuperadminDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['web'])
    ->prefix('guest')
    ->name('guest.')
    ->group(function () {
        Route::livewire('/', 'pages::guest.home')->name('home');
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

Route::middleware(['auth'])
    ->prefix('organizations')
    ->name('organizations.')
    ->group(function () {
        Route::livewire('/', OrganizationsIndex::class)->name('index');

        Route::livewire('{organization}/staff', OrganizationStaffIndex::class)->name('staff.index');
        Route::livewire('{organization}/staff/{staffMember}/permissions', OrganizationStaffPermissions::class)->name('staff.permissions');

        Route::prefix('{organization}/brands')
            ->name('brands.')
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
        Route::livewire('dashboard', 'pages::restaurant.dashboard')->name('dashboard');
    });

Route::middleware(['auth'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        Route::middleware(['superadmin'])
            ->group(function () {
                Route::livewire('dashboard', SuperadminDashboard::class)->name('dashboard');
            });
    });

require __DIR__.'/settings.php';
