<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\User;

final class MenuPolicy
{
    public function __construct(
        private readonly BranchPolicy $branches,
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->branches->view($user, $branch);
    }

    public function view(User $user, Menu $menu): bool
    {
        return $this->withBranch($menu, fn (Branch $branch): bool => $this->branches->view($user, $branch));
    }

    public function create(User $user, Branch $branch): bool
    {
        return $this->branches->manageMenu($user, $branch);
    }

    public function update(User $user, Menu $menu): bool
    {
        return $this->withBranch($menu, fn (Branch $branch): bool => $this->branches->manageMenu($user, $branch));
    }

    public function delete(User $user, Menu $menu): bool
    {
        return $this->update($user, $menu);
    }

    public function restore(User $user, Menu $menu): bool
    {
        return false;
    }

    public function forceDelete(User $user, Menu $menu): bool
    {
        return false;
    }

    public function changePrice(User $user, Menu $menu): bool
    {
        return $this->withBranch($menu, fn (Branch $branch): bool => $this->branches->changeMenuPrices($user, $branch));
    }

    public function changeAvailability(User $user, Menu $menu): bool
    {
        return $this->withBranch($menu, fn (Branch $branch): bool => $this->branches->changeMenuAvailability($user, $branch));
    }

    /**
     * @param  callable(Branch): bool  $authorize
     */
    private function withBranch(Menu $menu, callable $authorize): bool
    {
        $branch = $menu->branch;

        return $branch instanceof Branch && $authorize($branch);
    }
}
