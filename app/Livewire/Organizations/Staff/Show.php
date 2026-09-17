<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Staff;

use App\Actions\Staff\AddBranchStaffMemberAction;
use App\Actions\Staff\ApplyPermissionDraftAction;
use App\Actions\Staff\RemoveBranchStaffAssignmentAction;
use App\Livewire\Forms\Team\AssignmentRemovalForm;
use App\Livewire\Forms\Team\PermissionDraftForm;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class Show extends Index
{
    #[Locked]
    public int $membershipId;

    #[Url(history: true, except: 'overview')]
    public mixed $section = 'overview';

    #[Locked]
    public string $renderedSection = 'overview';

    #[Url(as: 'from', history: true, except: 'employees')]
    public mixed $returnSection = 'employees';

    #[Url(as: 'listPage', except: 1)]
    public mixed $returnPage = 1;

    #[Url(as: 'coverageListPage', except: 1)]
    public mixed $returnCoveragePage = 1;

    public PermissionDraftForm $permissionForm;

    public AssignmentRemovalForm $removalForm;

    #[Locked]
    public string $permissionFingerprint = '';

    /** @var array<int, string> */
    #[Locked]
    public array $originalPermissionStates = [];

    public function mount(Organization $organization, ?Brand $brand = null, ?Branch $branch = null, ?OrganizationUser $member = null, ?User $staffMember = null): void
    {
        $this->actorId = (int) Auth::id();
        $context = $this->staffQueries->context($organization->id, $brand?->id, $branch?->id);
        $this->organization = $context['organization'];
        $this->brand = $context['brand'];
        $this->branch = $context['branch'];
        $membership = $staffMember?->exists
            ? $this->cards->memberForUser($organization, $staffMember->id)
            : $this->cards->member($organization, $member->id ?? 0);
        $this->membershipId = $membership->id;
        $this->authorizeCard();
        if ($staffMember?->exists) {
            $this->section = 'access';
        }
        if ($this->section === 'areas') {
            $this->openAreaSection();
        }
    }

    public function selectSection(string $section): void
    {
        $this->authorizeCard();
        abort_unless(in_array($section, $this->sections(), true), 422);
        if (! $this->guardEditor()) {
            return;
        }
        $this->discardEditor();
        $this->section = $section;
        if ($section === 'areas') {
            $this->openAreaSection();
        }
    }

    public function updatedSection(): void
    {
        if (! $this->guardEditor()) {
            $this->section = $this->renderedSection;

            return;
        }
        $this->selectSection(is_string($this->section) ? $this->section : 'overview');
    }

    public function openPermissions(): void
    {
        $member = $this->authorizeCard();
        Gate::forUser($this->currentUser())->authorize('managePermissions', $member);
        if (! $this->guardEditor()) {
            return;
        }
        $this->discardEditor();
        $snapshot = $this->permissions->draftSnapshot($this->currentUser(), $this->organization, $member->user, $this->branch);
        $this->permissionFingerprint = $snapshot['fingerprint'];
        $this->permissionForm->states = $snapshot['states'];
        $this->originalPermissionStates = $snapshot['states'];
        $this->editor = 'permissions';
        $this->section = 'access';
        $this->rememberEditor();
    }

    public function previewPermissions(): void
    {
        $member = $this->authorizeCard();
        Gate::forUser($this->currentUser())->authorize('managePermissions', $member);
        abort_unless($this->editor === 'permissions', 422);
        $changes = $this->permissionForm->validatedChanges();
        $this->previewFingerprint = '';
        $this->preview = [];
        $this->attempt(function () use ($member, $changes): void {
            $this->preview = $this->permissions->draftPreview($this->currentUser(), $this->organization, $member->user, $changes, $this->permissionFingerprint, $this->branch);
        });
        if ($this->preview === []) {
            return;
        }
        $this->permissionForm->validatedConfirmation($this->preview['requires_confirmation']);
        $this->previewFingerprint = $this->fingerprint($this->permissionForm->all());
    }

    public function applyPermissions(ApplyPermissionDraftAction $apply): void
    {
        $member = $this->authorizeCard();
        Gate::forUser($this->currentUser())->authorize('managePermissions', $member);
        abort_unless($this->editor === 'permissions', 422);
        $changes = $this->permissionForm->validatedChanges();
        $confirmation = $this->permissionForm->validatedConfirmation((bool) ($this->preview['requires_confirmation'] ?? false));
        $this->assertPreview($this->permissionForm->all());
        $this->attempt(function () use ($apply, $member, $changes, $confirmation): void {
            $apply->handle($this->currentUser(), $this->organization, $member->user, $changes, $this->permissionFingerprint,
                $confirmation['reason'], $confirmation['confirmed'], $this->branch);
            $this->discardEditor();
            $this->successMessage = __('staff.workspace.updated');
        });
    }

    public function openExistingAssignment(): void
    {
        $member = $this->authorizeCard();
        if (! $this->guardEditor()) {
            return;
        }
        parent::openExistingAssignment();
        $this->memberForm->organizationMemberId = $member->id;
        $this->memberForm->roleId = $member->role_id;
        $this->rememberEditor();
    }

    public function previewExistingAssignment(): void
    {
        $member = $this->authorizeCard();
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch && $this->editor === 'assign', 422);
        $values = $this->memberForm->validatedAssignment($this->roles()->modelKeys());
        abort_unless($values['organizationMemberId'] === $member->id, 404);
        $this->preview = $this->branchAssignments->preview($this->organization, $this->branch, $member, $this->currentUser());
        $this->previewFingerprint = $this->fingerprint($values);
    }

    public function assignExistingMember(AddBranchStaffMemberAction $assign): void
    {
        $member = $this->authorizeCard();
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch && $this->editor === 'assign', 422);
        $values = $this->memberForm->validatedAssignment($this->roles()->modelKeys());
        abort_unless($values['organizationMemberId'] === $member->id, 404);
        $this->assertPreview($values);
        $role = $this->staffQueries->findAssignableRole($this->currentUser(), $this->organization, $values['roleId']);
        $this->attempt(function () use ($assign, $member, $role): void {
            $assign->handle($this->organization, $this->branch, $role, $this->currentUser(), ['organization_membership_id' => $member->id], $this->preview['fingerprint'], true);
            $this->discardEditor();
            $this->successMessage = __('staff.workspace.updated');
        });
    }

    public function openAssignmentRemoval(): void
    {
        $member = $this->authorizeCard();
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch, 404);
        $assignment = $this->cards->assignment($member, $this->branch);
        abort_unless($assignment !== null, 404);
        $this->authorizeMember($assignment);
        if (! $this->guardEditor()) {
            return;
        }
        $this->discardEditor();
        $this->editor = 'remove-assignment';
        $this->section = 'access';
        $this->rememberEditor();
    }

    public function previewAssignmentRemoval(): void
    {
        $member = $this->authorizeCard();
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch && $this->editor === 'remove-assignment', 422);
        $values = $this->removalForm->validatedRemoval();
        $this->previewFingerprint = '';
        $this->attempt(function () use ($member, $values): void {
            $this->preview = $this->branchAssignments->removalPreview($this->organization, $this->branch, $member, $this->currentUser());
            $this->previewFingerprint = $this->fingerprint($values);
        });
    }

    public function removeAssignment(RemoveBranchStaffAssignmentAction $remove): void
    {
        $member = $this->authorizeCard();
        $this->authorizeStaffManagement();
        abort_unless($this->branch instanceof Branch && $this->editor === 'remove-assignment', 422);
        $values = $this->removalForm->validatedRemoval();
        $this->assertPreview($values);
        $this->attempt(function () use ($member, $remove, $values): void {
            $remove->handle($this->organization, $this->branch, $member, $this->currentUser(), $this->preview['fingerprint'], $values['reason'], $values['confirmed']);
            $this->discardEditor();
            $this->successMessage = __('staff.workspace.updated');
        });
    }

    public function discardEditor(): void
    {
        parent::discardEditor();
        $this->permissionForm->reset();
        $this->removalForm->reset();
        $this->permissionFingerprint = '';
        $this->originalPermissionStates = [];
    }

    public function refreshWorkspace(): void
    {
        $this->authorizeCard();
        $this->successMessage = '';
    }

    public function render(): View
    {
        $member = $this->authorizeCard();
        $card = $this->cards->overview($this->currentUser(), $this->organization, $member, $this->branch);
        $section = is_string($this->section) && in_array($this->section, $this->sections(), true) ? $this->section : 'overview';
        $this->renderedSection = $section;
        $roles = $this->roles();
        $ids = is_array($this->assignmentForm->areaIds) ? array_map(intval(...), array_filter(array_slice($this->assignmentForm->areaIds, 0, 500), fn ($id): bool => (is_string($id) || is_int($id)) && ctype_digit((string) $id))) : [];
        $areas = $this->editor === 'areas' && $this->branch !== null ? $this->staffQueries->areaEditor($this->branch, $ids, is_string($this->assignmentForm->search) ? mb_substr($this->assignmentForm->search, 0, 120) : '', $this->originalAreaIds) : null;
        $history = $section === 'history' && $card['can_history'] ? $this->cards->history($this->currentUser(), $member, $this->branch) : null;
        $snapshot = $section === 'access' ? $this->permissions->draftSnapshot($this->currentUser(), $this->organization, $member->user, $this->branch) : null;
        $returnSection = is_string($this->returnSection) && in_array($this->returnSection, ['employees', 'invitations', 'assignments'], true) ? $this->returnSection : 'employees';
        $filters = $this->filters->validatedFilters($returnSection);
        $returnPage = filter_var($this->returnPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1;
        $pageName = ($this->branch === null ? 'organization' : 'branch').($returnSection === 'invitations' ? 'InvitationsPage' : 'StaffPage');
        $listUrl = route($this->branch === null ? 'organizations.staff.index' : 'organizations.brands.branches.staff.index', [
            'organization' => $this->organization->id, ...($this->branch === null ? [] : ['brand' => $this->brand->id, 'branch' => $this->branch->id]), ...$filters, 'section' => $returnSection, $pageName => $returnPage,
            ...($returnSection === 'assignments' ? ['coveragePage' => filter_var($this->returnCoveragePage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]) ?: 1] : []),
        ]);

        return view('livewire.organizations.staff.show', [
            'card' => $card, 'activeSection' => $section, 'contextLabel' => $this->contextLabel(), 'listUrl' => $listUrl,
            'isBranch' => $this->branch !== null, 'selectedMember' => ['user_name' => $card['name'], 'user_email' => $card['email']],
            'roleOptions' => $roles->map(fn ($role): array => ['id' => $role->id, 'label' => $role->code->localizedLabel()])->all(),
            'areaEditor' => $areas, 'areasAdded' => count(array_diff($ids, $this->originalAreaIds)), 'areasRemoved' => count(array_diff($this->originalAreaIds, $ids)),
            'coverageLabel' => $ids === [] ? __('staff.workspace.coverage_all') : __('staff.workspace.coverage_count', ['count' => count($ids)]),
            'showTechnicalPermissionKeys' => $this->currentUser()->isSuperadmin(),
            'capabilities' => $section === 'access' && $this->branch !== null ? $this->cards->capabilities($member->user, $this->branch) : [],
            'permissionRows' => $snapshot['rows'] ?? [],
            'permissionGroups' => collect($snapshot['rows'] ?? [])->groupBy('group_key')->map(fn ($rows): array => ['label' => $rows->first()['group_label'], 'rows' => $rows->all()])->all(), 'historyPaginator' => $history,
            'historyRows' => $history?->getCollection()->map(fn ($event): array => $this->cards->historyRow($event))->all() ?? [],
        ])->title($card['name']);
    }

    protected function findMember(int $id): OrganizationUser|BranchUser
    {
        $member = $this->authorizeCard();
        $selected = $this->branch === null ? $member : $this->cards->assignment($member, $this->branch);
        abort_unless($selected !== null && $selected->id === $id, 404);

        return $selected;
    }

    protected function isBranchWorkspace(): bool
    {
        return $this->branch !== null;
    }

    protected function sections(): array
    {
        return ['overview', 'access', 'areas', 'history'];
    }

    protected function editorFingerprint(): string
    {
        return match ($this->editor) {
            'permissions' => $this->fingerprint($this->permissionForm->all()),
            'remove-assignment' => $this->fingerprint($this->removalForm->all()),
            default => parent::editorFingerprint(),
        };
    }

    protected function attempt(callable $operation): void
    {
        try {
            parent::attempt($operation);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $field => $messages) {
                $mapped = match (true) {
                    $this->editor === 'permissions' && in_array($field, ['states', 'reason', 'confirmed'], true) => 'permissionForm.'.$field,
                    $this->editor === 'remove-assignment' && in_array($field, ['reason', 'confirmed'], true) => 'removalForm.'.$field,
                    $this->editor === 'remove-assignment' && $field === 'organizationMembershipId' => 'removalForm.confirmed',
                    $this->editor === 'member' && $field === 'editingRoleId' => 'memberForm.roleId',
                    $this->editor === 'member' && $field === 'reason' => 'memberForm.reason',
                    $this->editor === 'assign' && $field === 'organizationMembershipId' => 'memberForm.organizationMemberId',
                    default => $field,
                };
                $errors[$mapped] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }

    private function authorizeCard(): OrganizationUser
    {
        $member = $this->cards->member($this->organization, $this->membershipId);
        Gate::forUser($this->currentUser())->authorize('viewCard', [$member, $this->branch]);

        return $member;
    }

    private function openAreaSection(): void
    {
        $member = $this->authorizeCard();
        $card = $this->cards->overview($this->currentUser(), $this->organization, $member, $this->branch);
        if ($card['can_areas']) {
            $this->openAssignments($card['membership_id']);
        }
    }
}
