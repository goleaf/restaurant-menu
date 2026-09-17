<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PrepareAreaNodeMutationAction
{
    /** @return array{actor:User,branch:Branch,area:AreaNode|null} */
    public function handle(int $branchId, ?User $actor, string $ability, ?int $areaId = null, ?int $expectedVersion = null): array
    {
        $actor ??= Auth::user();
        $actor = $actor instanceof User ? $actor->fresh(['roles:id,code']) : null;
        if (! $actor instanceof User) {
            throw new AuthorizationException;
        }
        $branch = Branch::query()->whereKey($branchId)->firstOrFail();
        $area = $areaId === null ? null : $branch->areaNodes()
            ->when($ability === 'restore', fn ($query) => $query->withTrashed())
            ->whereKey($areaId)->lockForUpdate()->firstOrFail();
        if ($area instanceof AreaNode) {
            $area->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize($ability, $area);
            if ($expectedVersion !== null && $area->structure_version !== $expectedVersion) {
                throw ValidationException::withMessages(['structureVersion' => __('floor.errors.stale')]);
            }
        } else {
            Gate::forUser($actor)->authorize('create', [AreaNode::class, $branch]);
        }

        return compact('actor', 'branch', 'area');
    }
}
