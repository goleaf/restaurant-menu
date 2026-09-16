<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Waiter\MarkWaiterCallHandledAction;
use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Mcp\McpResponse;
use App\Mcp\McpTargets;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class HandleWaiterCallTool extends RestaurantMutationTool
{
    protected string $name = 'handle_waiter_call';

    protected string $description = 'Mark one waiter call handled after approval.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly MarkWaiterCallHandledAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::HandleWaiterCall;
    }

    protected function rules(): array
    {
        return ['waiter_call_id' => ['required', 'integer:strict', 'min:1']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        $this->targets->waiterCall($context, $input['waiter_call_id']);
        return Gate::forUser($context->user)->authorize('viewAny', [\App\Models\Order::class, $context->branch]);
    }

    protected function perform(McpContext $context, array $input): array
    {
        $call = $this->action->handle($this->targets->waiterCall($context, $input['waiter_call_id']), $context->user);
        return ['waiter_call_id' => $call->id, 'status' => $call->status->value];
    }
}
