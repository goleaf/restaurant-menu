<?php

declare(strict_types=1);

namespace App\Livewire\Restaurants;

use App\Actions\Organizations\CreateStructureIdentityAction;
use App\Livewire\Forms\Organizations\StructureCreationForm;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class StructureCreate extends Component
{
    public StructureCreationForm $form;

    #[Locked]
    public string $kind;

    #[Locked]
    public ?int $organizationId = null;

    #[Locked]
    public int $actorId;

    #[Locked]
    public string $requestId;

    public function mount(string $kind, ?int $organizationId = null): void
    {
        $this->kind = $kind;
        $this->organizationId = $organizationId;
        $this->actorId = (int) Auth::id();
        $this->requestId = (string) Str::uuid();
    }

    public function save(CreateStructureIdentityAction $create): void
    {
        $resource = $create->handle($this->actor(), $this->kind, $this->organizationId, $this->form->validated(), $this->requestId);
        $this->dispatch('structure-identity-created', kind: $this->kind, id: $resource->id);
    }

    public function render(RestaurantCenterQuery $queries): View
    {
        return view('livewire.restaurants.structure-create', [
            'title' => __($this->kind === 'organization' ? 'center.new_organization' : 'center.new_brand'),
            'nameLabel' => __($this->kind === 'organization' ? 'center.organization_name' : 'center.brand_name'),
            'organizationName' => $queries->structureCreationParent($this->actor(), $this->kind, $this->organizationId)?->name,
        ]);
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && (int) $actor->id === $this->actorId, 403);

        return $actor;
    }
}
