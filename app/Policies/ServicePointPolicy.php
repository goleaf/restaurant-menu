<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\ServicePoint;
use App\Models\User;

final class ServicePointPolicy
{
    public function __construct(
        private readonly BranchPolicy $branches,
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->branches->view($user, $branch);
    }

    public function view(User $user, ServicePoint $servicePoint): bool
    {
        return ! $servicePoint->trashed()
            && $this->withBranch($servicePoint, fn (Branch $branch): bool => $this->branches->view($user, $branch));
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->branches->manageServicePoints($user, $branch);
    }

    public function update(User $user, ServicePoint $servicePoint): bool
    {
        return ! $servicePoint->trashed()
            && $this->withBranch($servicePoint, fn (Branch $branch): bool => $this->branches->manageServicePoints($user, $branch));
    }

    public function delete(User $user, ServicePoint $servicePoint): bool
    {
        return $this->update($user, $servicePoint);
    }

    public function restore(User $user, ServicePoint $servicePoint): bool
    {
        return $servicePoint->trashed()
            && $this->withBranch($servicePoint, fn (Branch $branch): bool => $this->branches->manageServicePoints($user, $branch));
    }

    public function forceDelete(User $user, ServicePoint $servicePoint): bool
    {
        return false;
    }

    public function changeStatus(User $user, ServicePoint $servicePoint): bool
    {
        return $this->withBranch($servicePoint, fn (Branch $branch): bool => $this->branches->changeServicePointStatus($user, $branch));
    }

    public function generateQr(User $user, ServicePoint $servicePoint): bool
    {
        return $this->withBranch($servicePoint, fn (Branch $branch): bool => $this->branches->generateQr($user, $branch));
    }

    public function openTable(User $user, ServicePoint $servicePoint): bool
    {
        return $this->withBranch($servicePoint, fn (Branch $branch): bool => $this->branches->openTable($user, $branch));
    }

    /**
     * @param  callable(Branch): bool  $authorize
     */
    private function withBranch(ServicePoint $servicePoint, callable $authorize): bool
    {
        $branch = $servicePoint->branch;

        return $branch instanceof Branch && $authorize($branch);
    }
}
