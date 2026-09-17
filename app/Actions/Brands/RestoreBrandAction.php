<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreBrandAction
{
    public function __construct(private readonly RecordAuditLogAction $audit) {}

    public function handle(User $actor, Organization $organization, Brand $brand): void
    {
        DB::transaction(function () use ($actor, $organization, $brand): void {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $organization = Organization::query()->select(['id', 'owner_user_id'])->whereKey($organization->id)->firstOrFail();
            $scopedBrand = $organization->brands()
                ->withTrashed()
                ->select(['brands.id', 'brands.organization_id', 'brands.name', 'brands.deleted_at'])
                ->whereKey($brand->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('restore', $scopedBrand);

            $branches = Branch::withTrashed()->select(['id', 'organization_id', 'brand_id', 'name', 'is_active'])
                ->where('organization_id', $organization->id)->where('brand_id', $scopedBrand->id)
                ->where('is_active', true)->lazyById(200);
            foreach ($branches as $branch) {
                if ($branch->forceFill(['is_active' => false])->save() !== true) {
                    throw new \RuntimeException('The restored brand restaurant restriction could not be saved.');
                }
                $this->audit->handle(AuditLogAction::BranchSuspended, 'branch', $branch->id, actorUser: $actor,
                    organizationId: $organization->id, branchId: $branch->id,
                    oldValues: ['name' => $branch->name, 'is_active' => true],
                    newValues: ['name' => $branch->name, 'is_active' => false, 'reason' => __('center.restore_notice')]);
            }

            if ($scopedBrand->restore() !== true) {
                throw new \RuntimeException('The structure lifecycle change could not be saved.');
            }
        }, attempts: 3);
    }
}
