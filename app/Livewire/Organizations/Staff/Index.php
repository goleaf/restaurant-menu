<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Staff;

use App\Actions\Invitations\CancelInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Staff\AddOrganizationStaffMemberAction;
use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Livewire\Forms\Staff\InvitationForm;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use App\Support\LocalizedDateFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 15;

    private StaffQueryService $staffQueries;

    #[Locked]
    public Organization $organization;

    public string $manualName = '';

    public string $manualEmail = '';

    public ?int $manualRoleId = null;

    public InvitationForm $invitationForm;

    public ?string $lastInviteLink = null;

    public ?string $lastInviteCode = null;

    public bool $canManageStaff = false;

    public string $staffDeactivationReason = '';

    public ?int $editingMembershipId = null;

    public ?int $editingRoleId = null;

    public string $staffRoleReason = '';

    public string $staffSearch = '';

    public string $invitationSearch = '';

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
        $this->invitationForm->roleId = $this->manualRoleId;
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

    public function activateMember(int $membershipId, SetOrganizationStaffStatusAction $setStaffStatus): void
    {
        $this->authorizeStaffManagement();
        $membership = $this->findMembership($membershipId);

        Gate::forUser($this->currentUser())->authorize('update', $membership);

        $setStaffStatus->activate($membership, $this->currentUser());

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

    public function startEditingRole(int $membershipId): void
    {
        $this->authorizeStaffManagement();
        $membership = $this->findMembership($membershipId);

        $this->resetValidation(['editingRoleId', 'staffRoleReason']);
        $this->editingMembershipId = $membership->id;
        $this->editingRoleId = $membership->role_id;
        $this->staffRoleReason = '';
    }

    public function cancelEditingRole(): void
    {
        $this->reset('editingMembershipId', 'editingRoleId', 'staffRoleReason');
        $this->resetValidation(['editingRoleId', 'staffRoleReason']);
    }

    public function updateRole(UpdateOrganizationStaffRoleAction $updateRole): void
    {
        $this->authorizeStaffManagement();

        $validated = $this->validate([
            'editingMembershipId' => ['required', 'integer'],
            'editingRoleId' => ['required', 'integer', $this->assignableRoleRule()],
            ...RestaurantValidationRules::auditReason('staffRoleReason'),
        ]);

        $updateRole->handle(
            $this->currentUser(),
            $this->organization,
            $this->findMembership((int) $validated['editingMembershipId']),
            $this->findAssignableRole((int) $validated['editingRoleId']),
            (string) $validated['staffRoleReason'],
        );

        $this->cancelEditingRole();
        unset($this->members);

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('staff.messages.role_updated'));
    }

    public function cancelInvitation(int $invitationId, CancelInvitationAction $cancelInvitation): void
    {
        $this->authorizeStaffManagement();
        $invitation = $this->staffQueries->findOrganizationInvitation($this->organization, $invitationId);

        $cancelInvitation->handle($this->currentUser(), $this->organization, $invitation);

        $this->lastInviteLink = null;
        $this->lastInviteCode = null;
        unset($this->invitations);

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('staff.messages.invitation_cancelled'));
    }

    public function reissueInvitation(int $invitationId, ReissueInvitationAction $reissueInvitation): void
    {
        $this->authorizeStaffManagement();
        $invitation = $this->staffQueries->findOrganizationInvitation($this->organization, $invitationId);
        $reissued = $reissueInvitation->handle($this->currentUser(), $this->organization, $invitation);

        $this->lastInviteLink = $reissued->inviteLink();
        $this->lastInviteCode = $reissued->code;
        unset($this->invitations);

        Flux::toast(variant: 'success', text: __('staff.messages.invitation_reissued'));
    }

    public function updatedStaffSearch(): void
    {
        $this->resetPage(pageName: 'organizationStaffPage');
        unset($this->members);
    }

    public function updatedInvitationSearch(): void
    {
        $this->resetPage(pageName: 'organizationInvitationsPage');
        unset($this->invitations);
    }

    /** @return Paginator<int, OrganizationUser> */
    #[Computed]
    public function members(): Paginator
    {
        return $this->staffQueries->paginateOrganizationMembers(
            $this->organization,
            $this->staffSearch,
            self::PER_PAGE,
        );
    }

    /** @return Paginator<int, Invitation> */
    #[Computed]
    public function invitations(): Paginator
    {
        return $this->staffQueries->paginateOrganizationInvitations(
            $this->organization,
            $this->invitationSearch,
            self::PER_PAGE,
        );
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    #[Computed]
    public function roles(): EloquentCollection
    {
        return $this->staffQueries->assignableRoles($this->currentUser(), $this->organization);
    }

    public function render(): View
    {
        $members = $this->members();
        $invitations = $this->invitations();

        return view('livewire.organizations.staff.index', [
            'organizationName' => $this->organization->name,
            'roleOptions' => $this->roles()
                ->map(fn (Role $role): array => ['id' => $role->id, 'label' => $this->roleLabel($role)])
                ->all(),
            'memberRows' => $members
                ->getCollection()
                ->map(fn (OrganizationUser $member): array => [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'user_name' => $member->user->name,
                    'user_email' => $member->user->email,
                    'is_active' => $member->status === OrganizationUserStatus::Active,
                    'can_edit_role' => (int) $member->user_id !== (int) $this->currentUser()->id,
                    'role_id' => $member->role_id,
                    'localized_status' => $this->memberStatusLabel($member->status),
                    'role_label' => $this->roleLabel($member->role),
                ])
                ->all(),
            'membersPaginator' => $members,
            'invitationRows' => $invitations
                ->getCollection()
                ->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'role_label' => $this->roleLabel($invitation->role),
                    'localized_status' => $this->invitationStatusLabel($invitation->effectiveStatus()),
                    'email' => $invitation->email,
                    'phone' => $invitation->phone,
                    'created_by' => $invitation->invitedBy?->name,
                    'accepted_by' => $invitation->acceptedBy?->name,
                    'created_at' => LocalizedDateFormatter::dateTime($invitation->created_at),
                    'expires_at' => LocalizedDateFormatter::dateTime($invitation->expires_at),
                    'accepted_at' => LocalizedDateFormatter::dateTime($invitation->accepted_at),
                    'can_cancel' => $invitation->effectiveStatus() === InvitationStatus::Pending,
                    'can_reissue' => in_array($invitation->effectiveStatus(), [InvitationStatus::Pending, InvitationStatus::Expired], true),
                ])
                ->all(),
            'invitationsPaginator' => $invitations,
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

    private function assignableRoleRule(): mixed
    {
        return Rule::in($this->roles()->modelKeys());
    }

    private function createInvitation(CreateInvitationAction $createInvitation): Invitation
    {
        $this->authorizeStaffManagement();

        $validated = $this->invitationForm->validated($this->assignableRoleRule());
        $role = $this->findAssignableRole($validated['roleId']);

        $createdInvitation = $createInvitation->handle($this->organization, $role, $this->currentUser(), [
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        $this->lastInviteLink = $createdInvitation->inviteLink();
        $this->lastInviteCode = $createdInvitation->code;
        $this->invitationForm->clearRecipient();
        unset($this->invitations);

        return $createdInvitation->invitation;
    }

    private function defaultRoleId(): ?int
    {
        return $this->staffQueries->defaultWaiterRoleId();
    }

    private function findAssignableRole(int $roleId): Role
    {
        return $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $roleId);
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
