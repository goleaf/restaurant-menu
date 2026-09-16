<?php

declare(strict_types=1);

namespace App\Actions\Navigation;

use App\Models\User;
use App\Services\Navigation\ApplicationNavigationPresenter;
use App\Services\Navigation\WorkspaceAccessQuery;
use App\Support\Navigation\WorkspaceContext;
use Illuminate\Contracts\Session\Session;

final class RememberWorkspaceAction
{
    public function __construct(private readonly WorkspaceAccessQuery $queries, private readonly ApplicationNavigationPresenter $navigation) {}

    public function handle(User $user, int $branchId, string $destination, Session $session): void
    {
        $access = $this->queries->destinations($user);
        $branch = $this->queries->branch($branchId, $access);
        $context = new WorkspaceContext($user->id, 'restaurant', $destination, $branch->id, $branch->organization_id, $branch->brand_id);
        $target = $this->navigation->destination($context, $access, $destination);
        abort_if($target === null || $target['fallback'], 403);
        $session->put('workspace.preference', ['actor' => $user->id, 'branch' => $branchId, 'destination' => $destination]);
    }

    public function aggregate(User $user, string $destination, Session $session): void
    {
        abort_if($this->navigation->aggregateDestination($destination, $this->queries->destinations($user)) === null, 403);
        $session->put('workspace.preference', ['actor' => $user->id, 'mode' => 'aggregate', 'destination' => $destination]);
    }
}
