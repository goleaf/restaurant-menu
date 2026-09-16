<?php

declare(strict_types=1);

namespace App\Livewire\Workspace;

use App\Actions\Navigation\RememberWorkspaceAction;
use App\Livewire\Forms\Workspace\RestaurantSelectionForm;
use App\Models\User;
use App\Services\Navigation\ApplicationNavigationPresenter;
use App\Services\Navigation\WorkspaceAccessQuery;
use App\Support\Navigation\WorkspaceContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class RestaurantSwitcher extends Component
{
    public RestaurantSelectionForm $form;

    #[Locked]
    public ?int $branchId = null;

    #[Locked]
    public string $destination = 'overview';

    #[Locked]
    public string $mode = 'none';

    #[Locked]
    public int $actorId;

    #[Locked]
    public int $after = 0;

    #[Locked]
    public ?int $next = null;

    private WorkspaceAccessQuery $queries;

    private ApplicationNavigationPresenter $navigation;

    public function boot(WorkspaceAccessQuery $queries, ApplicationNavigationPresenter $navigation): void
    {
        $this->queries = $queries;
        $this->navigation = $navigation;
    }

    public function mount(?int $branchId = null, string $destination = 'overview', string $mode = 'none'): void
    {
        $this->actorId = (int) Auth::id();
        $this->branchId = $branchId;
        $this->destination = $destination;
        $this->mode = $mode;
    }

    public function updatedFormSearch(): void
    {
        $this->after = 0;
    }

    public function more(): void
    {
        $this->after = $this->next ?? 0;
    }

    public function choose(): void
    {
        $user = $this->actor();
        $data = $this->form->validate();
        $access = $this->queries->destinations($user);
        $branch = $this->queries->branch((int) $data['branchId'], $access);
        $context = new WorkspaceContext($user->id, 'restaurant', $this->destination, $branch->id, $branch->organization_id, $branch->brand_id);
        $target = $this->navigation->destination($context, $access, $this->destination);
        abort_if($target === null, 403);
        if ($target['fallback']) {
            $target['href'] = url()->query($target['href'], ['workspace_notice' => 'section_unavailable']);
        }
        $this->redirect($target['href'], navigate: true);
    }

    public function remember(RememberWorkspaceAction $remember): void
    {
        $actor = $this->actor();
        if ($this->branchId !== null) {
            $remember->handle($actor, $this->branchId, $this->destination, request()->session());
        } elseif ($this->mode === 'aggregate' && in_array($this->destination, ['overview', 'reports', 'audit'], true)) {
            $remember->aggregate($actor, $this->destination, request()->session());
        }
    }

    public function chooseAggregate(): void
    {
        $href = $this->navigation->aggregateDestination($this->destination, $this->queries->destinations($this->actor()));
        abort_if($href === null, 403);
        $this->redirect($href, navigate: true);
    }

    public function render(): View
    {
        $actor = $this->actor();
        $access = $this->queries->destinations($actor);
        $current = $this->branchId === null ? null : $this->queries->branch($this->branchId, $access);
        $search = $this->form->searchTerm();
        $page = $search === null ? ['options' => [], 'next' => null] : $this->queries->search($actor, $search, $this->after, $access);
        $this->next = $page['next'];

        $modeKey = 'workspace.mode.'.$this->mode;
        $canAggregate = $this->navigation->aggregateDestination($this->destination, $access) !== null;

        return view('livewire.workspace.restaurant-switcher', [
            'modeLabel' => __($modeKey),
            'canAggregate' => $canAggregate,
            'currentName' => $current?->name,
            'currentDescription' => $current ? $this->queries->description($current) : null,
            'canChoose' => $canAggregate || count($this->queries->branchIds($access)) > 1 || $current === null,
            'options' => $page['options'],
        ]);
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->id === $this->actorId, 409);

        return $user;
    }
}
