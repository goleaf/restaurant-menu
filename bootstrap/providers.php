<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\McpServiceProvider;
use App\Providers\ViewServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    McpServiceProvider::class,
    ViewServiceProvider::class,
];
