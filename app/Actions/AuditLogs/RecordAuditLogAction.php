<?php

namespace App\Actions\AuditLogs;

use App\Enums\AuditLogAction;
use App\Models\AuditLog;
use App\Models\TableSessionGuest;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class RecordAuditLogAction
{
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
        return AuditLog::query()->create([
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'user_id' => $actorUser?->id,
            'guest_id' => $actorGuest?->id,
            'guest_token' => $this->normalizeGuestToken($guestToken ?? $actorGuest?->guest_token),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->normalizeValues($oldValues),
            'new_values' => $this->normalizeValues($newValues),
            'created_at' => now(),
        ]);
    }

    private function normalizeGuestToken(?string $guestToken): ?string
    {
        $normalized = trim((string) $guestToken);

        return $normalized === '' ? null : mb_substr($normalized, 0, 128);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function normalizeValues(array $values): ?array
    {
        if ($values === []) {
            return null;
        }

        return collect($values)
            ->map(fn (mixed $value): mixed => $this->normalizeValue($value))
            ->all();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $nestedValue): mixed => $this->normalizeValue($nestedValue))
                ->all();
        }

        return $value;
    }
}
