<?php

declare(strict_types=1);

namespace App\Actions\Mcp;

use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Models\McpMutationReceipt;
use App\Support\Orders\IdempotencyKey;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ExecuteMcpMutationAction
{
    public function __construct(private readonly McpAccess $access) {}

    /**
     * @param  array<string, mixed>  $input  Validated normalized arguments in schema order.
     * @param  Closure(McpContext): mixed  $authorize  Current resource authorization; only an allowed AuthorizationResponse is accepted.
     * @param  Closure(McpContext): array<string, mixed>  $operation  Safe, explicitly projected result only.
     * @return array<string, mixed>
     */
    public function handle(McpAbility $ability, string $key, array $input, Closure $authorize, Closure $operation): array
    {
        if (! $ability->isMutation()) {
            throw new AuthorizationException;
        }
        $attempt = IdempotencyKey::from($key, 'idempotency_key');
        if ($attempt === null) {
            throw ValidationException::withMessages(['idempotency_key' => __('errors.types.validation_error.message')]);
        }
        $hash = hash('sha256', json_encode([$ability->value, $input], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($ability, $attempt, $hash, $authorize, $operation): array {
            $context = $this->access->context($ability);
            $decision = $authorize($context);
            if (! $decision instanceof AuthorizationResponse) {
                throw new AuthorizationException;
            }
            $decision->authorize();
            $receipt = McpMutationReceipt::query()
                ->select(['id', 'mcp_access_token_id', 'user_id', 'organization_id', 'branch_id', 'ability', 'input_hash', 'result'])
                ->whereBelongsTo($context->token, 'token')->where('idempotency_key', $attempt->value)->first();
            if ($receipt !== null) {
                if ($receipt->user_id !== $context->user->id || $receipt->branch_id !== $context->branch->id
                    || $receipt->organization_id !== $context->branch->organization_id
                    || $receipt->ability !== $ability || ! hash_equals($receipt->input_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => __('mcp.errors.replay_conflict')]);
                }

                return $receipt->result;
            }
            $result = $operation($context);
            $receipt = new McpMutationReceipt([
                'mcp_access_token_id' => $context->token->id, 'user_id' => $context->user->id,
                'organization_id' => $context->branch->organization_id, 'branch_id' => $context->branch->id,
                'idempotency_key' => $attempt->value, 'ability' => $ability, 'input_hash' => $hash, 'result' => $result,
            ]);
            if (! $receipt->save()) {
                throw new RuntimeException('Unable to persist MCP mutation receipt.');
            }

            return $result;
        }, 3);
    }
}
