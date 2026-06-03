<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class FirstSuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(SystemRolesSeeder::class);

        $email = trim((string) config('platform.first_superadmin.email', ''));

        if ($email === '') {
            return;
        }

        $name = trim((string) config('platform.first_superadmin.name', 'Platform Superadmin')) ?: 'Platform Superadmin';
        $password = (string) config('platform.first_superadmin.password', '');

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            if (trim($password) === '') {
                return;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
        }

        $role = Role::query()
            ->where('code', SystemRole::Superadmin->value)
            ->firstOrFail();

        $user->roles()->syncWithoutDetachingOrFail([$role->id]);
    }
}
