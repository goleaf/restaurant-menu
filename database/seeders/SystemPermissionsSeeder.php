<?php

namespace Database\Seeders;

use App\Enums\SystemPermission;
use App\Models\Permission;
use App\Models\PermissionRole;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SystemPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(SystemRolesSeeder::class);

        foreach (SystemPermission::seedRows() as $permission) {
            Permission::query()->updateOrCreate(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'sort_order' => $permission['sort_order'],
                ],
            );
        }

        $roleIds = Role::query()
            ->orderBy('id')
            ->pluck('id');

        $permissionIds = Permission::query()
            ->orderBy('id')
            ->pluck('id');

        $now = now();
        $rows = [];

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'enabled' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        PermissionRole::query()->insertOrIgnore($rows);
    }
}
