<?php

namespace App\Actions\Organizations;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteOrganizationAction
{
    public function handle(User $actor, Organization $organization): void
    {
        DB::transaction(function () use ($actor, $organization): void {
            $scopedOrganization = Organization::query()
                ->select(['id', 'owner_user_id', 'name'])
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('delete', $scopedOrganization);

            $hasActiveOrder = Order::query()
                ->active()
                ->whereIn('branch_id', Branch::withTrashed()
                    ->select('id')
                    ->where('organization_id', $scopedOrganization->id))
                ->exists();

            if ($hasActiveOrder) {
                throw BusinessRuleViolation::for(
                    BusinessRuleCode::StructureHasActiveOrder,
                    'structureDeletion',
                    context: ['organization_id' => $scopedOrganization->id],
                );
            }

            $scopedOrganization->deleteOrFail();
        });
    }
}
