<?php

declare(strict_types=1);

namespace App\Actions\Brands;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
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

            $this->restrictFormerStaff($actor, $organization, $scopedBrand);

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
                    newValues: ['name' => $branch->name, 'is_active' => false, 'reason' => __('center.restore_brand_notice')]);
            }

            if ($scopedBrand->restore() !== true) {
                throw new \RuntimeException('The structure lifecycle change could not be saved.');
            }
        }, attempts: 3);
    }

    private function restrictFormerStaff(User $actor, Organization $organization, Brand $brand): void
    {
        $brandBranches = Branch::withTrashed()->select('id')->where('organization_id', $organization->id)->where('brand_id', $brand->id);
        if (! $brandBranches->exists()) {
            return;
        }
        $memberships = OrganizationUser::query()->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'access_version'])
            ->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Active)
            ->whereHas('role', fn ($role) => $role->where('code', '!=', SystemRole::Owner->value))
            ->whereDoesntHave('user.roles', fn ($role) => $role->where('code', SystemRole::Superadmin->value))
            ->withExists(['user as has_explicit_assignments' => fn ($user) => $user->whereHas('branchAssignments', fn ($assignment) => $assignment->where('organization_id', $organization->id))])
            ->lazyById(200);
        foreach ($memberships as $membership) {
            if (! $membership->getAttribute('has_explicit_assignments')) {
                $this->materializeRestrictedScope($actor, $organization, $brand, $membership);

                continue;
            }
            $assignments = BranchUser::query()->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'access_version'])
                ->where('organization_id', $organization->id)->where('user_id', $membership->user_id)
                ->whereIn('branch_id', clone $brandBranches)->where('status', OrganizationUserStatus::Active)->lazyById(200);
            $changed = false;
            foreach ($assignments as $assignment) {
                if (! $changed) {
                    $this->advanceAccessVersion($membership);
                    $changed = true;
                }
                $assignment->forceFill(['status' => OrganizationUserStatus::Suspended, 'access_version' => $assignment->access_version + 1]);
                if ($assignment->save() !== true) {
                    throw new \RuntimeException('The restored brand staff restriction could not be saved.');
                }
                $this->recordAssignmentRestriction($actor, $assignment, false);
            }
        }
    }

    private function materializeRestrictedScope(User $actor, Organization $organization, Brand $brand, OrganizationUser $membership): void
    {
        $this->advanceAccessVersion($membership);
        $branches = Branch::withTrashed()->select(['id', 'organization_id', 'brand_id'])
            ->where('organization_id', $organization->id)
            ->where(fn ($query) => $query->where('brand_id', $brand->id)
                ->orWhere(fn ($outside) => $outside->whereNull('branches.deleted_at')
                    ->whereHas('brand', fn ($parent) => $parent->whereNull('brands.deleted_at')->where('organization_id', $organization->id))))
            ->lazyById(200);
        foreach ($branches as $branch) {
            $restricted = $branch->brand_id === $brand->id;
            $assignment = new BranchUser;
            $assignment->forceFill([
                'organization_id' => $organization->id, 'branch_id' => $branch->id,
                'user_id' => $membership->user_id, 'role_id' => $membership->role_id,
                'status' => $restricted ? OrganizationUserStatus::Suspended : OrganizationUserStatus::Active,
                'access_version' => $restricted ? 1 : 0, 'assigned_at' => now(), 'assigned_by_user_id' => $actor->id,
            ]);
            if ($assignment->save() !== true) {
                throw new \RuntimeException('The restored brand staff scope could not be saved.');
            }
            $this->recordAssignmentRestriction($actor, $assignment, true);
        }
    }

    private function advanceAccessVersion(OrganizationUser $membership): void
    {
        if ($membership->forceFill(['access_version' => $membership->access_version + 1])->save() !== true) {
            throw new \RuntimeException('The restored brand staff access version could not be saved.');
        }
    }

    private function recordAssignmentRestriction(User $actor, BranchUser $assignment, bool $created): void
    {
        $this->audit->handle($assignment->status === OrganizationUserStatus::Suspended ? AuditLogAction::StaffDeactivated : AuditLogAction::StaffPermissionChanged,
            'branch_user', $assignment->id, actorUser: $actor, organizationId: $assignment->organization_id, branchId: $assignment->branch_id,
            oldValues: ['staff_user_id' => $assignment->user_id, 'status' => OrganizationUserStatus::Active, 'branch_access_mode' => $created ? 'organization' : 'assignments'],
            newValues: ['staff_user_id' => $assignment->user_id, 'status' => $assignment->status, 'branch_access_mode' => 'assignments', 'assignment_created' => $created, 'reason' => __('center.restore_brand_notice')]);
    }
}
