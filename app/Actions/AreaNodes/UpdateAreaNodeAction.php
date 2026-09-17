<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateAreaNodeAction
{
    public function __construct(
        private readonly PrepareAreaNodeMutationAction $prepare,
        private readonly EnsureAreaNodeParentAction $ensureParent,
        private readonly RecordAreaNodeChangeAction $recordChange,
        private readonly ValidateAreaNodeInputAction $validateInput,
    ) {}

    /** @param array{parent_id:int|null,type:string,name:string,icon:string|null,sort_order:int,is_active:bool} $data */
    public function handle(AreaNode $areaNode, array $data, ?User $actor = null, ?int $expectedVersion = null): AreaNode
    {
        return DB::transaction(function () use ($areaNode, $data, $actor, $expectedVersion): AreaNode {
            $context = $this->prepare->handle($areaNode->branch_id, $actor, 'update', $areaNode->id, $expectedVersion);
            $data = $this->validateInput->handle($data);
            $area = $context['area'];
            assert($area instanceof AreaNode);
            $this->ensureParent->handle($context['branch'], $data['parent_id'], $area->id);
            $before = $area->only(['parent_id', 'type', 'name', 'icon', 'sort_order', 'is_active', 'structure_version']);
            $area->fill([...$data, 'type' => AreaNodeType::from($data['type'])]);
            if (! $area->isDirty()) {
                return $area;
            }
            if (! $area->save()) {
                throw new RuntimeException('Required area update was rejected.');
            }
            $this->recordChange->handle($context['actor'], $context['branch'], $area, 'update', $before);

            return $area;
        }, attempts: 3);
    }
}
