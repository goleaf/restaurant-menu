<?php

namespace App\Actions\AuditLogs;

use App\Actions\AuditLogs\Support\AuditLogValueSanitizer;
use App\Enums\AuditLogAction;
use App\Models\AuditLog;
use App\Models\TableSessionGuest;
use App\Models\User;

class RecordAuditLogAction
{
    public function __construct(
        private readonly AuditLogValueSanitizer $valueSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function handle(
        AuditLogAction $action,
        string $entityType,
        ?int $entityId = null,
        ?User $actorUser = null,
        ?TableSessionGuest $actorGuest = null,
        ?string $guestToken = null,
        ?int $organizationId = null,
        ?int $branchId = null,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        $auditLog = new AuditLog;
        $auditLog->forceFill([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'user_id' => $actorUser?->id,
            'guest_id' => $actorGuest?->id,
            'guest_display_name' => $this->normalizeGuestDisplayName($actorGuest?->guest_name),
            'guest_token' => null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->valueSanitizer->forStorage($oldValues),
            'new_values' => $this->valueSanitizer->forStorage($newValues),
            'created_at' => now(),
        ])->save();

        return $auditLog;
    }

    private function normalizeGuestDisplayName(?string $guestName): ?string
    {
        $normalized = str((string) $guestName)->squish()->toString();

        return $normalized === '' ? null : mb_substr($normalized, 0, 120);
    }
}
