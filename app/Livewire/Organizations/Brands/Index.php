<?php

namespace App\Livewire\Organizations\Brands;

use App\Actions\Brands\CreateBrandAction;
use App\Actions\Brands\DeleteBrandAction;
use App\Actions\Brands\UpdateBrandAction;
use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Brands')]
class Index extends Component
{
    use WithFileUploads;

    public Organization $organization;

    public string $name = '';

    /**
     * @var array<int, mixed>
     */
    public array $brandLogos = [];

    public ?int $editingBrandId = null;

    public string $editingName = '';

    public ?int $deletingBrandId = null;

    public bool $canManageBrands = false;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;

        if (! $this->currentUser()->canAccessOrganization($organization)) {
            abort(403);
        }

        $this->canManageBrands = $this->currentUser()->canManageOrganizationBrands($organization);
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

        Flux::toast(variant: 'success', text: __('Brand created.'));
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

        Flux::toast(variant: 'success', text: __('Brand updated.'));
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

        $deleteBrand->handle($this->findOrganizationBrand($this->deletingBrandId));

        $this->cancelDelete();
        unset($this->brands);

        Flux::toast(variant: 'success', text: __('Brand deleted.'));
    }

    public function saveLogo(int $brandId, StoreLocalImageAction $storeLocalImage): void
    {
        $this->authorizeBrandManagement();

        $brand = $this->findOrganizationBrand($brandId);

        $this->validate([
            'brandLogos.'.$brand->id => StoreLocalImageAction::validationRules(),
        ]);

        $file = $this->brandLogos[$brand->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $brand->update([
            'logo_path' => $storeLocalImage->handle(
                file: $file,
                directory: 'media/organizations/'.$this->organization->id.'/brands/'.$brand->id.'/logos',
                oldPath: $brand->logo_path,
            ),
        ]);

        unset($this->brandLogos[$brand->id], $this->brands);

        Flux::toast(variant: 'success', text: __('Logo uploaded.'));
    }

    public function removeLogo(int $brandId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $this->authorizeBrandManagement();

        $brand = $this->findOrganizationBrand($brandId);

        $deleteLocalMediaFile->handle($brand->logo_path);
        $brand->update(['logo_path' => null]);

        unset($this->brandLogos[$brand->id], $this->brands);

        Flux::toast(variant: 'success', text: __('Logo removed.'));
    }

    /**
     * @return EloquentCollection<int, Brand>
     */
    #[Computed]
    public function brands(): EloquentCollection
    {
        return $this->organization
            ->brands()
            ->select([
                'id',
                'organization_id',
                'name',
                'logo_path',
                'created_at',
                'updated_at',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.index');
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

        return [
            $field => ['required', 'string', 'max:120', $uniqueRule],
        ];
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
        if (! $this->canManageBrands) {
            abort(403);
        }
    }

    private function findOrganizationBrand(int $brandId): Brand
    {
        return $this->organization
            ->brands()
            ->select([
                'id',
                'organization_id',
                'name',
                'logo_path',
                'created_at',
                'updated_at',
            ])
            ->whereKey($brandId)
            ->firstOrFail();
    }
}
