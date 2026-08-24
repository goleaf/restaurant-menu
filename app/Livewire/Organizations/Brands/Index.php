<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands;

use App\Actions\Brands\CreateBrandAction;
use App\Actions\Brands\DeleteBrandAction;
use App\Actions\Brands\RestoreBrandAction;
use App\Actions\Brands\UpdateBrandAction;
use App\Actions\Brands\UpdateBrandLogoAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\BrandQueryService;
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

    private BrandQueryService $brandQueries;

    public Organization $organization;

    public string $name = '';

    public string $search = '';

    #[Url(as: 'lifecycle', except: 'active')]
    public string $lifecycle = 'active';

    #[Url(as: 'sort', except: 'name_asc')]
    public string $sort = 'name_asc';

    /**
     * @var array<int, mixed>
     */
    public array $brandLogos = [];

    public ?int $editingBrandId = null;

    public string $editingName = '';

    public ?int $deletingBrandId = null;

    public bool $canManageBrands = false;

    public function boot(BrandQueryService $brandQueries): void
    {
        $this->brandQueries = $brandQueries;
    }

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $user = $this->currentUser();

        Gate::forUser($user)->authorize('viewAny', [Brand::class, $organization]);

        $this->canManageBrands = Gate::forUser($user)->allows('create', [Brand::class, $organization]);
    }

    public function create(CreateBrandAction $createBrand): void
    {
        $this->authorizeBrandManagement();

        $this->name = trim($this->name);

        $validated = $this->validate($this->brandNameRules('name'));

        $createBrand->handle($this->organization, [
            'name' => $validated['name'],
        ]);

        $this->reset('name');
        unset($this->brands);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.index.brand_created'));
    }

    public function startEditing(int $brandId): void
    {
        $this->authorizeBrandManagement();

        $brand = $this->findOrganizationBrand($brandId);

        $this->editingBrandId = $brand->id;
        $this->editingName = $brand->name;
        $this->deletingBrandId = null;
    }

    public function cancelEditing(): void
    {
        $this->reset('editingBrandId', 'editingName');
    }

    public function update(UpdateBrandAction $updateBrand): void
    {
        $this->authorizeBrandManagement();

        if ($this->editingBrandId === null) {
            return;
        }

        $this->editingName = trim($this->editingName);

        $validated = $this->validate(
            $this->brandNameRules('editingName', $this->editingBrandId),
        );

        $updateBrand->handle($this->findOrganizationBrand($this->editingBrandId), [
            'name' => $validated['editingName'],
        ]);

        $this->cancelEditing();
        unset($this->brands);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.index.brand_updated'));
    }

    public function confirmDelete(int $brandId): void
    {
        $this->authorizeBrandManagement();

        $brand = $this->findOrganizationBrand($brandId);

        $this->deletingBrandId = $brand->id;
        $this->cancelEditing();
    }

    public function cancelDelete(): void
    {
        $this->reset('deletingBrandId');
    }

    public function delete(DeleteBrandAction $deleteBrand): void
    {
        $this->authorizeBrandManagement();

        if ($this->deletingBrandId === null) {
            return;
        }

        $deleteBrand->handle(
            $this->currentUser(),
            $this->organization,
            $this->findOrganizationBrand($this->deletingBrandId),
        );

        $this->cancelDelete();
        unset($this->brands);

        Flux::toast(variant: 'success', text: __('structure.messages.archived'));
    }

    public function restore(int $brandId, RestoreBrandAction $restoreBrand): void
    {
        $restoreBrand->handle(
            $this->currentUser(),
            $this->organization,
            $this->brandQueries->findForOrganization($this->organization, $brandId, true),
        );

        unset($this->brands);

        Flux::toast(variant: 'success', text: __('structure.messages.restored'));
    }

    public function saveLogo(int $brandId, UpdateBrandLogoAction $updateLogo): void
    {
        $this->authorizeBrandManagement();

        $brand = $this->findOrganizationBrand($brandId);

        $this->validate(
            RestaurantValidationRules::imageUpload('brandLogos.'.$brand->id),
            StoreLocalImageAction::validationMessages('brandLogos.'.$brand->id),
        );

        $file = $this->brandLogos[$brand->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $updateLogo->handle($brand, $file);

        unset($this->brandLogos[$brand->id], $this->brands);

        Flux::toast(variant: 'success', text: __('uploads.messages.uploaded'));
    }

    public function removeLogo(int $brandId, UpdateBrandLogoAction $updateLogo): void
    {
        $this->authorizeBrandManagement();

        $brand = $this->findOrganizationBrand($brandId);

        $updateLogo->handle($brand, null);

        unset($this->brandLogos[$brand->id], $this->brands);

        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: 'brandsPage');
        unset($this->brands);
    }

    public function updatedLifecycle(): void
    {
        $this->normalizeLifecycle();
        $this->resetPage(pageName: 'brandsPage');
        unset($this->brands);
    }

    public function updatedSort(): void
    {
        $this->normalizeSort();
        $this->resetPage(pageName: 'brandsPage');
        unset($this->brands);
    }

    /** @return Paginator<int, Brand> */
    #[Computed]
    public function brands(): Paginator
    {
        return $this->brandQueries->paginateForOrganization(
            $this->organization,
            $this->search,
            self::PER_PAGE,
            $this->lifecycle,
            $this->sort,
        );
    }

    public function render(): View
    {
        $brands = $this->brands();

        return view('livewire.organizations.brands.index', [
            'organizationName' => $this->organization->name,
            'brandRows' => $brands
                ->getCollection()
                ->map(fn (Brand $brand): array => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'logo_url' => $brand->logoUrl(),
                    'created_at' => $brand->created_at->format('d.m.Y'),
                    'is_archived' => $brand->trashed(),
                    'branches_url' => route('organizations.brands.branches.index', [
                        'organization' => $this->organization->id,
                        'brand' => $brand->id,
                    ]),
                ])
                ->all(),
            'brandsPaginator' => $brands,
        ])->title(__('navigation.brands'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function brandNameRules(string $field, ?int $ignoreBrandId = null): array
    {
        $uniqueRule = Rule::unique((new Brand)->getTable(), 'name')
            ->where(fn ($query) => $query->where('organization_id', $this->organization->id));

        if ($ignoreBrandId !== null) {
            $uniqueRule->ignore($ignoreBrandId);
        }

        $rules = RestaurantValidationRules::brandName($field);
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

    private function authorizeBrandManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('create', [Brand::class, $this->organization]);
    }

    private function findOrganizationBrand(int $brandId): Brand
    {
        $brand = $this->brandQueries->findForOrganization($this->organization, $brandId);

        Gate::forUser($this->currentUser())->authorize('update', $brand);

        return $brand;
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
