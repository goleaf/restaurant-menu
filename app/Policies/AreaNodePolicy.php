<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;

final class AreaNodePolicy
{
    public function __construct(
        private readonly BranchPolicy $branches,
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->branches->view($user, $branch);
    }

    public function view(User $user, AreaNode $areaNode): bool
    {
        return ! $areaNode->trashed()
            && $this->withBranch($areaNode, fn (Branch $branch): bool => $this->branches->view($user, $branch));
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->branches->manageZones($user, $branch);
    }

    public function update(User $user, AreaNode $areaNode): bool
    {
        return ! $areaNode->trashed()
            && $this->withBranch($areaNode, fn (Branch $branch): bool => $this->branches->manageZones($user, $branch));
    }

    public function delete(User $user, AreaNode $areaNode): bool
    {
        return $this->update($user, $areaNode);
    }

    public function restore(User $user, AreaNode $areaNode): bool
    {
        return $areaNode->trashed()
            && $this->withBranch($areaNode, fn (Branch $branch): bool => $this->branches->manageZones($user, $branch));
    }

    public function forceDelete(User $user, AreaNode $areaNode): bool
    {
        return false;
    }

    /**
     * @param  callable(Branch): bool  $authorize
     */
    private function withBranch(AreaNode $areaNode, callable $authorize): bool
    {
        $branch = $areaNode->branch;

        return $branch instanceof Branch && $authorize($branch);
    }
}
