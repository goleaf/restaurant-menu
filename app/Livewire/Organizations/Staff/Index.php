<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Staff;

use App\Actions\Invitations\CancelInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Actions\Staff\SetBranchStaffStatusAction;
use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Actions\Staff\SyncWaiterAreaAssignmentsAction;
use App\Actions\Staff\UpdateBranchStaffRoleAction;
use App\Actions\Staff\UpdateOrganizationStaffRoleAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Forms\Staff\InvitationForm;
use App\Livewire\Forms\Staff\StaffFilterForm;
use App\Livewire\Forms\Staff\StaffMemberForm;
use App\Livewire\Forms\Staff\WaiterAssignmentForm;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Staff\StaffQueryService;
use App\Support\LocalizedDateFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class Index extends Component
{
    use WithPagination;

    #[Locked]
    public Organization $organization;

    #[Locked]
    public ?Brand $brand = null;

    #[Locked]
    public ?Branch $branch = null;

    #[Url(history: true, except: 'employees')]
    public mixed $section = 'employees';

    #[Locked]
    public string $renderedSection = 'employees';

    public StaffFilterForm $filters;

    public InvitationForm $invitationForm;

    public StaffMemberForm $memberForm;

    public WaiterAssignmentForm $assignmentForm;

    #[Locked]
    public string $editor = '';

    #[Locked]
    public ?int $editingMembershipId = null;

    #[Locked]
    public int $editingVersion = 0;

    #[Locked]
    public string $memberOperation = 'role';

    #[Locked]
    public string $originalEditor = '';

    #[Locked]
    public string $previewFingerprint = '';

    /** @var array<string,mixed> */
    #[Locked]
    public array $preview = [];

    /** @var list<int> */
    #[Locked]
    public array $originalAreaIds = [];

    #[Locked]
    public string $assignmentFingerprint = '';

    #[Locked]
    public ?int $confirmInvitationId = null;

    #[Locked]
    public string $invitationVersion = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    private StaffQueryService $staffQueries;

    private ?User $actor = null;

    private bool $authorized = false;

    private ?string $createdInvitationLink = null;

    public function boot(StaffQueryService $staffQueries): void
    {
        $this->staffQueries = $staffQueries;
    }

    public function mount(Organization $organization, ?Brand $brand = null, ?Branch $branch = null): void
    {
        $this->organization = $organization;
        if ($this->isBranchWorkspace()) {
            abort_unless($brand instanceof Brand && $branch instanceof Branch, 404);
            $this->brand = $brand;
            $this->branch = $branch;
        }
        $this->authorizeStaffManagement();
        $this->invitationForm->roleId = $this->staffQueries->defaultWaiterRoleId();
    }

    public function selectSection(string $section): void
    {
        $this->authorizeStaffManagement();
        abort_unless(in_array($section, $this->sections(), true), 422);
        if (! $this->guardEditor()) {
            return;
        }
        $this->discardEditor();
        $this->section = $section;
        $this->filters->reset('status');
        $this->resetValidation();
    }

    public function updatedSection(): void
    {
        $this->authorizeStaffManagement();
        if (! $this->guardEditor()) {
            $this->section = $this->renderedSection;

            return;
        }
        $this->discardEditor();
    }

    public function updating(): void
    {
        $this->successMessage = '';
    }

    public function updatedFilters(): void
    {
        $this->resetPage(pageName: $this->pageName());
        $this->successMessage = '';
    }

    public function resetFilters(): void
    {
        $this->filters->reset();
        $this->resetPage(pageName: $this->pageName());
        $this->resetValidation();
    }

    public function refreshWorkspace(): void
    {
        $this->authorizeStaffManagement();
        $this->successMessage = '';
        $this->errorMessage = '';
    }

    public function openInvitation(): void
    {
        $this->authorizeStaffManagement();
        if (! $this->guardEditor()) {
            return;
        }
        $this->discardEditor();
        $this->editor = 'invite';
        $this->rememberEditor();
    }

    public function previewInvitation(): void
    {
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        $values = $this->invitationForm->validated(Rule::in($this->roles()->modelKeys()));
        $role = $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $values['roleId']);
        $this->previewFingerprint = $this->fingerprint($values);
        $this->preview = ['email' => $values['email'], 'role' => $role->code->localizedLabel(), 'scope' => $this->contextLabel(),
            'expires' => LocalizedDateFormatter::dateTime(now()->addDays($values['expiresInDays']))];
        $this->successMessage = '';
    }

    public function createInviteLink(CreateInvitationAction $createInvitation): void
    {
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        $values = $this->invitationForm->validated(Rule::in($this->roles()->modelKeys()));
        $this->assertPreview($values);
        $role = $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $values['roleId']);
        $this->attempt(function () use ($createInvitation, $role, $values): void {
            $created = $createInvitation->handle($this->organization, $role, $this->currentUser(), [
                'brand' => $this->brand, 'branch' => $this->branch, 'email' => $values['email'], 'phone' => $values['phone'],
                'expires_at' => now()->addDays($values['expiresInDays']),
            ]);
            $this->createdInvitationLink = $created->inviteLink();
            $this->discardEditor();
            $this->section = 'invitations';
            $this->resetPage(pageName: $this->pageName());
            $this->successMessage = __('staff.messages.invitation_created');
        });
    }

    public function confirmInvitation(mixed $invitationId, string $operation): void
    {
        $invitationId = $this->validatedIdentifier($invitationId);
        $this->authorizeStaffManagement();
        abort_unless(in_array($operation, ['reissue', 'cancel'], true), 422);
        if (! $this->guardEditor()) {
            return;
        }
        $invitation = $this->findInvitation($invitationId);
        Gate::forUser($this->currentUser())->authorize('assign', [$invitation->role, $this->organization]);
        $this->discardEditor();
        $this->editor = $operation;
        $this->confirmInvitationId = $invitation->id;
        $this->invitationVersion = $invitation->credentialVersion();
        $this->preview = ['email' => $invitation->email, 'role' => $invitation->role->code->localizedLabel(), 'scope' => $this->contextLabel()];
        $this->rememberEditor();
    }

    public function cancelInvitation(mixed $invitationId, CancelInvitationAction $cancelInvitation): void
    {
        $invitationId = $this->validatedIdentifier($invitationId);
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        abort_unless($this->editor === 'cancel' && $this->confirmInvitationId === $invitationId, 422);
        $this->attempt(function () use ($cancelInvitation, $invitationId): void {
            $cancelInvitation->handle($this->currentUser(), $this->organization, $this->findInvitation($invitationId));
            $this->discardEditor();
            $this->successMessage = __('staff.messages.invitation_cancelled');
        });
    }

    public function reissueInvitation(mixed $invitationId, ReissueInvitationAction $reissueInvitation): void
    {
        $invitationId = $this->validatedIdentifier($invitationId);
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        abort_unless($this->editor === 'reissue' && $this->confirmInvitationId === $invitationId, 422);
        $this->attempt(function () use ($reissueInvitation, $invitationId): void {
            $created = $reissueInvitation->handle($this->currentUser(), $this->organization, $this->findInvitation($invitationId), $this->invitationVersion);
            $this->createdInvitationLink = $created->inviteLink();
            $this->discardEditor();
            $this->successMessage = __('staff.messages.invitation_reissued');
        });
    }

    public function openMember(mixed $membershipId, string $operation = 'role'): void
    {
        $membershipId = $this->validatedIdentifier($membershipId);
        $this->authorizeStaffManagement();
        abort_unless(in_array($operation, ['role', 'status'], true), 422);
        if (! $this->guardEditor()) {
            return;
        }
        $member = $this->findMember($membershipId);
        $this->authorizeMember($member);
        $this->discardEditor();
        $this->editor = 'member';
        $this->editingMembershipId = $member->id;
        $this->editingVersion = $member->access_version;
        $this->memberOperation = $operation;
        $this->memberForm->fill(['roleId' => (string) $member->role_id, 'status' => $member->status->value, 'reason' => '']);
        $this->rememberEditor();
    }

    public function startEditingRole(mixed $membershipId): void
    {
        $membershipId = $this->validatedIdentifier($membershipId);
        $this->openMember($membershipId);
    }

    public function previewMemberChange(): void
    {
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        $member = $this->findMember($this->editingMembershipId ?? 0);
        $this->authorizeMember($member);
        $values = $this->memberForm->validatedChange($this->roles()->modelKeys());
        $this->previewFingerprint = $this->fingerprint($values);
        $role = $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $values['roleId']);
        $proposedStatus = $this->memberOperation === 'status' ? $values['status'] : $member->status->value;
        $this->preview = ['current' => $member->role->code->localizedLabel().' · '.$member->status->localizedLabel(),
            'proposed' => ($this->memberOperation === 'role' ? $role->code->localizedLabel() : $member->role->code->localizedLabel()).' · '.OrganizationUserStatus::from($proposedStatus)->localizedLabel()];
    }

    public function saveMember(UpdateOrganizationStaffRoleAction $organizationRole, UpdateBranchStaffRoleAction $branchRole, SetOrganizationStaffStatusAction $organizationStatus, SetBranchStaffStatusAction $branchStatus): void
    {
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        abort_unless($this->editor === 'member', 422);
        $member = $this->findMember($this->editingMembershipId ?? 0);
        $this->authorizeMember($member);
        $values = $this->memberForm->validatedChange($this->roles()->modelKeys());
        $this->assertPreview($values);
        $this->attempt(function () use ($member, $values, $organizationRole, $branchRole, $organizationStatus, $branchStatus): void {
            if ($this->memberOperation === 'role') {
                $role = $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $values['roleId']);
                if ($member instanceof BranchUser && $this->branch instanceof Branch) {
                    $branchRole->handle($this->currentUser(), $this->branch, $member, $role, $values['reason'], $this->editingVersion);
                } elseif ($member instanceof OrganizationUser) {
                    $organizationRole->handle($this->currentUser(), $this->organization, $member, $role, $values['reason'], $this->editingVersion);
                }
            } else {
                $action = $member instanceof BranchUser ? $branchStatus : $organizationStatus;
                if ($values['status'] === 'active') {
                    $action->activate($member, $this->currentUser(), $values['reason'], $this->editingVersion);
                } else {
                    $action->suspend($member, $this->currentUser(), $values['reason'], $this->editingVersion);
                }
            }
            $this->discardEditor();
            $this->successMessage = __('staff.workspace.updated');
        });
    }

    public function openExistingAssignment(): void
    {
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch, 404);
        if (! $this->guardEditor()) {
            return;
        }
        $this->discardEditor();
        $this->editor = 'assign';
        $this->rememberEditor();
    }

    public function assignExistingMember(AddBranchStaffMemberAction $assign): void
    {
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch, 404);
        $values = $this->memberForm->validatedAssignment($this->roles()->modelKeys());
        $member = $this->staffQueries->findOrganizationMembership($this->organization, $values['organizationMemberId']);
        $role = $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $values['roleId']);
        $this->attempt(function () use ($assign, $member, $role): void {
            $assign->handle($this->organization, $this->branch, $role, $this->currentUser(), ['email' => $member->user->email]);
            $this->discardEditor();
            $this->successMessage = __('staff.messages.staff_created');
        });
    }

    public function openAssignments(mixed $membershipId): void
    {
        $membershipId = $this->validatedIdentifier($membershipId);
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch, 404);
        if (! $this->guardEditor()) {
            return;
        }
        $member = $this->findMember($membershipId);
        $this->authorizeMember($member);
        abort_unless($member instanceof BranchUser && $member->role->code === SystemRole::Waiter && $member->status->value === 'active', 403);
        $this->discardEditor();
        $this->editor = 'areas';
        $this->editingMembershipId = $membershipId;
        $this->refreshAssignments(false);
        $this->rememberEditor();
    }

    public function refreshAssignments(bool $keepDraft = true): void
    {
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch && $this->editor === 'areas', 422);
        $member = $this->findMember($this->editingMembershipId ?? 0);
        abort_unless($member instanceof BranchUser, 404);
        $this->authorizeMember($member);
        $snapshot = $this->staffQueries->assignmentSnapshot($this->branch, $member);
        $this->previewFingerprint = '';
        $this->assignmentFingerprint = $snapshot['fingerprint'];
        $this->originalAreaIds = $snapshot['ids'];
        $this->originalEditor = $this->fingerprint(['areaIds' => array_map(strval(...), $snapshot['ids'])]);
        if (! $keepDraft) {
            $this->assignmentForm->areaIds = array_map(strval(...), $snapshot['ids']);
            $this->originalEditor = $this->editorFingerprint();
            $this->dispatch('staff-editor-clean');
        }
        $this->resetValidation();
        $this->errorMessage = '';
    }

    public function previewAreaAssignments(): void
    {
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        abort_unless($this->editor === 'areas', 422);
        $ids = $this->assignmentForm->validatedIds();
        $this->previewFingerprint = $this->fingerprint(['areaIds' => $ids]);
    }

    public function updatedAssignmentFormSearch(): void
    {
        $this->resetPage(pageName: 'assignmentAreasPage');
    }

    public function saveAreaAssignments(SyncWaiterAreaAssignmentsAction $syncAssignments): void
    {
        $this->successMessage = '';
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch && $this->editor === 'areas', 422);
        $member = $this->findMember($this->editingMembershipId ?? 0);
        abort_unless($member instanceof BranchUser, 404);
        $ids = $this->assignmentForm->validatedIds();
        $this->assertPreview(['areaIds' => $ids]);
        $this->attempt(function () use ($syncAssignments, $member, $ids): void {
            $syncAssignments->handle($this->branch, $member, $this->currentUser(), $ids, $this->assignmentFingerprint);
            $this->refreshAssignments(false);
            $this->successMessage = __('staff.messages.waiter_zones_updated');
        });
    }

    public function requestCloseEditor(): void
    {
        if ($this->guardEditor()) {
            $this->discardEditor();
        }
    }

    public function discardEditor(): void
    {
        $this->editor = '';
        $this->editingMembershipId = null;
        $this->confirmInvitationId = null;
        $this->preview = [];
        $this->previewFingerprint = $this->originalEditor = $this->assignmentFingerprint = $this->invitationVersion = '';
        $this->originalAreaIds = [];
        $this->memberForm->reset();
        $this->assignmentForm->reset();
        $this->invitationForm->clearRecipient();
        $this->resetValidation();
        $this->successMessage = $this->errorMessage = '';
        $this->dispatch('staff-editor-closed');
    }

    public function render(): View
    {
        $this->authorizeStaffManagement();
        $section = is_string($this->section) && in_array($this->section, $this->sections(), true) ? $this->section : 'employees';
        if ($section !== $this->section) {
            $this->addError('section', __('staff.workspace.no_results'));
        }
        try {
            $filters = $this->filters->validatedFilters($section);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $filters = ['search' => '', 'role' => '', 'status' => '', 'sort' => 'newest'];
        }
        $this->renderedSection = $section;
        $roles = $this->roles();
        $members = $invitations = null;
        if ($section === 'invitations') {
            $invitations = $this->branch instanceof Branch
                ? $this->staffQueries->paginateBranchInvitations($this->organization, $this->branch, $filters['search'], 15, $filters)
                : $this->staffQueries->paginateOrganizationInvitations($this->organization, $filters['search'], 15, $filters);
        } else {
            if ($section === 'assignments') {
                $filters['role'] = SystemRole::Waiter->value;
                $filters['status'] = 'active';
            }
            $members = $this->branch instanceof Branch
                ? $this->staffQueries->paginateBranchMembers($this->branch, $filters['search'], 15, $filters)
                : $this->staffQueries->paginateOrganizationMembers($this->organization, $filters['search'], 15, $filters);
        }
        $selectedMember = null;
        $permissionLinks = $this->staffQueries->permissionLinks($this->organization, $this->currentUser(), $members?->getCollection()->pluck('user_id')->all() ?? [], $roles->modelKeys());
        if ($this->editingMembershipId !== null) {
            $member = $this->findMember($this->editingMembershipId);
            $this->authorizeMember($member);
            $selectedMember = $this->staffQueries->memberRow($member, $this->currentUser(), $roles->modelKeys());
        }
        $ids = is_array($this->assignmentForm->areaIds) ? array_values(array_filter($this->assignmentForm->areaIds, fn (mixed $id): bool => (is_string($id) || is_int($id)) && ctype_digit((string) $id))) : [];
        $ids = array_map(intval(...), $ids);
        $areas = $this->editor === 'areas' && $this->branch instanceof Branch
            ? $this->staffQueries->areaEditor($this->branch, $ids, is_string($this->assignmentForm->search) ? mb_substr($this->assignmentForm->search, 0, 120) : '') : null;
        $candidates = $this->editor === 'assign' && $this->branch instanceof Branch
            ? $this->staffQueries->assignableOrganizationMembers($this->organization, $this->branch, is_string($this->assignmentForm->search) ? mb_substr($this->assignmentForm->search, 0, 120) : '') : collect();

        return view($this->viewName(), [
            'activeSection' => $section, 'isBranch' => $this->isBranchWorkspace(), 'contextLabel' => $this->contextLabel(),
            'hasActiveFilters' => $filters['search'] !== '' || $filters['role'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'newest',
            'coverageOverview' => $section === 'assignments' && $this->branch instanceof Branch ? $this->staffQueries->coverageOverview($this->branch) : null,
            'organizationName' => $this->organization->name, 'createdInvitationLink' => $this->createdInvitationLink,
            'roleOptions' => $roles->map(fn (Role $role): array => ['id' => $role->id, 'label' => $role->code->localizedLabel()])->all(),
            'filterRoleOptions' => array_map(fn (SystemRole $role): array => ['value' => $role->value, 'label' => $role->localizedLabel()], SystemRole::cases()),
            'statusOptions' => $section === 'invitations' ? ['pending', 'accepted', 'cancelled', 'expired', 'rejected'] : ['active', 'invited', 'suspended', 'removed'],
            'statusLabels' => array_combine($section === 'invitations' ? ['pending', 'accepted', 'cancelled', 'expired', 'rejected'] : ['active', 'invited', 'suspended', 'removed'], array_map(fn (string $status): string => ($section === 'invitations' ? InvitationStatus::from($status)->localizedLabel() : OrganizationUserStatus::from($status)->localizedLabel()), $section === 'invitations' ? ['pending', 'accepted', 'cancelled', 'expired', 'rejected'] : ['active', 'invited', 'suspended', 'removed'])),
            'memberRows' => $members?->getCollection()->map(fn ($member): array => [...$this->staffQueries->memberRow($member, $this->currentUser(), $roles->modelKeys()), 'permissions_url' => $permissionLinks[$member->user_id] ?? null])->all() ?? [],
            'invitationRows' => $invitations?->getCollection()->map(fn (Invitation $invitation): array => $this->staffQueries->invitationRow($invitation, $roles->modelKeys()))->all() ?? [],
            'membersPaginator' => $members, 'invitationsPaginator' => $invitations, 'selectedMember' => $selectedMember,
            'areaEditor' => $areas, 'areasAdded' => count(array_diff($ids, $this->originalAreaIds)), 'areasRemoved' => count(array_diff($this->originalAreaIds, $ids)),
            'coverageLabel' => $ids === [] ? __('staff.workspace.coverage_all') : __('staff.workspace.coverage_count', ['count' => count($ids)]),
            'candidateRows' => $candidates->map(fn (OrganizationUser $member): array => ['id' => $member->id, 'label' => $member->user->name.' · '.$member->user->email])->all(),
        ])->title(__($this->isBranchWorkspace() ? 'staff.branch_access' : 'staff.organization_access'));
    }

    protected function isBranchWorkspace(): bool
    {
        return false;
    }

    protected function viewName(): string
    {
        return 'livewire.organizations.staff.index';
    }

    private function validatedIdentifier(mixed $id): int
    {
        $validated = Validator::make(['member' => $id], ['member' => ['required', 'numeric', 'integer', 'min:1']])->validate();

        return (int) $validated['member'];
    }

    private function currentUser(): User
    {
        if ($this->actor === null) {
            $user = Auth::user();
            abort_unless($user instanceof User, 401);
            $this->actor = $user->fresh() ?? $user;
        }

        return $this->actor;
    }

    private function authorizeStaffManagement(): void
    {
        if ($this->authorized) {
            return;
        }
        $context = $this->staffQueries->context($this->organization->id, $this->brand?->id, $this->branch?->id);
        $this->organization = $context['organization'];
        $this->brand = $context['brand'];
        $this->branch = $context['branch'];
        Gate::forUser($this->currentUser())->authorize('manageStaff', $this->branch ?? $this->organization);
        $this->authorized = true;
    }

    /** @return Collection<int,Role> */
    private function roles(): Collection
    {
        return $this->staffQueries->assignableRoles($this->currentUser(), $this->organization);
    }

    private function findMember(int $id): OrganizationUser|BranchUser
    {
        return $this->branch instanceof Branch ? $this->staffQueries->findBranchUser($this->branch, $id) : $this->staffQueries->findOrganizationMembership($this->organization, $id);
    }

    private function authorizeMember(OrganizationUser|BranchUser $member): void
    {
        abort_if($member->user_id === $this->currentUser()->id || $member->user->isSuperadmin(), 403);
        Gate::forUser($this->currentUser())->authorize('assign', [$member->role, $this->organization]);
    }

    private function findInvitation(int $id): Invitation
    {
        return $this->branch instanceof Branch ? $this->staffQueries->findBranchInvitation($this->organization, $this->branch, $id) : $this->staffQueries->findOrganizationInvitation($this->organization, $id);
    }

    /** @return list<string> */
    private function sections(): array
    {
        return $this->isBranchWorkspace() ? ['employees', 'invitations', 'assignments'] : ['employees', 'invitations'];
    }

    private function pageName(): string
    {
        return ($this->isBranchWorkspace() ? 'branch' : 'organization').($this->section === 'invitations' ? 'InvitationsPage' : 'StaffPage');
    }

    private function contextLabel(): string
    {
        return $this->organization->name.($this->branch instanceof Branch ? ' → '.$this->brand->name.' → '.$this->branch->name.' · '.$this->branch->timezone : '');
    }

    /** @param array<string,mixed> $values */
    private function fingerprint(array $values): string
    {
        return hash('sha256', json_encode([$this->organization->id, $this->branch?->id, $values], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $values */
    private function assertPreview(array $values): void
    {
        if ($this->previewFingerprint === '' || ! hash_equals($this->previewFingerprint, $this->fingerprint($values))) {
            throw ValidationException::withMessages(['preview' => __('staff.workspace.preview_changed')]);
        }
    }

    private function editorFingerprint(): string
    {
        return $this->fingerprint(match ($this->editor) {
            'invite' => $this->invitationForm->all(), 'member', 'assign' => $this->memberForm->all(),
            'areas' => ['areaIds' => $this->assignmentForm->areaIds], default => [],
        });
    }

    private function guardEditor(): bool
    {
        if ($this->editor !== '' && $this->originalEditor !== '' && ! hash_equals($this->originalEditor, $this->editorFingerprint())) {
            $this->errorMessage = __('staff.workspace.unsaved');

            return false;
        }

        return true;
    }

    private function rememberEditor(): void
    {
        $this->originalEditor = $this->editorFingerprint();
        $this->dispatch('staff-editor-opened');
    }

    private function attempt(callable $operation): void
    {
        $this->successMessage = $this->errorMessage = '';
        try {
            $operation();
        } catch (ValidationException $exception) {
            $this->dispatch('staff-validation-failed');
            throw $exception;
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->errorMessage = __('staff.workspace.save_failed');
        }
    }
}
