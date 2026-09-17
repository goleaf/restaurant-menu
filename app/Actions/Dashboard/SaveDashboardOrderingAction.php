<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Models\Branch;
use App\Models\User;

final class SaveDashboardOrderingAction
{
    public function __construct(private readonly UpdateBranchTemporaryClosureAction $updateClosure) {}

    public function handle(User $actor, int $branchId, bool $closed, ?string $reason, ?string $until, int $expectedVersion, string $expectedTimezone, string $requestId): Branch
    {
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id'])->whereKey($branchId)->firstOrFail();

        return $this->updateClosure->handle($actor, $branch, $closed, $reason, $until, $expectedVersion, $expectedTimezone, $requestId);
    }
}
