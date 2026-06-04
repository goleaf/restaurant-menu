<?php

namespace App\Livewire\Organizations\Brands\Branches\Staff;

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Branch staff')]
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

        Flux::toast(variant: 'success', text: __('Branch staff member added.'));
    }

    public function createInviteLink(CreateInvitationAction $createInvitation): void
    {
        $this->createInvitation($createInvitation);

        Flux::toast(variant: 'success', text: __('Invite link created.'));
    }

    public function createInviteCode(CreateInvitationAction $createInvitation): void
    {
        $this->createInvitation($createInvitation);

        Flux::toast(variant: 'success', text: __('Invite code created.'));
    }

    public function activateMember(int $branchUserId): void
    {
        $this->authorizeStaffManagement();

        $branchUser = $this->findBranchUser($branchUserId);
        $branchUser->update([
            'status' => OrganizationUserStatus::Active,
            'assigned_at' => $branchUser->assigned_at ?? now(),
        ]);

        unset($this->members);
    }

    public function deactivateMember(int $branchUserId): void
    {
        $this->authorizeStaffManagement();

        $branchUser = $this->findBranchUser($branchUserId);

        if ($branchUser->user_id === $this->currentUser()->id) {
            Flux::toast(variant: 'warning', text: __('You cannot deactivate yourself.'));

            return;
        }

        $branchUser->update(['status' => OrganizationUserStatus::Suspended]);

        unset($this->members);
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

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.staff.index');
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function manualStaffRules(): array
    {
        return [
            'manualName' => ['required', 'string', 'max:120'],
            'manualEmail' => ['required', 'email', 'max:255'],
            'manualRoleId' => ['required', 'integer', $this->assignableRoleRule()],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function invitationRules(): array
    {
        return [
            'inviteEmail' => ['nullable', 'email', 'max:255'],
            'invitePhone' => ['nullable', 'string', 'max:40'],
            'inviteRoleId' => ['required', 'integer', $this->assignableRoleRule()],
        ];
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
