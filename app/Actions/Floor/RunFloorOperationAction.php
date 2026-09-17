<?php

declare(strict_types=1);

namespace App\Actions\Floor;

use App\Models\Branch;
use App\Models\FloorOperation;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RunFloorOperationAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  Closure(User, Branch): mixed  $authorize
     * @param  Closure(User, Branch): array<string, mixed>  $apply
     * @return array<string, mixed>
     */
    public function handle(User $actor, Branch $branch, string $requestId, string $kind, ?int $targetId, array $payload, Closure $authorize, Closure $apply): array
    {
        Validator::make(['requestId' => $requestId], ['requestId' => ['required', 'uuid']], attributes: ['requestId' => __('floor.fields.request')])->validate();
        $payloadHash = hash('sha256', json_encode([$kind, $targetId, $payload], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $branch, $kind, $targetId, $requestId, $payloadHash, $authorize, $apply): array {
            $currentActor = User::query()->whereKey($actor->id)->firstOrFail();
            $currentBranch = Branch::query()->whereKey($branch->id)->where('organization_id', $branch->organization_id)->where('brand_id', $branch->brand_id)->firstOrFail();
            $authorize($currentActor, $currentBranch);
            $receipt = FloorOperation::query()->where('request_id', $requestId)->first();
            if ($receipt !== null) {
                if ($receipt->branch_id !== $currentBranch->id || $receipt->actor_user_id !== $currentActor->id
                    || ! hash_equals($receipt->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['requestId' => __('floor.errors.command_conflict')]);
                }

                return $receipt->result;
            }

            $result = $apply($currentActor, $currentBranch);
            $receipt = new FloorOperation;
            $receipt->forceFill([
                'request_id' => $requestId, 'branch_id' => $currentBranch->id, 'actor_user_id' => $currentActor->id,
                'kind' => $kind, 'target_id' => $targetId, 'payload_hash' => $payloadHash, 'result' => $result,
            ]);
            if (! $receipt->save()) {
                throw new RuntimeException('Floor operation receipt could not be stored.');
            }

            return $result;
        }, 3);
    }
}
