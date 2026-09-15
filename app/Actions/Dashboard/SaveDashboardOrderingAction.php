<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Models\Branch;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class SaveDashboardOrderingAction
{
    public function __construct(private readonly UpdateBranchTemporaryClosureAction $updateClosure) {}

    public function handle(User $actor, int $branchId, bool $closed, ?string $reason, ?string $until): Branch
    {
        $values = Validator::make([
            'temporarilyClosed' => $closed,
            'temporaryClosedReason' => $reason,
            'temporaryClosedUntil' => $until,
        ], RestaurantValidationRules::temporaryClosure($closed))->validate();

        return DB::transaction(function () use ($actor, $branchId, $closed, $values): Branch {
            $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'timezone', 'is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until', 'deleted_at'])
                ->whereKey($branchId)->firstOrFail();
            Gate::forUser($actor->fresh() ?? $actor)->authorize('manageSettings', $branch);

            return $this->updateClosure->handle($branch, $closed, $values['temporaryClosedReason'], $values['temporaryClosedUntil']);
        });
    }
}
