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
use App\Services\Staff\BranchAssignmentQueryService;
use App\Services\Staff\PermissionQueryService;
use App\Services\Staff\StaffQueryService;
use App\Services\Staff\TeamCardQueryService;
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
    public ?int $actorId = null;

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

    protected StaffQueryService $staffQueries;

    protected TeamCardQueryService $cards;

    protected PermissionQueryService $permissions;

    protected BranchAssignmentQueryService $branchAssignments;

    private ?User $actor = null;

    private bool $authorized = false;

    private ?string $createdInvitationLink = null;

    public function boot(StaffQueryService $staffQueries, TeamCardQueryService $cards, PermissionQueryService $permissions, BranchAssignmentQueryService $branchAssignments): void
    {
        $this->staffQueries = $staffQueries;
        $this->cards = $cards;
        $this->permissions = $permissions;
        $this->branchAssignments = $branchAssignments;
    }

    public function mount(Organization $organization, ?Brand $brand = null, ?Branch $branch = null): void
    {
        $this->actorId = (int) Auth::id();
        $this->organization = $organization;
        if ($this->isBranchWorkspace()) {
            abort_unless($brand instanceof Brand && $branch instanceof Branch, 404);
            $this->brand = $brand;
            $this->branch = $branch;
        }
        $this->authorizeWorkspace();
        $this->invitationForm->roleId = $this->staffQueries->defaultWaiterRoleId();
    }

    public function selectSection(string $section): void
    {
        $this->authorizeWorkspace();
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
        $this->authorizeWorkspace();
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
        $this->authorizeWorkspace();
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
        $this->preview = ['email' => $values['email'], 'role' => $role->code->localizedLabel(), 'scope' => $this->contextLabel(), 'role_defaults' => $this->staffQueries->roleAccessPreview($role),
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
            $this->filters->reset('status');
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
            $cancelInvitation->handle($this->currentUser(), $this->organization, $this->findInvitation($invitationId), $this->invitationVersion);
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
        $this->previewFingerprint = '';
        $role = $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $values['roleId']);
        $proposedStatus = $this->memberOperation === 'status' ? $values['status'] : $member->status->value;
        $this->preview = ['current' => $member->role->code->localizedLabel().' · '.$member->status->localizedLabel(),
            'proposed' => ($this->memberOperation === 'role' ? $role->code->localizedLabel() : $member->role->code->localizedLabel()).' · '.OrganizationUserStatus::from($proposedStatus)->localizedLabel()];
        if ($this->memberOperation === 'role') {
            $this->attempt(function () use ($member, $role): void {
                $this->preview['impact'] = $this->permissions->rolePreview($this->currentUser(), $this->organization, $member->user, $role, $member instanceof BranchUser ? $this->branch : null, $member instanceof BranchUser);
            });
            if (! isset($this->preview['impact'])) {
                return;
            }
        }
        $this->previewFingerprint = $this->fingerprint($values);
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
                    $branchRole->handle($this->currentUser(), $this->branch, $member, $role, $values['reason'], $this->editingVersion, $this->preview['impact']['fingerprint'] ?? null);
                } elseif ($member instanceof OrganizationUser) {
                    $organizationRole->handle($this->currentUser(), $this->organization, $member, $role, $values['reason'], $this->editingVersion, $this->preview['impact']['fingerprint'] ?? null);
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

    public function selectExistingMember(mixed $membershipId): void
    {
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch, 404);
        $member = $this->staffQueries->findOrganizationMembership($this->organization, $this->validatedIdentifier($membershipId));
        $this->authorizeMember($member);
        abort_unless($member->status === OrganizationUserStatus::Active, 403);
        $this->redirect($this->cards->url($member, $this->branch, 'access'), navigate: true);
    }

    public function assignExistingMember(AddBranchStaffMemberAction $assign): void
    {
        $values = $this->memberForm->validatedAssignment($this->roles()->modelKeys());
        $this->selectExistingMember($values['organizationMemberId']);
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
        $this->authorizeWorkspace();
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
                ? ($section === 'employees' ? $this->staffQueries->paginateBranchTeam($this->branch, $filters['search'], 15, $filters) : $this->staffQueries->paginateBranchMembers($this->branch, $filters['search'], 15, $filters))
                : $this->staffQueries->paginateOrganizationMembers($this->organization, $filters['search'], 15, $filters);
        }
        $selectedMember = null;
        if ($this->editingMembershipId !== null) {
            $member = $this->findMember($this->editingMembershipId);
            $this->authorizeMember($member);
            $selectedMember = $this->staffQueries->memberRow($member, $this->currentUser(), $roles->modelKeys());
        }
        $ids = is_array($this->assignmentForm->areaIds) ? array_values(array_filter(array_slice($this->assignmentForm->areaIds, 0, 500), fn (mixed $id): bool => (is_string($id) || is_int($id)) && ctype_digit((string) $id))) : [];
        $ids = array_map(intval(...), $ids);
        $areas = $this->editor === 'areas' && $this->branch instanceof Branch
            ? $this->staffQueries->areaEditor($this->branch, $ids, is_string($this->assignmentForm->search) ? mb_substr($this->assignmentForm->search, 0, 120) : '', $this->originalAreaIds) : null;
        $candidates = $this->editor === 'assign' && $this->branch instanceof Branch
            ? $this->staffQueries->assignableOrganizationMembers($this->organization, $this->branch, is_string($this->assignmentForm->search) ? mb_substr($this->assignmentForm->search, 0, 120) : '', $this->currentUser()) : collect();

        $membershipIds = $this->staffQueries->membershipIds($this->organization, $members?->getCollection()->pluck('user_id')->all() ?? []);
        $memberRows = $members?->getCollection()->map(function ($member) use ($roles, $membershipIds, $filters, $section): array {
            $row = $this->staffQueries->memberRow($member, $this->currentUser(), $roles->modelKeys());
            $assignment = $member instanceof OrganizationUser && $this->branch !== null && $member->user->relationLoaded('branchAssignments') ? $member->user->branchAssignments->first() : null;
            if ($member instanceof OrganizationUser && $this->branch !== null) {
                $row['coverage'] = $assignment === null ? __('team.card.inherited') : __('team.card.explicit');
                $row['role_label'] = __('team.card.organization_role').': '.$row['role_label'].($assignment === null ? '' : ' · '.__('team.card.branch_role').': '.$assignment->role->code->localizedLabel());
                $row['localized_status'] = $member->status->localizedLabel().($assignment === null ? '' : ' · '.$assignment->status->localizedLabel());
            }
            $memberId = $membershipIds[$member->user_id] ?? null;
            $row['card_url'] = $memberId === null ? null : route($this->branch === null ? 'organizations.staff.show' : 'organizations.brands.branches.staff.show', [
                'organization' => $this->organization->id, ...($this->branch === null ? [] : ['brand' => $this->brand->id, 'branch' => $this->branch->id]),
                'member' => $memberId, ...$filters, 'from' => $section, 'listPage' => $this->getPage($this->pageName()), 'section' => $section === 'assignments' ? 'areas' : 'overview',
            ]);

            return $row;
        })->all() ?? [];

        $acceptedMemberships = $this->staffQueries->membershipIds($this->organization, $invitations?->getCollection()->pluck('accepted_by_user_id')->filter()->all() ?? []);

        $coverage = $section === 'assignments' && $this->branch instanceof Branch ? $this->staffQueries->coverageOverview($this->branch) : null;
        if ($coverage !== null) {
            $link = fn (array $person): array => [...$person, 'url' => route('organizations.brands.branches.staff.show', [
                'organization' => $this->organization->id, 'brand' => $this->brand->id, 'branch' => $this->branch->id,
                'member' => $person['id'], 'section' => 'areas', 'from' => 'assignments', ...$filters,
                'listPage' => $this->getPage($this->pageName()), 'coverageListPage' => $this->getPage('coveragePage'),
            ])];
            $coverage['unrestricted_members'] = array_map($link, $coverage['unrestricted_members']);
            foreach ($coverage['rows'] as &$areaRow) {
                $areaRow['waiter_members'] = array_map($link, $areaRow['waiter_members']);
            }
            unset($areaRow);
        }

        return view($this->viewName(), [
            'canManageStaff' => Gate::forUser($this->currentUser())->allows('manageStaff', $this->branch ?? $this->organization),
            'activeSection' => $section, 'isBranch' => $this->isBranchWorkspace(), 'contextLabel' => $this->contextLabel(),
            'hasActiveFilters' => $filters['search'] !== '' || $filters['role'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'newest',
            'coverageOverview' => $coverage,
            'invitationSummary' => $section === 'invitations' ? $this->staffQueries->invitationSummary($this->organization, $this->branch, $filters) : [],
            'organizationName' => $this->organization->name, 'createdInvitationLink' => $this->createdInvitationLink,
            'roleOptions' => $roles->map(fn (Role $role): array => ['id' => $role->id, 'label' => $role->code->localizedLabel()])->all(),
            'filterRoleOptions' => array_map(fn (SystemRole $role): array => ['value' => $role->value, 'label' => $role->localizedLabel()], SystemRole::cases()),
            'statusOptions' => $section === 'invitations' ? ['pending', 'accepted', 'cancelled', 'expired', 'rejected'] : ['active', 'invited', 'suspended', 'removed'],
            'statusLabels' => array_combine($section === 'invitations' ? ['pending', 'accepted', 'cancelled', 'expired', 'rejected'] : ['active', 'invited', 'suspended', 'removed'], array_map(fn (string $status): string => ($section === 'invitations' ? InvitationStatus::from($status)->localizedLabel() : OrganizationUserStatus::from($status)->localizedLabel()), $section === 'invitations' ? ['pending', 'accepted', 'cancelled', 'expired', 'rejected'] : ['active', 'invited', 'suspended', 'removed'])),
            'memberRows' => $memberRows,
            'invitationRows' => $invitations?->getCollection()->map(function (Invitation $invitation) use ($roles, $acceptedMemberships, $filters): array {
                $memberId = $acceptedMemberships[$invitation->accepted_by_user_id] ?? null;
                $url = $memberId === null ? null : route($this->branch === null ? 'organizations.staff.show' : 'organizations.brands.branches.staff.show', [
                    'organization' => $this->organization->id, ...($this->branch === null ? [] : ['brand' => $this->brand->id, 'branch' => $this->branch->id]),
                    'member' => $memberId, ...$filters, 'from' => 'invitations', 'listPage' => $this->getPage($this->pageName()),
                ]);

                return [...$this->staffQueries->invitationRow($invitation, $roles->modelKeys()), 'scope' => $this->contextLabel(), 'card_url' => $url];
            })->all() ?? [],
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

    protected function currentUser(): User
    {
        abort_unless($this->actorId !== null && $this->actorId === Auth::id(), 403);
        if ($this->actor === null) {
            $user = Auth::user();
            abort_unless($user instanceof User, 401);
            $this->actor = $user->fresh(['roles:id,code']) ?? $user;
        }

        return $this->actor;
    }

    protected function authorizeWorkspace(): void
    {
        $context = $this->staffQueries->context($this->organization->id, $this->brand?->id, $this->branch?->id);
        $this->organization = $context['organization'];
        $this->brand = $context['brand'];
        $this->branch = $context['branch'];
        Gate::forUser($this->currentUser())->authorize('viewTeam', $this->branch ?? $this->organization);
    }

    protected function authorizeStaffManagement(): void
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
    protected function roles(): Collection
    {
        return $this->staffQueries->assignableRoles($this->currentUser(), $this->organization);
    }

    protected function findMember(int $id): OrganizationUser|BranchUser
    {
        return $this->branch instanceof Branch ? $this->staffQueries->findBranchUser($this->branch, $id) : $this->staffQueries->findOrganizationMembership($this->organization, $id);
    }

    protected function authorizeMember(OrganizationUser|BranchUser $member): void
    {
        abort_if($member->user_id === $this->currentUser()->id || $member->user->isSuperadmin(), 403);
        Gate::forUser($this->currentUser())->authorize('assign', [$member->role, $this->organization]);
    }

    private function findInvitation(int $id): Invitation
    {
        return $this->branch instanceof Branch ? $this->staffQueries->findBranchInvitation($this->organization, $this->branch, $id) : $this->staffQueries->findOrganizationInvitation($this->organization, $id);
    }

    /** @return list<string> */
    protected function sections(): array
    {
        return $this->isBranchWorkspace() ? ['employees', 'invitations', 'assignments'] : ['employees', 'invitations'];
    }

    private function pageName(): string
    {
        return ($this->isBranchWorkspace() ? 'branch' : 'organization').($this->section === 'invitations' ? 'InvitationsPage' : 'StaffPage');
    }

    protected function contextLabel(): string
    {
        return $this->organization->name.($this->branch instanceof Branch ? ' → '.$this->brand->name.' → '.$this->branch->name.' · '.$this->branch->timezone : '');
    }

    /** @param array<string,mixed> $values */
    protected function fingerprint(array $values): string
    {
        return hash('sha256', json_encode([$this->organization->id, $this->branch?->id, $values], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $values */
    protected function assertPreview(array $values): void
    {
        if ($this->previewFingerprint === '' || ! hash_equals($this->previewFingerprint, $this->fingerprint($values))) {
            throw ValidationException::withMessages(['preview' => __('staff.workspace.preview_changed')]);
        }
    }

    protected function editorFingerprint(): string
    {
        return $this->fingerprint(match ($this->editor) {
            'invite' => $this->invitationForm->all(), 'member', 'assign' => $this->memberForm->all(),
            'areas' => ['areaIds' => $this->assignmentForm->areaIds], default => [],
        });
    }

    protected function guardEditor(): bool
    {
        if ($this->editor !== '' && $this->originalEditor !== '' && ! hash_equals($this->originalEditor, $this->editorFingerprint())) {
            $this->errorMessage = __('staff.workspace.unsaved');

            return false;
        }

        return true;
    }

    protected function rememberEditor(): void
    {
        $this->originalEditor = $this->editorFingerprint();
        $this->dispatch('staff-editor-opened');
    }

    protected function attempt(callable $operation): void
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
