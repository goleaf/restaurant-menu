<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches\Staff;

use App\Actions\Invitations\CancelInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Actions\Staff\SetBranchStaffStatusAction;
use App\Actions\Staff\SyncWaiterAreaAssignmentsAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Forms\Staff\InvitationForm;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
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

    #[Locked]
    public Brand $brand;

    #[Locked]
    public Branch $branch;

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

    /**
     * @var array<int, list<string>>
     */
    public array $areaAssignments = [];

    public function boot(StaffQueryService $staffQueries): void
    {
        $this->staffQueries = $staffQueries;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->branch = $branch;

        if ($brand->organization_id !== $organization->id || $branch->organization_id !== $organization->id || $branch->brand_id !== $brand->id) {
            abort(403);
        }

        $gate = Gate::forUser($this->currentUser());
        $gate->authorize('view', $branch);

        $this->canManageStaff = $gate->allows('manageStaff', $branch);

        if (! $this->canManageStaff) {
            abort(403);
        }

        $this->manualRoleId = $this->defaultRoleId();
        $this->invitationForm->roleId = $this->manualRoleId;
        $this->loadAreaAssignments();
    }

    public function addManualStaffMember(AddBranchStaffMemberAction $addStaffMember): void
    {
        $this->authorizeStaffManagement();

        $this->manualName = trim($this->manualName);
        $this->manualEmail = trim($this->manualEmail);

        $validated = $this->validate($this->manualStaffRules());
        $role = $this->findAssignableRole((int) $validated['manualRoleId']);

        $addStaffMember->handle($this->organization, $this->branch, $role, $this->currentUser(), [
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

    public function activateMember(int $branchUserId, SetBranchStaffStatusAction $setStaffStatus): void
    {
        $this->authorizeStaffManagement();

        $setStaffStatus->activate($this->findBranchUser($branchUserId), $this->currentUser());

        unset($this->members);

        Flux::toast(variant: 'success', text: __('staff.messages.staff_reactivated'));
    }

    public function deactivateMember(int $branchUserId, SetBranchStaffStatusAction $setStaffStatus): void
    {
        $this->authorizeStaffManagement();

        $validated = $this->validate(RestaurantValidationRules::auditReason('staffDeactivationReason'), [
            'staffDeactivationReason.required' => __('staff.errors.deactivation_reason_required'),
            'staffDeactivationReason.min' => __('staff.errors.deactivation_reason_min'),
        ]);

        $branchUser = $this->findBranchUser($branchUserId);

        if (! $setStaffStatus->suspend(
            $branchUser,
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

    public function startEditingRole(int $branchUserId): void
    {
        $this->authorizeStaffManagement();
        $branchUser = $this->findBranchUser($branchUserId);

        $this->resetValidation(['editingRoleId', 'staffRoleReason']);
        $this->editingMembershipId = $branchUser->id;
        $this->editingRoleId = $branchUser->role_id;
        $this->staffRoleReason = '';
    }

    public function cancelEditingRole(): void
    {
        $this->reset('editingMembershipId', 'editingRoleId', 'staffRoleReason');
        $this->resetValidation(['editingRoleId', 'staffRoleReason']);
    }

    public function updateRole(UpdateBranchStaffRoleAction $updateRole): void
    {
        $this->authorizeStaffManagement();

        $validated = $this->validate([
            'editingMembershipId' => ['required', 'integer'],
            'editingRoleId' => ['required', 'integer', $this->assignableRoleRule()],
            ...RestaurantValidationRules::auditReason('staffRoleReason'),
        ]);

        $branchUser = $updateRole->handle(
            $this->currentUser(),
            $this->branch,
            $this->findBranchUser((int) $validated['editingMembershipId']),
            $this->findAssignableRole((int) $validated['editingRoleId']),
            (string) $validated['staffRoleReason'],
        );

        $this->cancelEditingRole();
        $this->loadAreaAssignments();
        unset($this->members);

        if (! $this->memberIsWaiter($branchUser->load('role'))) {
            unset($this->areaNodes);
        }

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('staff.messages.role_updated'));
    }

    public function cancelInvitation(int $invitationId, CancelInvitationAction $cancelInvitation): void
    {
        $this->authorizeStaffManagement();
        $invitation = $this->staffQueries->findBranchInvitation($this->organization, $this->branch, $invitationId);

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
        $invitation = $this->staffQueries->findBranchInvitation($this->organization, $this->branch, $invitationId);
        $reissued = $reissueInvitation->handle($this->currentUser(), $this->organization, $invitation);

        $this->lastInviteLink = $reissued->inviteLink();
        $this->lastInviteCode = $reissued->code;
        unset($this->invitations);

        Flux::toast(variant: 'success', text: __('staff.messages.invitation_reissued'));
    }

    public function saveAreaAssignments(int $userId, SyncWaiterAreaAssignmentsAction $syncAssignments): void
    {
        $this->authorizeStaffManagement();

        $branchUser = $this->findBranchUserByUser($userId);

        if (! $this->memberIsWaiter($branchUser)) {
            abort(403);
        }

        $selectedAreaIds = collect($this->areaAssignments[$userId] ?? [])
            ->map(fn (mixed $areaNodeId): int => (int) $areaNodeId)
            ->filter(fn (int $areaNodeId): bool => $areaNodeId > 0)
            ->unique()
            ->values();
        $validAreaIds = $this->areaNodes()->pluck('id')->map(fn (int $areaNodeId): int => $areaNodeId)->values();

        if ($selectedAreaIds->diff($validAreaIds)->isNotEmpty()) {
            $this->addError('areaAssignments.'.$userId, __('staff.errors.zone_unavailable'));

            return;
        }

        $selectedAreaIds = collect($syncAssignments->handle(
            $this->branch,
            $branchUser,
            $this->currentUser(),
            $selectedAreaIds->all(),
        ));

        $this->areaAssignments[$userId] = $selectedAreaIds
            ->map(fn (int $areaNodeId): string => (string) $areaNodeId)
            ->values()
            ->all();

        Flux::toast(variant: 'success', text: __('staff.messages.waiter_zones_updated'));
    }

    public function updatedStaffSearch(): void
    {
        $this->resetPage(pageName: 'branchStaffPage');
        unset($this->members);
    }

    public function updatedInvitationSearch(): void
    {
        $this->resetPage(pageName: 'branchInvitationsPage');
        unset($this->invitations);
    }

    /** @return Paginator<int, BranchUser> */
    #[Computed]
    public function members(): Paginator
    {
        return $this->staffQueries->paginateBranchMembers(
            $this->branch,
            $this->staffSearch,
            self::PER_PAGE,
        );
    }

    /** @return Paginator<int, Invitation> */
    #[Computed]
    public function invitations(): Paginator
    {
        return $this->staffQueries->paginateBranchInvitations(
            $this->organization,
            $this->branch,
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

    /**
     * @return EloquentCollection<int, AreaNode>
     */
    #[Computed]
    public function areaNodes(): EloquentCollection
    {
        return $this->staffQueries->activeAreaNodes($this->branch);
    }

    private function memberIsWaiter(BranchUser $member): bool
    {
        return $member->role?->code === SystemRole::Waiter;
    }

    public function render(): View
    {
        $members = $this->members();
        $invitations = $this->invitations();

        return view('livewire.organizations.brands.branches.staff.index', [
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'roleOptions' => $this->roles()
                ->map(fn (Role $role): array => ['id' => $role->id, 'label' => $this->roleLabel($role)])
                ->all(),
            'memberRows' => $members
                ->getCollection()
                ->map(fn (BranchUser $member): array => [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'user_name' => $member->user->name,
                    'user_email' => $member->user->email,
                    'is_active' => $member->status === OrganizationUserStatus::Active,
                    'can_edit_role' => (int) $member->user_id !== (int) $this->currentUser()->id,
                    'role_id' => $member->role_id,
                    'is_waiter' => $this->memberIsWaiter($member),
                    'localized_status' => $this->memberStatusLabel($member->status),
                    'role_label' => $this->roleLabel($member->role),
                ])
                ->all(),
            'membersPaginator' => $members,
            'areaNodeOptions' => $this->areaNodes()
                ->map(fn (AreaNode $areaNode): array => ['id' => $areaNode->id, 'name' => $areaNode->name])
                ->all(),
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
            ->title(__('staff.branch_access'));
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
            'brand' => $this->brand,
            'branch' => $this->branch,
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

    private function findBranchUser(int $branchUserId): BranchUser
    {
        return $this->staffQueries->findBranchUser($this->branch, $branchUserId);
    }

    private function findBranchUserByUser(int $userId): BranchUser
    {
        return $this->staffQueries->findBranchUserByUser($this->branch, $userId);
    }

    private function loadAreaAssignments(): void
    {
        $this->areaAssignments = $this->staffQueries->areaAssignments($this->branch);
    }

    private function authorizeStaffManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('manageStaff', $this->branch);
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
