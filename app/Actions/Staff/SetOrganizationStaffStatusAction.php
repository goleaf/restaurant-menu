<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\OrganizationUserStatus;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class SetOrganizationStaffStatusAction
{
    public function __construct(
        private readonly RecordAuditLogAction $recordAuditLog,
    ) {}

    public function activate(OrganizationUser $membership, User $actor): OrganizationUser
    {
        $this->authorize($membership, $actor, 'update');

        $membership->forceFill([
            'status' => OrganizationUserStatus::Active,
            'joined_at' => $membership->joined_at ?? now(),
        ])->saveOrFail();

        return $membership;
    }

    public function suspend(OrganizationUser $membership, User $actor, string $reason): bool
    {
        Gate::forUser($actor)->authorize('deactivate', $membership);

        if ($membership->user_id === $actor->id) {
            return false;
        }

        $this->authorizeRole($membership, $actor);

        $previousStatus = $membership->status;
        $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->saveOrFail();

        $this->recordAuditLog->handle(
            action: AuditLogAction::StaffDeactivated,
            entityType: 'organization_user',
            entityId: $membership->id,
            actorUser: $actor,
            organizationId: $membership->organization_id,
            oldValues: [
                'staff_user_id' => $membership->user_id,
                'status' => $previousStatus,
            ],
            newValues: [
                'staff_user_id' => $membership->user_id,
                'status' => OrganizationUserStatus::Suspended,
                'reason' => $reason,
            ],
        );

        return true;
    }

    private function authorize(OrganizationUser $membership, User $actor, string $ability): void
    {
        Gate::forUser($actor)->authorize($ability, $membership);
        $this->authorizeRole($membership, $actor);
    }

    private function authorizeRole(OrganizationUser $membership, User $actor): void
    {
        $organization = Organization::query()->whereKey($membership->organization_id)->firstOrFail();
        $role = Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->whereKey($membership->role_id)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('assign', [$role, $organization]);
    }
}
