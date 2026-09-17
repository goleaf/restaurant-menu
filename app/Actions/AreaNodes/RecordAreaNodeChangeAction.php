<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\User;

final class RecordAreaNodeChangeAction
{
    public function __construct(private readonly RecordAuditLogAction $recordAuditLog) {}

    /** @param array<string,mixed> $before */
    public function handle(User $actor, Branch $branch, AreaNode $area, string $operation, array $before): void
    {
        $this->recordAuditLog->handle(
            action: AuditLogAction::AreaNodeChanged,
            entityType: 'area_node', entityId: $area->id, actorUser: $actor,
            organizationId: $branch->organization_id, branchId: $branch->id,
            oldValues: $before,
            newValues: ['operation' => $operation, ...$area->only(['parent_id', 'name', 'type', 'icon', 'sort_order', 'is_active', 'deleted_at', 'structure_version'])],
        );
    }
}
