<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First Superadmin
    |--------------------------------------------------------------------------
    |
    | Set these values in .env before running the database seeder if the first
    | platform-level superadmin should be created automatically.
    |
    */

    'first_superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Platform Superadmin'),
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

];
