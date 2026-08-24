<?php

declare(strict_types=1);

namespace App\Livewire\Organizations;

use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\DeleteOrganizationAction;
use App\Actions\Organizations\RestoreOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationLogoAction;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\OrganizationQueryService;
use App\Support\LocalizedDateFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    private const PER_PAGE = 15;

    private OrganizationQueryService $organizationQueries;

    public string $name = '';

    public string $search = '';

    #[Url(as: 'lifecycle', except: 'active')]
    public string $lifecycle = 'active';

    #[Url(as: 'sort', except: 'name_asc')]
    public string $sort = 'name_asc';

    /**
     * @var array<int, mixed>
     */
    public array $organizationLogos = [];

    public ?int $editingOrganizationId = null;

    public string $editingName = '';

    public ?int $deletingOrganizationId = null;

    public int $currentUserId = 0;

    public function boot(OrganizationQueryService $organizationQueries): void
    {
        $this->organizationQueries = $organizationQueries;
    }

    public function mount(): void
    {
        $this->currentUserId = $this->currentUser()->id;
    }

    public function create(CreateOrganizationAction $createOrganization): void
    {
        Gate::forUser($this->currentUser())->authorize('create', Organization::class);

        $this->name = trim($this->name);

        $validated = $this->validate($this->organizationNameRules('name'));

        $createOrganization->handle($this->currentUser(), [
            'name' => $validated['name'],
        ]);

        $this->reset('name');
        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.index.organization_created'));
    }

    public function startEditing(int $organizationId): void
    {
        $organization = $this->findOwnedOrganization($organizationId);

        $this->editingOrganizationId = $organization->id;
        $this->editingName = $organization->name;
        $this->deletingOrganizationId = null;
    }

    public function cancelEditing(): void
    {
        $this->reset('editingOrganizationId', 'editingName');
    }

    public function update(UpdateOrganizationAction $updateOrganization): void
    {
        if ($this->editingOrganizationId === null) {
            return;
        }

        $this->editingName = trim($this->editingName);

        $validated = $this->validate(
            $this->organizationNameRules('editingName', $this->editingOrganizationId),
        );

        $updateOrganization->handle($this->findOwnedOrganization($this->editingOrganizationId), [
            'name' => $validated['editingName'],
        ]);

        $this->cancelEditing();
        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.index.organization_updated'));
    }

    public function confirmDelete(int $organizationId): void
    {
        $organization = $this->findOwnedOrganization($organizationId);

        $this->deletingOrganizationId = $organization->id;
        $this->cancelEditing();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingOrganizationId');
    }

    public function delete(DeleteOrganizationAction $deleteOrganization): void
    {
        if ($this->deletingOrganizationId === null) {
            return;
        }

        $deleteOrganization->handle(
            $this->currentUser(),
            $this->findOwnedOrganization($this->deletingOrganizationId),
        );

        $this->cancelDelete();
        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('structure.messages.archived'));
    }

    public function restore(int $organizationId, RestoreOrganizationAction $restoreOrganization): void
    {
        $restoreOrganization->handle(
            $this->currentUser(),
            $this->organizationQueries->findAccessibleTo($this->currentUser(), $organizationId, true),
        );

        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('structure.messages.restored'));
    }

    public function saveLogo(int $organizationId, UpdateOrganizationLogoAction $updateLogo): void
    {
        $organization = $this->findOwnedOrganization($organizationId);

        $this->validate(
            RestaurantValidationRules::imageUpload('organizationLogos.'.$organization->id),
            StoreLocalImageAction::validationMessages('organizationLogos.'.$organization->id),
        );

        $file = $this->organizationLogos[$organization->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $updateLogo->handle($organization, $file);

        unset($this->organizationLogos[$organization->id], $this->organizations);

        Flux::toast(variant: 'success', text: __('uploads.messages.uploaded'));
    }

    public function removeLogo(int $organizationId, UpdateOrganizationLogoAction $updateLogo): void
    {
        $organization = $this->findOwnedOrganization($organizationId);

        $updateLogo->handle($organization, null);

        unset($this->organizationLogos[$organization->id], $this->organizations);

        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: 'organizationsPage');
        unset($this->organizations);
    }

    public function updatedLifecycle(): void
    {
        $this->normalizeLifecycle();
        $this->resetPage(pageName: 'organizationsPage');
        unset($this->organizations);
    }

    public function updatedSort(): void
    {
        $this->normalizeSort();
        $this->resetPage(pageName: 'organizationsPage');
        unset($this->organizations);
    }

    /** @return Paginator<int, Organization> */
    #[Computed]
    public function organizations(): Paginator
    {
        return $this->organizationQueries->paginateAccessibleTo(
            $this->currentUser(),
            $this->search,
            self::PER_PAGE,
            $this->lifecycle,
            $this->sort,
        );
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function staffManageableOrganizationIds(): array
    {
        return $this->organizations()
            ->getCollection()
            ->filter(fn (Organization $organization): bool => Gate::forUser($this->currentUser())->allows('manageStaff', $organization))
            ->pluck('id')
            ->all();
    }

    public function render(): View
    {
        $manageableOrganizationIds = $this->staffManageableOrganizationIds();
        $organizations = $this->organizations();

        return view('livewire.organizations.index', [
            'organizationRows' => $organizations
                ->getCollection()
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'logo_url' => $organization->logoUrl(),
                    'is_owner' => $organization->owner_user_id === $this->currentUserId,
                    'can_manage_staff' => in_array($organization->id, $manageableOrganizationIds, true),
                    'created_at' => LocalizedDateFormatter::date($organization->created_at),
                    'is_archived' => $organization->trashed(),
                    'brands_url' => route('organizations.brands.index', ['organization' => $organization->id]),
                    'staff_url' => route('organizations.staff.index', ['organization' => $organization->id]),
                ])
                ->all(),
            'organizationsPaginator' => $organizations,
        ])->title(__('navigation.organizations'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function organizationNameRules(string $field, ?int $ignoreOrganizationId = null): array
    {
        $uniqueRule = Rule::unique((new Organization)->getTable(), 'name')
            ->where(fn ($query) => $query->where('owner_user_id', $this->currentUserId));

        if ($ignoreOrganizationId !== null) {
            $uniqueRule->ignore($ignoreOrganizationId);
        }

        $rules = RestaurantValidationRules::organizationName($field);
        $rules[$field][] = $uniqueRule;

        return $rules;
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function findOwnedOrganization(int $organizationId): Organization
    {
        $organization = $this->organizationQueries->findAccessibleTo($this->currentUser(), $organizationId);

        Gate::forUser($this->currentUser())->authorize('update', $organization);

        return $organization;
    }

    private function normalizeLifecycle(): void
    {
        if (! in_array($this->lifecycle, ['active', 'archived'], true)) {
            $this->lifecycle = 'active';
        }
    }

    private function normalizeSort(): void
    {
        if (! in_array($this->sort, ['name_asc', 'name_desc', 'newest', 'oldest'], true)) {
            $this->sort = 'name_asc';
        }
    }
}
