<?php

namespace Database\Seeders;

use App\Support\DemoLogin\DemoEnvironment;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SystemPermissionsSeeder::class);
        $this->call(FirstSuperadminSeeder::class);
        $this->call(KitchenDepartmentsSeeder::class);

        if (app(DemoEnvironment::class)->shouldSeedDatabase()) {
            $this->call(DemoRestaurantSeeder::class);
        }
    }
}
