<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RestoreAreaNodeAction
{
    public function __construct(
        private readonly PrepareAreaNodeMutationAction $prepare,
        private readonly EnsureAreaNodeParentAction $ensureParent,
        private readonly RecordAreaNodeChangeAction $recordChange,
    ) {}

    public function handle(User $actor, Branch $branch, AreaNode $areaNode, ?int $expectedVersion = null): void
    {
        DB::transaction(function () use ($actor, $branch, $areaNode, $expectedVersion): void {
            $context = $this->prepare->handle($branch->id, $actor, 'restore', $areaNode->id, $expectedVersion);
            $area = $context['area'];
            assert($area instanceof AreaNode);
            $this->ensureParent->handle($context['branch'], $area->parent_id, $area->id, withDescendants: true);
            $before = $area->only(['parent_id', 'deleted_at', 'structure_version']);
            if (! $area->restore()) {
                throw new RuntimeException('Required area restoration was rejected.');
            }
            $this->recordChange->handle($context['actor'], $context['branch'], $area, 'restore', $before);
        }, attempts: 3);
    }
}
