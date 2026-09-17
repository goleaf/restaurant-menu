<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SetAreaNodeActiveAction
{
    public function __construct(private readonly PrepareAreaNodeMutationAction $prepare, private readonly RecordAreaNodeChangeAction $recordChange) {}

    public function handle(AreaNode $areaNode, bool $isActive, ?User $actor = null, ?int $expectedVersion = null): AreaNode
    {
        return DB::transaction(function () use ($areaNode, $isActive, $actor, $expectedVersion): AreaNode {
            $context = $this->prepare->handle($areaNode->branch_id, $actor, 'update', $areaNode->id, $expectedVersion);
            $area = $context['area'];
            assert($area instanceof AreaNode);
            if ($area->is_active === $isActive) {
                return $area;
            }
            $before = $area->only(['is_active', 'structure_version']);
            if (! $area->fill(['is_active' => $isActive])->save()) {
                throw new RuntimeException('Required area status update was rejected.');
            }
            $this->recordChange->handle($context['actor'], $context['branch'], $area, 'status', $before);

            return $area;
        }, attempts: 3);
    }
}
