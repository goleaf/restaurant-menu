<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Staff;

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Staff\AddOrganizationStaffMemberAction;
use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    private StaffQueryService $staffQueries;

    public Organization $organization;

    public string $manualName = '';

    public string $manualEmail = '';

    public ?int $manualRoleId = null;

    public string $inviteEmail = '';

    public string $invitePhone = '';

    public ?int $inviteRoleId = null;

    public ?string $lastInviteLink = null;

    public ?string $lastInviteCode = null;

    public bool $canManageStaff = false;

    public string $staffDeactivationReason = '';

    public function boot(StaffQueryService $staffQueries): void
    {
        $this->staffQueries = $staffQueries;
    }

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $gate = Gate::forUser($this->currentUser());

        $gate->authorize('view', $organization);

        $this->canManageStaff = $gate->allows('manageStaff', $organization);

        if (! $this->canManageStaff) {
            abort(403);
        }

        $this->manualRoleId = $this->defaultRoleId();
        $this->inviteRoleId = $this->manualRoleId;
    }

    public function addManualStaffMember(AddOrganizationStaffMemberAction $addStaffMember): void
    {
        $this->authorizeStaffManagement();

        $this->manualName = trim($this->manualName);
        $this->manualEmail = trim($this->manualEmail);

        $validated = $this->validate($this->manualStaffRules());
        $role = $this->findAssignableRole((int) $validated['manualRoleId']);

        $addStaffMember->handle($this->organization, $role, $this->currentUser(), [
            'name' => $validated['manualName'],
            'email' => $validated['manualEmail'],
        ]);

        $this->reset('manualName', 'manualEmail');
        unset($this->members);

        Flux::toast(variant: 'success', text: __('staff.messages.staff_created'));
    }

    public function createInviteLink(CreateInvitationAction $createInvitation): void
    {
        $this->createInvitation($createInvitation);

        Flux::toast(variant: 'success', text: __('staff.messages.invitation_created'));
    }

    public function createInviteCode(CreateInvitationAction $createInvitation): void
    {
        $this->createInvitation($createInvitation);

        Flux::toast(variant: 'success', text: __('staff.messages.invitation_created'));
    }

    public function activateMember(int $membershipId, SetOrganizationStaffStatusAction $setStaffStatus): void
    {
        $this->authorizeStaffManagement();
        $membership = $this->findMembership($membershipId);

        Gate::forUser($this->currentUser())->authorize('update', $membership);

        $setStaffStatus->activate($membership);

        unset($this->members);

        Flux::toast(variant: 'success', text: __('staff.messages.staff_reactivated'));
    }

    public function deactivateMember(int $membershipId, SetOrganizationStaffStatusAction $setStaffStatus): void
    {
        $this->authorizeStaffManagement();

        $validated = $this->validate(RestaurantValidationRules::auditReason('staffDeactivationReason'), [
            'staffDeactivationReason.required' => __('staff.errors.deactivation_reason_required'),
            'staffDeactivationReason.min' => __('staff.errors.deactivation_reason_min'),
        ]);

        $membership = $this->findMembership($membershipId);

        Gate::forUser($this->currentUser())->authorize('deactivate', $membership);

        if (! $setStaffStatus->suspend(
            $membership,
            $this->currentUser(),
            (string) $validated['staffDeactivationReason'],
        )) {
            Flux::toast(variant: 'warning', text: __('staff.errors.self_deactivation_blocked'));

            return;
        }

        $this->staffDeactivationReason = '';
        unset($this->members);

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('staff.messages.staff_deactivated'));
    }

    /**
     * @return EloquentCollection<int, OrganizationUser>
     */
    #[Computed]
    public function members(): EloquentCollection
    {
        return $this->staffQueries->organizationMembers($this->organization);
    }

    /**
     * @return EloquentCollection<int, Invitation>
     */
    #[Computed]
    public function invitations(): EloquentCollection
    {
        return $this->staffQueries->organizationInvitations($this->organization);
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    #[Computed]
    public function roles(): EloquentCollection
    {
        return $this->staffQueries->assignableRoles();
    }

    public function render(): View
    {
        return view('livewire.organizations.staff.index', [
            'organizationName' => $this->organization->name,
            'roleOptions' => $this->roles()
                ->map(fn (Role $role): array => ['id' => $role->id, 'label' => $this->roleLabel($role)])
                ->all(),
            'memberRows' => $this->members()
                ->map(fn (OrganizationUser $member): array => [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'user_name' => $member->user->name,
                    'user_email' => $member->user->email,
                    'is_active' => $member->status === OrganizationUserStatus::Active,
                    'localized_status' => $this->memberStatusLabel($member->status),
                    'role_label' => $this->roleLabel($member->role),
                ])
                ->all(),
            'invitationRows' => $this->invitations()
                ->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'role_label' => $this->roleLabel($invitation->role),
                    'localized_status' => $this->invitationStatusLabel($invitation->status),
                    'email' => $invitation->email,
                    'phone' => $invitation->phone,
                ])
                ->all(),
        ])
            ->title(__('staff.organization_access'));
    }

    private function roleLabel(?Role $role): string
    {
        if (! $role instanceof Role) {
            return '';
        }

        return $role->code->localizedLabel();
    }

    private function memberStatusLabel(OrganizationUserStatus $status): string
    {
        return __(sprintf('staff.statuses.%s', $status->value));
    }

    private function invitationStatusLabel(InvitationStatus $status): string
    {
        return __(sprintf('staff.invitation_statuses.%s', $status->value));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function manualStaffRules(): array
    {
        return RestaurantValidationRules::manualStaff($this->assignableRoleRule());
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function invitationRules(): array
    {
        return RestaurantValidationRules::staffInvitation($this->assignableRoleRule());
    }

    private function assignableRoleRule(): mixed
    {
        return Rule::exists((new Role)->getTable(), 'id')
            ->where(fn ($query) => $query->where('code', '!=', SystemRole::Superadmin->value));
    }

    private function createInvitation(CreateInvitationAction $createInvitation): Invitation
    {
        $this->authorizeStaffManagement();

        $this->inviteEmail = trim($this->inviteEmail);
        $this->invitePhone = trim($this->invitePhone);

        $validated = $this->validate($this->invitationRules());
        $role = $this->findAssignableRole((int) $validated['inviteRoleId']);

        $createdInvitation = $createInvitation->handle($this->organization, $role, $this->currentUser(), [
            'email' => $validated['inviteEmail'] === '' ? null : $validated['inviteEmail'],
            'phone' => $validated['invitePhone'] === '' ? null : $validated['invitePhone'],
        ]);

        $this->lastInviteLink = $createdInvitation->inviteLink();
        $this->lastInviteCode = $createdInvitation->code;
        unset($this->invitations);

        return $createdInvitation->invitation;
    }

    private function defaultRoleId(): ?int
    {
        return $this->staffQueries->defaultWaiterRoleId();
    }

    private function findAssignableRole(int $roleId): Role
    {
        return $this->staffQueries->findAssignableRole($roleId);
    }

    private function findMembership(int $membershipId): OrganizationUser
    {
        return $this->staffQueries->findOrganizationMembership($this->organization, $membershipId);
    }

    private function authorizeStaffManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('manageStaff', $this->organization);
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
