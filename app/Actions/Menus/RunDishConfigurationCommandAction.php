<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\MenuOperation;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RunDishConfigurationCommandAction
{
    public function __construct(private readonly RecordAuditLogAction $audit) {}

    /**
     * @param  array<string,mixed>  $payload
     * @param  Closure(User,Branch):array<string,mixed>  $apply
     * @return array<string,mixed>
     */
    public function handle(User $actor, Branch $branch, MenuOperationKind $kind, int $targetId, array $payload, ?string $requestId, Closure $apply): array
    {
        if ($requestId !== null && ! Str::isUuid($requestId)) {
            throw ValidationException::withMessages(['operation' => __('menu.operations.errors.invalid_request')]);
        }

        return DB::transaction(function () use ($actor, $branch, $kind, $targetId, $payload, $requestId, $apply): array {
            $actor = $actor->fresh(['roles']);
            $branch = Branch::query()->whereKey($branch->id)->where('organization_id', $branch->organization_id)
                ->where('brand_id', $branch->brand_id)->first();
            if (! $actor instanceof User || ! $branch instanceof Branch) {
                throw new AuthorizationException;
            }
            Gate::forUser($actor)->authorize('manageMenu', $branch);
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $receipt = $requestId === null ? null : MenuOperation::query()->where('request_id', $requestId)->first();
            if ($receipt instanceof MenuOperation) {
                if ($receipt->actor_user_id !== $actor->id || $receipt->branch_id !== $branch->id || $receipt->kind !== $kind
                    || $receipt->target_id !== $targetId || ($receipt->payload['input_hash'] ?? null) !== $hash
                    || $receipt->phase !== MenuOperationPhase::Completed) {
                    throw new AuthorizationException;
                }
                foreach ($receipt->payload['result']['required_abilities'] ?? [] as $ability) {
                    Gate::forUser($actor)->authorize($ability, $branch);
                }

                return $receipt->payload['result'];
            }

            $result = $apply($actor, $branch);
            if (($result['changed'] ?? true) === true) {
                $this->audit->handle(AuditLogAction::DishConfigurationChanged,
                    entityType: $result['entity_type'], entityId: $result['entity_id'], actorUser: $actor,
                    organizationId: $branch->organization_id, branchId: $branch->id,
                    oldValues: $result['before'] ?? [], newValues: ['operation' => $payload['operation'], ...($result['after'] ?? [])]);
            }
            if ($requestId !== null) {
                $receipt = new MenuOperation;
                $receipt->forceFill(['request_id' => $requestId, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id,
                    'kind' => $kind, 'target_id' => $targetId, 'menu_id' => $result['menu_id'] ?? null,
                    'result_id' => $result['item_id'] ?? null, 'phase' => MenuOperationPhase::Completed,
                    'processed_count' => 1, 'payload' => ['input_hash' => $hash, 'result' => $result], 'completed_at' => now()]);
                if ($receipt->save() !== true) {
                    throw new RuntimeException('The dish configuration receipt could not be saved.');
                }
            }

            return $result;
        }, attempts: 3);
    }

    public function assertVersion(int $actual, ?int $expected): void
    {
        if ($expected !== null && ($expected < 0 || $actual !== $expected)) {
            throw ValidationException::withMessages(['configuration' => __('dish.errors.configuration_changed')]);
        }
    }
}
