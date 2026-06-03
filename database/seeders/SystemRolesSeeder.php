<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SystemRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (SystemRole::seedRows() as $role) {
            Role::query()->updateOrCreate(
                ['code' => $role['code']],
                [
                    'name' => $role['name'],
                    'sort_order' => $role['sort_order'],
                ],
            );
        }
    }
}
