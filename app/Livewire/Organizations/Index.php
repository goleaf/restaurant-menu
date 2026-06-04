<?php

namespace App\Livewire\Organizations;

use App\Actions\Media\DeleteLocalMediaFileAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\DeleteOrganizationAction;
use App\Actions\Organizations\UpdateOrganizationAction;
use App\Enums\OrganizationSubscriptionStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
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

#[Title('Organizations')]
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
        $this->name = trim($this->name);

        $validated = $this->validate($this->organizationNameRules('name'));

        $createOrganization->handle($this->currentUser(), [
            'name' => $validated['name'],
        ]);

        $this->reset('name');
        unset($this->organizations);

        Flux::toast(variant: 'success', text: __('Organization created.'));
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

        Flux::toast(variant: 'success', text: __('Organization updated.'));
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

        Flux::toast(variant: 'success', text: __('Organization deleted.'));
    }

    public function saveLogo(int $organizationId, StoreLocalImageAction $storeLocalImage): void
    {
        $organization = $this->findOwnedOrganization($organizationId);

        $this->validate([
            'organizationLogos.'.$organization->id => StoreLocalImageAction::validationRules(),
        ]);

        $file = $this->organizationLogos[$organization->id] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $organization->update([
            'logo_path' => $storeLocalImage->handle(
                file: $file,
                directory: 'media/organizations/'.$organization->id.'/logos',
                oldPath: $organization->logo_path,
            ),
        ]);

        unset($this->organizationLogos[$organization->id], $this->organizations);

        Flux::toast(variant: 'success', text: __('Logo uploaded.'));
    }

    public function removeLogo(int $organizationId, DeleteLocalMediaFileAction $deleteLocalMediaFile): void
    {
        $organization = $this->findOwnedOrganization($organizationId);

        $deleteLocalMediaFile->handle($organization->logo_path);
        $organization->update(['logo_path' => null]);

        unset($this->organizationLogos[$organization->id], $this->organizations);

        Flux::toast(variant: 'success', text: __('Logo removed.'));
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
        return $this->organizations
            ->filter(fn (Organization $organization): bool => $this->currentUser()->hasPermission(SystemPermission::ManageStaff, $organization))
            ->pluck('id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.organizations.index');
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

        if (! $this->currentUser()->canAccessOrganization($organization)) {
            abort(403);
        }

        if (! $this->currentUser()->hasOrganizationRole($organization, SystemRole::Owner)) {
            abort(403);
        }

        return $organization;
    }
}
