<?php

namespace App\Actions\AreaNodes;

use App\Enums\AreaNodeType;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateAreaNodeAction
{
    public function __construct(
        private readonly PrepareAreaNodeMutationAction $prepare,
        private readonly EnsureAreaNodeParentAction $ensureParent,
        private readonly RecordAreaNodeChangeAction $recordChange,
        private readonly ValidateAreaNodeInputAction $validateInput,
    ) {}

    /**
     * @param  array{parent_id: int|null, type: string, name: string, icon: string|null, sort_order: int, is_active: bool}  $data
     */
    public function handle(Branch $branch, array $data, ?User $actor = null): AreaNode
    {
        return DB::transaction(function () use ($branch, $data, $actor): AreaNode {
            $context = $this->prepare->handle($branch->id, $actor, 'create');
            $data = $this->validateInput->handle($data);
            $branch = $context['branch'];
            $this->ensureParent->handle($branch, $data['parent_id']);
            $area = $branch->areaNodes()->make([
                'parent_id' => $data['parent_id'],
                'type' => AreaNodeType::from($data['type']),
                'name' => $data['name'],
                'icon' => $data['icon'],
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
                'metadata' => [],
            ]);
            if (! $area->save()) {
                throw new RuntimeException('Required area creation was rejected.');
            }
            $this->recordChange->handle($context['actor'], $branch, $area, 'create', []);

            return $area;
        }, attempts: 3);
    }
}
