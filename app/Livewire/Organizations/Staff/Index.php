<?php

namespace App\Livewire\Organizations\Staff;

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Staff\AddOrganizationStaffMemberAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
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

#[Title('Organization staff')]
class Index extends Component
{
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

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;

        if (! $this->currentUser()->canAccessOrganization($organization)) {
            abort(403);
        }

        $this->canManageStaff = $this->currentUser()->hasPermission(SystemPermission::ManageStaff, $organization);

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

        Flux::toast(variant: 'success', text: __('Staff member added.'));
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

    public function activateMember(int $membershipId): void
    {
        $this->authorizeStaffManagement();

        $membership = $this->findMembership($membershipId);
        $membership->update([
            'status' => OrganizationUserStatus::Active,
            'joined_at' => $membership->joined_at ?? now(),
        ]);

        unset($this->members);
    }

    public function deactivateMember(int $membershipId): void
    {
        $this->authorizeStaffManagement();

        $membership = $this->findMembership($membershipId);

        if ($membership->user_id === $this->currentUser()->id) {
            Flux::toast(variant: 'warning', text: __('You cannot deactivate yourself.'));

            return;
        }

        $membership->update(['status' => OrganizationUserStatus::Suspended]);

        unset($this->members);
    }

    /**
     * @return EloquentCollection<int, OrganizationUser>
     */
    #[Computed]
    public function members(): EloquentCollection
    {
        return OrganizationUser::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'joined_at', 'invited_by_user_id', 'created_at', 'updated_at'])
            ->with([
                'user' => fn ($query) => $query->select(['id', 'name', 'email']),
                'role' => fn ($query) => $query->select(['id', 'code', 'name', 'sort_order']),
            ])
            ->where('organization_id', $this->organization->id)
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
            ->whereNull('brand_id')
            ->whereNull('branch_id')
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
        return view('livewire.organizations.staff.index');
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

    private function findMembership(int $membershipId): OrganizationUser
    {
        return OrganizationUser::query()
            ->where('organization_id', $this->organization->id)
            ->whereKey($membershipId)
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
