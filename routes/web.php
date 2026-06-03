<?php

use App\Livewire\Organizations\Brands\Branches\Index as OrganizationBrandBranchesIndex;
use App\Livewire\Organizations\Brands\Branches\Settings as OrganizationBrandBranchSettings;
use App\Livewire\Organizations\Brands\Index as OrganizationBrandsIndex;
use App\Livewire\Organizations\Index as OrganizationsIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['web'])
    ->prefix('guest')
    ->name('guest.')
    ->group(function () {
        Route::livewire('/', 'pages::guest.home')->name('home');
    });

Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth'])
    ->prefix('organizations')
    ->name('organizations.')
    ->group(function () {
        Route::livewire('/', OrganizationsIndex::class)->name('index');

        Route::prefix('{organization}/brands')
            ->name('brands.')
            ->group(function () {
                Route::livewire('/', OrganizationBrandsIndex::class)->name('index');

                Route::prefix('{brand}/branches')
                    ->name('branches.')
                    ->group(function () {
                        Route::livewire('/', OrganizationBrandBranchesIndex::class)->name('index');

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
        Route::livewire('dashboard', 'pages::superadmin.dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
