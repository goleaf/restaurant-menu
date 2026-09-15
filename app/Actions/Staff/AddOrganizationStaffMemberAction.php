<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\OrganizationUserStatus;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Existing-member lookup only; account provisioning belongs to invitations or setup. */
class AddOrganizationStaffMemberAction
{
    /** @param array{name?: string, email: string} $data */
    public function handle(Organization $organization, Role $role, User $assignedBy, array $data, bool $replaceExistingMembershipRole = true): User
    {
        Gate::forUser($assignedBy)->authorize('manageStaff', $organization);
        Gate::forUser($assignedBy)->authorize('assign', [$role, $organization]);
        $email = mb_strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)
            ->whereHas('organizationMemberships', fn ($query) => $query->where('organization_id', $organization->id)
                ->where('status', OrganizationUserStatus::Active->value))->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages(['email' => __('staff.errors.invitation_required')]);
        }

        return $user;
    }
}
