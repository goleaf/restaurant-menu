<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\Branch;
use App\Models\MenuOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EnsureMenuOperationAccessAction
{
    public function handle(User $actor, Branch $branch, string $requestId): Branch
    {
        if (! Str::isUuid($requestId)) {
            throw ValidationException::withMessages(['operation' => __('menu.operations.errors.invalid_request')]);
        }

        $currentActor = $actor->fresh();
        $currentBranch = Branch::query()->whereKey($branch->id)->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)->first();

        if (! $currentActor instanceof User || ! $currentBranch instanceof Branch) {
            throw new AuthorizationException;
        }
        Gate::forUser($currentActor)->authorize('manageMenu', $currentBranch);

        return $currentBranch;
    }

    public function assertOwner(MenuOperation $operation, User $actor, Branch $branch): void
    {
        if ((int) $operation->branch_id !== (int) $branch->id || (int) $operation->actor_user_id !== (int) $actor->id) {
            throw new AuthorizationException;
        }
    }
}
