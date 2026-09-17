<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\AreaNodeQueryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DeleteAreaNodeAction
{
    public function __construct(
        private readonly PrepareAreaNodeMutationAction $prepare,
        private readonly EnsureAreaNodeParentAction $ensureParent,
        private readonly RecordAreaNodeChangeAction $recordChange,
        private readonly AreaNodeQueryService $queries,
    ) {}

    public function handle(User $actor, Branch $branch, AreaNode $areaNode, ?int $expectedVersion = null, ?string $expectedPreview = null): void
    {
        DB::transaction(function () use ($actor, $branch, $areaNode, $expectedVersion, $expectedPreview): void {
            $context = $this->prepare->handle($branch->id, $actor, 'delete', $areaNode->id, $expectedVersion);
            $area = $context['area'];
            assert($area instanceof AreaNode);
            $this->ensureParent->handle($context['branch'], $area->parent_id, $area->id);
            $preview = $this->queries->archivePreview($context['branch'], $area);
            if ($expectedPreview !== null && ! hash_equals($preview['fingerprint'], $expectedPreview)) {
                throw ValidationException::withMessages(['structureDeletion' => __('floor.errors.stale')]);
            }
            if ($preview['nonterminal_sessions_count'] > 0) {
                throw BusinessRuleViolation::for(BusinessRuleCode::ServicePointHasActiveSession, 'structureDeletion', __('floor.errors.area_busy'));
            }
            if ($preview['active_orders_count'] > 0) {
                throw BusinessRuleViolation::for(BusinessRuleCode::StructureHasActiveOrder, 'structureDeletion');
            }
            $before = [...$area->only(['parent_id', 'name', 'deleted_at', 'structure_version']), 'children_reparented' => $preview['children_count']];
            foreach ($area->children()->where('branch_id', $branch->id)->reorder()->lazyById(100) as $child) {
                $childBefore = $child->only(['parent_id', 'structure_version']);
                if (! $child->fill(['parent_id' => $area->parent_id])->save()) {
                    throw new RuntimeException('Required child area movement was rejected.');
                }
                $this->recordChange->handle($context['actor'], $context['branch'], $child, 'reparent', $childBefore);
            }
            if (! $area->delete()) {
                throw new RuntimeException('Required area archive was rejected.');
            }
            $this->recordChange->handle($context['actor'], $context['branch'], $area, 'archive', $before);
        }, attempts: 3);
    }
}
