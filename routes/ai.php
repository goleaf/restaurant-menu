<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateRestaurantMcp;
use App\Mcp\Servers\RestaurantServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware([AuthenticateRestaurantMcp::class])->prefix('mcp')->name('mcp.')->group(function (): void {
    Mcp::web('restaurant', RestaurantServer::class)->name('restaurant');
});
