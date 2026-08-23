<?php

declare(strict_types=1);

namespace App\Livewire\Organizations;

use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\DeleteOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationLogoAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public string $name = '';

    /**
     * @var array<int, mixed>
     */
    public array $organizationLogos = [];

    public ?int $editingOrganizationId = null;

    public string $editingName = '';

    public ?int $deletingOrganizationId = null;

    public int $currentUserId = 0;

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

        $deleteOrganization->handle($this->findOwnedOrganization($this->deletingOrganizationId));

        $this->cancelDelete();
        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.index.organization_deleted'));
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

    /**
     * @return EloquentCollection<int, Organization>
     */
    #[Computed]
    public function organizations(): EloquentCollection
    {
        return $this->currentUser()
            ->organizations()
            ->wherePivot('status', OrganizationUserStatus::Active->value)
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('subscription')
                    ->orWhereHas('subscription', function ($subscriptionQuery): void {
                        $subscriptionQuery->where('status', OrganizationSubscriptionStatus::Active->value);
                    });
            })
            ->select([
                'organizations.id',
                'organizations.owner_user_id',
                'organizations.name',
                'organizations.logo_path',
                'organizations.created_at',
                'organizations.updated_at',
            ])
            ->orderBy('organizations.name')
            ->orderBy('organizations.id')
            ->get();
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function staffManageableOrganizationIds(): array
    {
        return $this->organizations()
            ->filter(fn (Organization $organization): bool => Gate::forUser($this->currentUser())->allows('manageStaff', $organization))
            ->pluck('id')
            ->all();
    }

    public function render(): View
    {
        $manageableOrganizationIds = $this->staffManageableOrganizationIds();

        return view('livewire.organizations.index', [
            'organizationRows' => $this->organizations()
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'logo_url' => $organization->logoUrl(),
                    'is_owner' => $organization->owner_user_id === $this->currentUserId,
                    'can_manage_staff' => in_array($organization->id, $manageableOrganizationIds, true),
                    'created_at' => $organization->created_at->format('d.m.Y'),
                    'brands_url' => route('organizations.brands.index', ['organization' => $organization->id]),
                    'staff_url' => route('organizations.staff.index', ['organization' => $organization->id]),
                ])
                ->all(),
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
        $organization = Organization::query()
            ->select([
                'id',
                'owner_user_id',
                'name',
                'logo_path',
                'created_at',
                'updated_at',
            ])
            ->whereKey($organizationId)
            ->firstOrFail();

        Gate::forUser($this->currentUser())->authorize('update', $organization);

        return $organization;
    }
}
