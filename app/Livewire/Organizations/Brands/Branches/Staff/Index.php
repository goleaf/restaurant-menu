<?php

namespace App\Livewire\Organizations\Brands\Branches\Staff;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

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

    /**
     * @var array<int, list<string>>
     */
    public array $areaAssignments = [];

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->branch = $branch;

        if ($brand->organization_id !== $organization->id || $branch->organization_id !== $organization->id || $branch->brand_id !== $brand->id) {
            abort(403);
        }

        if (! $this->currentUser()->canAccessBranch($branch, $organization)) {
            abort(403);
        }

        $this->canManageStaff = $this->currentUser()->hasPermission(SystemPermission::ManageStaff, $organization);

        if (! $this->canManageStaff) {
            abort(403);
        }

        $this->manualRoleId = $this->defaultRoleId();
        $this->inviteRoleId = $this->manualRoleId;
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

    public function createInviteCode(CreateInvitationAction $createInvitation): void
    {
        $this->createInvitation($createInvitation);

        Flux::toast(variant: 'success', text: __('staff.messages.invitation_created'));
    }

    public function activateMember(int $branchUserId): void
    {
        $this->authorizeStaffManagement();

        $branchUser = $this->findBranchUser($branchUserId);
        $branchUser->forceFill([
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => $branchUser->assigned_at ?? now(),
        ])->save();

        unset($this->members);

        Flux::toast(variant: 'success', text: __('staff.messages.staff_reactivated'));
    }

    public function deactivateMember(int $branchUserId, RecordAuditLogAction $recordAuditLog): void
    {
        $this->authorizeStaffManagement();

        $validated = $this->validate(RestaurantValidationRules::auditReason('staffDeactivationReason'), [
            'staffDeactivationReason.required' => __('staff.errors.deactivation_reason_required'),
            'staffDeactivationReason.min' => __('staff.errors.deactivation_reason_min'),
        ]);

        $branchUser = $this->findBranchUser($branchUserId);

        if ($branchUser->user_id === $this->currentUser()->id) {
            Flux::toast(variant: 'warning', text: __('staff.errors.self_deactivation_blocked'));

            return;
        }

        $previousStatus = $branchUser->status;
        $branchUser->forceFill(['status' => OrganizationUserStatus::Suspended])->save();

        $recordAuditLog->handle(
            action: AuditLogAction::StaffDeactivated,
            entityType: 'branch_user',
            entityId: $branchUser->id,
            actorUser: $this->currentUser(),
            organizationId: $this->organization->id,
            branchId: $this->branch->id,
            oldValues: [
                'staff_user_id' => $branchUser->user_id,
                'status' => $previousStatus,
            ],
            newValues: [
                'staff_user_id' => $branchUser->user_id,
                'status' => OrganizationUserStatus::Suspended,
                'reason' => (string) $validated['staffDeactivationReason'],
            ],
        );

        $this->staffDeactivationReason = '';
        unset($this->members);

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('staff.messages.staff_deactivated'));
    }

    public function saveAreaAssignments(int $userId): void
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
        $validAreaIds = $this->areaNodes->pluck('id')->map(fn (int $areaNodeId): int => $areaNodeId)->values();

        if ($selectedAreaIds->diff($validAreaIds)->isNotEmpty()) {
            $this->addError('areaAssignments.'.$userId, __('staff.errors.zone_unavailable'));

            return;
        }

        $existingAssignments = AreaNodeWaiter::query()
            ->where('branch_id', $this->branch->id)
            ->where('user_id', $userId);

        if ($selectedAreaIds->isEmpty()) {
            $existingAssignments->delete();
        } else {
            $existingAssignments
                ->whereNotIn('area_node_id', $selectedAreaIds)
                ->delete();
        }

        foreach ($selectedAreaIds as $areaNodeId) {
            $assignment = AreaNodeWaiter::query()
                ->where('area_node_id', $areaNodeId)
                ->where('user_id', $userId)
                ->first() ?? new AreaNodeWaiter;

            $assignment->forceFill([
                'organization_id' => $this->organization->id,
                'branch_id' => $this->branch->id,
                'area_node_id' => $areaNodeId,
                'user_id' => $userId,
                'assigned_by_user_id' => $this->currentUser()->id,
                'assigned_at' => now(),
            ])->save();
        }

        $this->areaAssignments[$userId] = $selectedAreaIds
            ->map(fn (int $areaNodeId): string => (string) $areaNodeId)
            ->values()
            ->all();

        Flux::toast(variant: 'success', text: __('staff.messages.waiter_zones_updated'));
    }

    /**
     * @return EloquentCollection<int, BranchUser>
     */
    #[Computed]
    public function members(): EloquentCollection
    {
        return BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at'])
            ->with([
                'user' => fn ($query) => $query->select(['id', 'name', 'email']),
                'role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order']),
            ])
            ->where('branch_id', $this->branch->id)
            ->orderBy('status')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Invitation>
     */
    #[Computed]
    public function invitations(): EloquentCollection
    {
        return Invitation::query()
            ->select(['id', 'organization_id', 'brand_id', 'branch_id', 'role_id', 'email', 'phone', 'invite_token', 'invite_code', 'expires_at', 'status', 'invited_by_user_id', 'created_at', 'updated_at'])
            ->with(['role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order'])])
            ->where('organization_id', $this->organization->id)
            ->where('branch_id', $this->branch->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    #[Computed]
    public function roles(): EloquentCollection
    {
        return Role::query()
            ->select(['id', 'code', 'name', 'sort_order'])
            ->where('code', '!=', SystemRole::Superadmin->value)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return EloquentCollection<int, AreaNode>
     */
    #[Computed]
    public function areaNodes(): EloquentCollection
    {
        return AreaNode::query()
            ->select(['id', 'branch_id', 'name', 'sort_order', 'is_active'])
            ->where('branch_id', $this->branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function memberIsWaiter(BranchUser $member): bool
    {
        return $member->role?->code === SystemRole::Waiter;
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.staff.index')
            ->title(__('staff.branch_access'));
    }

    public function roleLabel(?Role $role): string
    {
        if (! $role instanceof Role) {
            return '';
        }

        $roleCode = $role->code instanceof SystemRole
            ? $role->code->value
            : (string) $role->code;
        $systemRole = SystemRole::tryFrom($roleCode);

        return $systemRole?->localizedLabel() ?? (string) $role->name;
    }

    public function memberStatusLabel(OrganizationUserStatus $status): string
    {
        return __('staff.statuses.'.$status->value);
    }

    public function invitationStatusLabel(InvitationStatus $status): string
    {
        return __('staff.invitation_statuses.'.$status->value);
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

        $invitation = $createInvitation->handle($this->organization, $role, $this->currentUser(), [
            'brand' => $this->brand,
            'branch' => $this->branch,
            'email' => $validated['inviteEmail'] === '' ? null : $validated['inviteEmail'],
            'phone' => $validated['invitePhone'] === '' ? null : $validated['invitePhone'],
        ]);

        $this->lastInviteLink = $invitation->inviteLink();
        $this->lastInviteCode = $invitation->invite_code;
        unset($this->invitations);

        return $invitation;
    }

    private function defaultRoleId(): ?int
    {
        return Role::query()
            ->where('code', SystemRole::Waiter->value)
            ->value('id');
    }

    private function findAssignableRole(int $roleId): Role
    {
        return Role::query()
            ->where('code', '!=', SystemRole::Superadmin->value)
            ->whereKey($roleId)
            ->firstOrFail();
    }

    private function findBranchUser(int $branchUserId): BranchUser
    {
        return BranchUser::query()
            ->where('branch_id', $this->branch->id)
            ->whereKey($branchUserId)
            ->firstOrFail();
    }

    private function findBranchUserByUser(int $userId): BranchUser
    {
        return BranchUser::query()
            ->select(['id', 'organization_id', 'branch_id', 'user_id', 'role_id', 'status', 'assigned_at', 'assigned_by_user_id', 'created_at', 'updated_at'])
            ->with(['role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order'])])
            ->where('branch_id', $this->branch->id)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    private function loadAreaAssignments(): void
    {
        $this->areaAssignments = AreaNodeWaiter::query()
            ->select(['id', 'branch_id', 'area_node_id', 'user_id'])
            ->where('branch_id', $this->branch->id)
            ->orderBy('user_id')
            ->orderBy('area_node_id')
            ->get()
            ->groupBy('user_id')
            ->map(fn (EloquentCollection $assignments): array => $assignments
                ->pluck('area_node_id')
                ->map(fn (int $areaNodeId): string => (string) $areaNodeId)
                ->values()
                ->all())
            ->all();
    }

    private function authorizeStaffManagement(): void
    {
        if (! $this->currentUser()->hasPermission(SystemPermission::ManageStaff, $this->organization)) {
            abort(403);
        }
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
