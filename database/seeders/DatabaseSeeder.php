<?php

namespace Database\Seeders;

use App\Support\DemoLogin\DemoEnvironment;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
