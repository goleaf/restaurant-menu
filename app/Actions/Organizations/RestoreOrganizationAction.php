<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RestoreOrganizationAction
{
    public function __construct(private readonly RecordAuditLogAction $audit) {}

    public function handle(User $actor, Organization $organization): void
    {
        DB::transaction(function () use ($actor, $organization): void {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $scopedOrganization = Organization::withTrashed()
                ->select(['id', 'owner_user_id', 'name', 'deleted_at'])
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('restore', $scopedOrganization);

            $memberships = OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
                ->where('organization_id', $scopedOrganization->id)->where('status', OrganizationUserStatus::Active)
                ->whereHas('role', fn ($role) => $role->where('code', '!=', SystemRole::Owner->value))->lazyById(200);
            foreach ($memberships as $membership) {
                $membership->forceFill(['status' => OrganizationUserStatus::Suspended, 'access_version' => $membership->access_version + 1]);
                if ($membership->save() !== true) {
                    throw new \RuntimeException('The restored organization staff restriction could not be saved.');
                }
                $this->audit->handle(AuditLogAction::StaffDeactivated, 'organization_user', $membership->id, actorUser: $actor,
                    organizationId: $scopedOrganization->id,
                    oldValues: ['staff_user_id' => $membership->user_id, 'status' => OrganizationUserStatus::Active],
                    newValues: ['staff_user_id' => $membership->user_id, 'status' => OrganizationUserStatus::Suspended, 'reason' => __('center.restore_notice')]);
            }

            $branches = Branch::withTrashed()->select(['id', 'organization_id', 'brand_id', 'name', 'is_active'])
                ->where('organization_id', $scopedOrganization->id)->where('is_active', true)->lazyById(200);
            foreach ($branches as $branch) {
                if ($branch->forceFill(['is_active' => false])->save() !== true) {
                    throw new \RuntimeException('The restored organization restaurant restriction could not be saved.');
                }
                $this->audit->handle(AuditLogAction::BranchSuspended, 'branch', $branch->id, actorUser: $actor,
                    organizationId: $scopedOrganization->id, branchId: $branch->id,
                    oldValues: ['name' => $branch->name, 'is_active' => true],
                    newValues: ['name' => $branch->name, 'is_active' => false, 'reason' => __('center.restore_notice')]);
            }

            if ($scopedOrganization->restore() !== true) {
                throw new \RuntimeException('The structure lifecycle change could not be saved.');
            }
        }, attempts: 3);
    }
}
