<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Actions\Bar\ResolveBarAccessibleDepartmentIdsAction;
use App\Actions\Kitchen\ResolveKitchenAccessibleDepartmentIdsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission as Permission;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class WorkspaceAccessQuery
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $permissions,
        private readonly ResolveKitchenAccessibleDepartmentIdsAction $kitchen,
        private readonly ResolveBarAccessibleDepartmentIdsAction $bar,
    ) {}

    /** @return array<string, list<int>> */
    public function destinations(User $user): array
    {
        $permissions = $this->permissions->handleMany($user, [
            Permission::ViewReports, Permission::ViewOrders, Permission::ConfirmOrders,
            Permission::ManageMenu, Permission::ChangeAvailability, Permission::ManageZones,
            Permission::ManageServicePoints, Permission::GenerateQr, Permission::ManageStaff, Permission::ManagePermissions,
            Permission::ExportData, Permission::ManageSettings, Permission::ManageBranches, Permission::ViewAuditLog,
            Permission::ViewKitchen, Permission::SendToKitchen,
        ]);
        $union = static fn (array $codes): array => collect($codes)
            ->flatMap(fn (Permission $code) => $permissions[$code->value])->unique()->values()->all();
        $kitchen = KitchenDepartment::query()->whereIn('id', $this->kitchen->handle($user, $permissions))->distinct()->pluck('branch_id')->all();
        $bar = KitchenDepartment::query()->whereIn('id', $this->bar->handle($user, $permissions))->distinct()->pluck('branch_id')->all();

        return [
            'overview' => array_values(array_unique([...$union([
                Permission::ViewReports, Permission::ViewOrders, Permission::ConfirmOrders,
                Permission::ManageMenu, Permission::ManageServicePoints, Permission::GenerateQr,
            ]), ...$kitchen, ...$bar])),
            'waiter' => $union([Permission::ViewOrders]),
            'kitchen' => $kitchen,
            'bar' => $bar,
            'menu' => $union([Permission::ManageMenu]),
            'availability' => $union([Permission::ChangeAvailability, Permission::ManageMenu, Permission::ManageSettings, Permission::ManageBranches]),
            'halls' => $union([Permission::ManageZones]),
            'tables' => $union([Permission::ManageServicePoints]),
            'qr' => $union([Permission::GenerateQr]),
            'team' => $union([Permission::ManageStaff, Permission::ManagePermissions]),
            'reports' => $union([Permission::ExportData]),
            'report_view' => $union([Permission::ViewReports]),
            'settings' => $union([Permission::ManageSettings, Permission::ManageBranches]),
            'audit' => $union([Permission::ViewAuditLog]),
            'management' => $union([Permission::ViewReports, Permission::ManageMenu, Permission::ManageStaff, Permission::ManageSettings, Permission::ManageBranches]),
        ];
    }

    /** @param array<string, list<int>> $access @return list<int> */
    public function branchIds(array $access): array
    {
        return collect($access)->flatten()->unique()->values()->all();
    }

    /** @param array<string, list<int>> $access */
    public function branch(int $id, array $access): Branch
    {
        abort_unless(in_array($id, $this->branchIds($access), true), 403);

        return $this->branches()->findOrFail($id);
    }

    /**
     * Scanning at most 200 authorized records preserves Unicode matching on SQLite
     * without introducing SQL functions or exposing inaccessible names/counts.
     *
     * @param  array<string, list<int>>|null  $access
     * @return array{options: list<array{id: int, name: string, description: string}>, next: ?int}
     */
    public function search(User $user, string $search = '', int $after = 0, ?array $access = null): array
    {
        $ids = $this->branchIds($access ?? $this->destinations($user));
        $rows = $this->branches()->whereIn('id', $ids)->where('id', '>', $after)->orderBy('id')->limit(201)->get();
        $needle = $this->normalize($search);
        $options = [];
        $next = null;
        $scanned = 0;
        foreach ($rows as $branch) {
            if (count($options) === 20 || $scanned === 200) {
                break;
            }
            $scanned++;
            $next = $branch->id;
            $description = $this->description($branch);
            if ($needle === '' || str_contains($this->normalize($branch->name.' '.$description), $needle)) {
                $options[] = ['id' => $branch->id, 'name' => $branch->name, 'description' => $description];
            }
        }

        return ['options' => $options, 'next' => $scanned < $rows->count() ? $next : null];
    }

    public function description(Branch $branch): string
    {
        return implode(' · ', array_filter([$branch->brand->name, $branch->organization->name, $branch->city]));
    }

    /** @return Builder<Branch> */
    private function branches(): Builder
    {
        return Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'city'])
            ->with(['organization:id,name', 'brand:id,name']);
    }

    private function normalize(string $value): string
    {
        return Str::ascii(mb_strtolower(trim($value)));
    }
}
