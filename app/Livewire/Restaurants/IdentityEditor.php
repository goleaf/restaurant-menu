<?php

declare(strict_types=1);

namespace App\Livewire\Restaurants;

use App\Actions\Branches\UpdateBranchAction;
use App\Actions\Branches\UpdateBranchLogoAction;
use App\Actions\Brands\UpdateBrandAction;
use App\Actions\Brands\UpdateBrandLogoAction;
use App\Actions\Onboarding\ContinueRestaurantPreparationAction;
use App\Actions\Organizations\ChangeStructureLifecycleAction;
use App\Actions\Organizations\UpdateOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationLogoAction;
use App\Livewire\Forms\Organizations\RestaurantIdentityForm;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use App\Services\Restaurant\BranchReadinessService;
use App\Support\RestaurantSetupOptions;
use App\Support\Validation\Media\ImageUploadRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class IdentityEditor extends Component
{
    use WithFileUploads;

    public RestaurantIdentityForm $form;

    #[Locked]
    public string $kind;

    #[Locked]
    public int $objectId;

    #[Locked]
    public int $actorId;

    #[Locked]
    public string $fingerprint;

    #[Locked]
    public string $mediaFingerprint;

    #[Locked]
    public bool $wasArchived;

    public mixed $logo = null;

    public string $confirmation = '';

    public bool $confirming = false;

    private RestaurantCenterQuery $queries;

    public function boot(RestaurantCenterQuery $queries): void
    {
        $this->queries = $queries;
    }

    public function mount(string $kind, int $objectId): void
    {
        $this->kind = $kind;
        $this->objectId = $objectId;
        $this->actorId = (int) Auth::id();
        $resource = $this->resource();
        $this->form->load($resource);
        $this->fingerprint = $resource->identityFingerprint();
        $this->mediaFingerprint = hash('sha256', (string) $resource->logo_path);
        $this->wasArchived = $resource->trashed();
    }

    public function save(UpdateOrganizationAction $organization, UpdateBrandAction $brand, UpdateBranchAction $branch): void
    {
        $resource = $this->resource();
        Gate::forUser($this->actor())->authorize('update', $resource);
        $data = $this->form->validated($resource);
        if ($resource instanceof Organization) {
            $saved = $organization->handle($resource, ['name' => $data['name']], $this->actor(), $this->fingerprint);
        } elseif ($resource instanceof Brand) {
            $saved = $brand->handle($resource, ['name' => $data['name']], $this->actor(), $this->fingerprint);
        } else {
            $saved = $branch->handle($resource, ['name' => $data['name'], 'address' => $data['address'], 'city' => $data['city'], 'country' => $data['country'], 'timezone' => $data['timezone'], 'currency' => $data['currency'], 'is_active' => (bool) $data['isActive']], $this->actor(), expectedFingerprint: $this->fingerprint);
        }
        $this->fingerprint = $saved->identityFingerprint();
        $this->dispatch('restaurant-identity-saved');
        Flux::toast(variant: 'success', text: __('center.saved'));
    }

    public function saveLogo(UpdateOrganizationLogoAction $organization, UpdateBrandLogoAction $brand, UpdateBranchLogoAction $branch): void
    {
        $resource = $this->resource();
        Gate::forUser($this->actor())->authorize('update', $resource);
        $validated = Validator::make(['logo' => $this->logo], ImageUploadRules::imageUpload('logo'), ImageUploadRules::messages('logo'), ['logo' => __('center.logo')])->validate();
        $this->resetErrorBag('logo');
        match (true) {
            $resource instanceof Organization => $organization->handle($resource, $validated['logo'], $this->actor(), $this->mediaFingerprint),
            $resource instanceof Brand => $brand->handle($resource, $validated['logo'], $this->actor(), $this->mediaFingerprint),
            default => $branch->handle($resource, $validated['logo'], $this->actor(), $this->mediaFingerprint),
        };
        $this->mediaFingerprint = hash('sha256', (string) $resource->logo_path);
        $this->logo = null;
        $this->dispatch('restaurant-logo-saved');
        Flux::toast(variant: 'success', text: __('center.logo_saved'));
    }

    public function removeLogo(UpdateOrganizationLogoAction $organization, UpdateBrandLogoAction $brand, UpdateBranchLogoAction $branch): void
    {
        $resource = $this->resource();
        Gate::forUser($this->actor())->authorize('update', $resource);
        match (true) {
            $resource instanceof Organization => $organization->handle($resource, null, $this->actor(), $this->mediaFingerprint),
            $resource instanceof Brand => $brand->handle($resource, null, $this->actor(), $this->mediaFingerprint),
            default => $branch->handle($resource, null, $this->actor(), $this->mediaFingerprint),
        };
        $this->mediaFingerprint = hash('sha256', (string) $resource->logo_path);
        Flux::toast(variant: 'success', text: __('uploads.messages.removed'));
        Flux::modal('remove-restaurant-logo')->close();
    }

    public function changeLifecycle(ChangeStructureLifecycleAction $change): void
    {
        $resource = $this->resource();
        $change->handle($this->actor(), $resource, $this->fingerprint, $this->confirmation, $this->wasArchived);
        $current = $this->resource();
        $this->wasArchived = $current->trashed();
        $this->fingerprint = $current->identityFingerprint();
        $this->confirmation = '';
        $this->confirming = false;
        $this->dispatch('restaurant-lifecycle-saved');
    }

    public function continueSetup(ContinueRestaurantPreparationAction $continue): void
    {
        $resource = $this->resource();
        abort_unless($resource instanceof Branch, 404);
        $setup = $continue->handle($this->actor(), $resource);
        $this->redirectRoute('restaurants.setup', ['setup' => $setup->id], navigate: true);
    }

    public function render(BranchReadinessService $readiness): View
    {
        $resource = $this->resource();

        return view('livewire.restaurants.identity-editor', [
            'canEdit' => Gate::forUser($this->actor())->allows('update', $resource),
            'title' => $resource->name, 'logoUrl' => $resource->logoUrl(), 'isRestaurant' => $resource instanceof Branch,
            'logoPreview' => $this->logo instanceof TemporaryUploadedFile && $this->logo->isPreviewable() ? $this->logo->temporaryUrl() : null,
            'currencies' => RestaurantSetupOptions::currencyOptions(), 'timezones' => RestaurantSetupOptions::timezoneOptions(),
            'archived' => $resource->trashed(),
            'canChangeLifecycle' => Gate::forUser($this->actor())->allows($resource->trashed() ? 'restore' : 'delete', $resource),
            'scopeLabel' => match ($this->kind) {
                'organization' => __('center.organization'), 'brand' => __('center.brand'), default => __('center.restaurant_name')
            },
            'readiness' => $resource instanceof Branch && ! $resource->trashed() ? $readiness->handle($this->actor(), $resource) : null,
        ]);
    }

    private function resource(): Organization|Brand|Branch
    {
        return $this->queries->identity($this->actor(), $this->kind, $this->objectId);
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && (int) $actor->id === $this->actorId, 403);

        return $actor;
    }
}
