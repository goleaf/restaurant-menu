<?php

declare(strict_types=1);

namespace App\Actions\Exports;

use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Enums\SystemPermission;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;

class ResolveExportAccessibleBranchIdsAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    /**
     * @return Collection<int, int<1, max>>
     */
    public function handle(User $user): Collection
    {
        return $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ExportData)
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->sort()
            ->values();
    }

    public function canExport(User $user, Branch|int $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        return $this->handle($user)->contains((int) $branchId);
    }
}
