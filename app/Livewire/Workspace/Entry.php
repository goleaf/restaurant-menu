<?php

declare(strict_types=1);

namespace App\Livewire\Workspace;

use App\Models\User;
use App\Services\Navigation\ApplicationNavigationPresenter;
use App\Services\Navigation\WorkspaceAccessQuery;
use App\Services\Navigation\WorkspaceContextResolver;
use App\Services\Onboarding\RestaurantSetupQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Livewire\Component;

final class Entry extends Component
{
    public bool $canSetUp = false;

    public bool $hasRestaurants = false;

    public function mount(Request $request, WorkspaceAccessQuery $queries, WorkspaceContextResolver $resolver, ApplicationNavigationPresenter $navigation, RestaurantSetupQueryService $setup): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $access = $queries->destinations($user);
        $this->hasRestaurants = $queries->branchIds($access) !== [];
        $context = $resolver->resolve($user, $request, $access);
        if ($context->branchId !== null) {
            $preference = $request->session()->get('workspace.preference', []);
            $preferred = is_array($preference) && ($preference['actor'] ?? null) === $user->id && is_string($preference['destination'] ?? null) ? $preference['destination'] : null;
            $destination = $navigation->destination($context, $access, $preferred);
            if ($destination !== null) {
                if ($destination['fallback']) {
                    $destination['href'] = url()->query($destination['href'], ['workspace_notice' => 'section_unavailable']);
                }
                $this->redirect($destination['href']);

                return;
            }
        }
        if ($context->mode === 'aggregate') {
            $href = $navigation->aggregateDestination($context->destination, $access);
            if ($href !== null) {
                $this->redirect($href);

                return;
            }
        }
        $this->canSetUp = $setup->userHasAccess($user);
    }

    public function render(): View
    {
        return view('livewire.workspace.entry')->title(__('workspace.choose'));
    }
}
