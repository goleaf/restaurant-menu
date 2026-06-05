<?php

namespace Database\Seeders;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(SystemRolesSeeder::class);

        DB::transaction(function (): void {
            Permission::query()
                ->select(['id', 'sort_order'])
                ->orderBy('id')
                ->each(function (Permission $permission): void {
                    $permission->forceFill([
                        'sort_order' => 50000 + (int) $permission->id,
                    ])->save();
                });

            foreach (SystemPermission::seedRows() as $permission) {
                Permission::query()->updateOrCreate(
                    ['code' => $permission['code']],
                    [
                        'name' => $permission['name'],
                        'sort_order' => $permission['sort_order'],
                    ],
                );
            }

            $this->syncBaselineRolePermissions();
        });
    }

    private function syncBaselineRolePermissions(): void
    {
        $permissions = Permission::query()
            ->select(['id', 'code'])
            ->whereIn('code', SystemPermission::values())
            ->get()
            ->keyBy('code');

        Role::query()
            ->select(['id', 'code'])
            ->whereIn('code', SystemRole::values())
            ->orderBy('sort_order')
            ->get()
            ->each(function (Role $role) use ($permissions): void {
                $systemRole = $role->code instanceof SystemRole
                    ? $role->code
                    : SystemRole::from((string) $role->code);

                $enabledCodes = collect(SystemPermission::baselineForRole($systemRole))
                    ->map(fn (SystemPermission $permission): string => $permission->value)
                    ->flip();

                $syncPayload = [];

                foreach (SystemPermission::cases() as $systemPermission) {
                    $permission = $permissions->get($systemPermission->value);

                    if (! $permission instanceof Permission) {
                        continue;
                    }

                    $syncPayload[(int) $permission->id] = [
                        'enabled' => $enabledCodes->has($systemPermission->value),
                    ];
                }

                $role->permissions()->sync($syncPayload, false);
            });
    }
}
