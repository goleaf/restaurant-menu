<?php

declare(strict_types=1);

namespace App\Livewire\Restaurants;

use App\Livewire\Forms\Organizations\RestaurantCenterFilterForm;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Locked]
    public int $actorId;

    public RestaurantCenterFilterForm $filters;

    public mixed $organizationSearch = '';

    public mixed $brandSearch = '';

    #[Url(history: true)]
    public mixed $kind = 'branch';

    #[Url(as: 'object', history: true)]
    public mixed $objectId = null;

    #[Url(as: 'create', history: true)]
    public mixed $createKind = null;

    private RestaurantCenterQuery $queries;

    public function boot(RestaurantCenterQuery $queries): void
    {
        $this->queries = $queries;
    }

    public function mount(?Organization $organization = null, ?Brand $brand = null): void
    {
        $this->actorId = (int) Auth::id();
        if ($organization?->exists) {
            Gate::forUser($this->actor())->authorize('view', $organization);
            $this->filters->organizationId = (string) $organization->id;
            $this->filters->view = 'structure';
        }
        if ($brand?->exists) {
            abort_unless((int) $brand->organization_id === (int) $organization?->id, 404);
            Gate::forUser($this->actor())->authorize('view', $brand);
            $this->filters->brandId = (string) $brand->id;
            $this->filters->view = 'restaurants';
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'filters.organizationId') {
            $this->filters->brandId = '';
            $this->brandSearch = '';
        }
        if (str_starts_with($property, 'filters.')) {
            foreach (['page', 'organizationsPage', 'brandsPage'] as $pageName) {
                $this->resetPage($pageName);
            }
        }
    }

    #[On('restaurant-lifecycle-saved')]
    public function lifecycleSaved(): void
    {
        $this->objectId = null;
    }

    #[On('restaurant-identity-saved')]
    public function identitySaved(): void {}

    #[On('structure-identity-created')]
    public function structureCreated(mixed $kind, mixed $id): void
    {
        $data = Validator::make(['kind' => $kind, 'id' => $id], [
            'kind' => ['required', 'string', Rule::in(['organization', 'brand'])],
            'id' => ['required', 'numeric', 'integer', 'min:1'],
        ])->validate();
        $this->queries->identity($this->actor(), $data['kind'], (int) $data['id']);
        $this->createKind = null;
        $this->kind = $data['kind'];
        $this->objectId = (int) $data['id'];
    }

    public function clearFilters(): void
    {
        $this->filters->search = '';
        $this->filters->active = 'all';
        $this->filters->setup = 'all';
        $this->filters->lifecycle = 'active';
        foreach (['page', 'organizationsPage', 'brandsPage'] as $pageName) {
            $this->resetPage($pageName);
        }
    }

    public function render(): View
    {
        $actor = $this->actor();
        $this->validate(['kind' => ['required', 'string', Rule::in(['organization', 'brand', 'branch'])], 'objectId' => ['nullable', 'numeric', 'integer', 'min:1'],
            'createKind' => ['nullable', 'string', Rule::in(['organization', 'brand'])],
            'organizationSearch' => ['nullable', 'string', 'max:100'], 'brandSearch' => ['nullable', 'string', 'max:100']], attributes: [
                'organizationSearch' => __('center.organization'), 'brandSearch' => __('center.brand'),
            ]);
        $validatedFilters = $this->filters->validated();
        $filters = $this->queries->consistentFilters($actor, $validatedFilters);
        if ($filters['brand'] !== $validatedFilters['brand']) {
            $this->filters->brandId = $filters['brand'];
            $this->brandSearch = '';
            $this->resetPage();
        }
        $rows = $this->rows($actor, $filters);
        if ($rows->isEmpty() && $rows->currentPage() > 1) {
            $this->resetPage($rows->getPageName());
            $rows = $this->rows($actor, $filters);
        }
        $rowKind = $filters['view'] === 'restaurants' ? 'branch' : ($filters['organization'] === '' ? 'organization' : 'brand');
        $parameters = ['view' => $filters['view'], 'q' => $filters['search'],
            'organization' => $filters['organization'], 'brand' => $filters['brand'],
            'active' => $filters['active'], 'setup' => $filters['setup'], 'lifecycle' => $filters['lifecycle'], 'sort' => $filters['sort'],
            ...array_intersect_key($this->paginators, array_flip(['page', 'organizationsPage', 'brandsPage']))];
        $rows->through(fn ($resource): array => $this->queries->row($resource, $rowKind, $parameters));
        $secondaryFilterCount = count(array_filter([
            $filters['organization'] !== '', $filters['brand'] !== '',
            $filters['active'] !== 'all', $filters['setup'] !== 'all',
            $filters['lifecycle'] !== 'active', $filters['sort'] !== 'name_asc',
        ]));

        return view('livewire.restaurants.index', ['rows' => $rows, 'rowKind' => $rowKind, 'view' => $filters['view'],
            'secondaryFilterCount' => $secondaryFilterCount,
            'organizations' => $this->queries->filterOrganizations($actor, trim($this->organizationSearch ?? ''), $filters['organization']),
            'brands' => $this->queries->filterBrands($actor, $filters['organization'], trim($this->brandSearch ?? ''), $filters['brand']),
            'emptyState' => $rows->isEmpty() ? $this->queries->emptyState($actor, $filters) : null,
            'canCreateRestaurant' => $this->queries->canCreateRestaurant($actor, $filters['organization']),
            'canCreateOrganization' => Gate::forUser($actor)->allows('create', Organization::class),
            'canCreateBrand' => $this->queries->canCreateBrand($actor, $filters['organization']),
            'creationOrganizationId' => $this->createKind === 'brand' && $filters['organization'] !== '' ? (int) $filters['organization'] : null,
            'createOrganizationUrl' => route('restaurants.index', [...$parameters, 'view' => 'structure', 'create' => 'organization']),
            'createBrandUrl' => route('restaurants.index', [...$parameters, 'view' => 'structure', 'create' => 'brand']),
            'closeUrl' => route('restaurants.index', $parameters),
            'createUrl' => route('restaurants.create', ['organization' => $filters['organization'], 'brand' => $filters['brand']]),
        ])->title(__('center.title'));
    }

    /** @param array<string,string> $filters @return \Illuminate\Pagination\Paginator<int, \App\Models\Branch|Brand|Organization> */
    private function rows(User $actor, array $filters): Paginator
    {
        return match (true) {
            $filters['view'] === 'restaurants' => $this->queries->restaurants($actor, $filters),
            $filters['organization'] !== '' => $this->queries->brands($actor, (int) $filters['organization'], $filters['search'], $filters['lifecycle'], $filters['sort']),
            default => $this->queries->organizations($actor, $filters['search'], $filters['lifecycle'], $filters['sort']),
        };
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && (int) $actor->id === $this->actorId, 403);

        return $actor;
    }
}
