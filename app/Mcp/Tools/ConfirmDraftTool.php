<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
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
final class ConfirmDraftTool extends RestaurantMutationTool
{
    protected string $name = 'confirm_draft';

    protected string $description = 'Confirm a submitted draft and dispatch its order using the existing waiter workflow.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly ConfirmDraftOrderByWaiterAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::ConfirmDraft;
    }

    protected function rules(): array
    {
        return ['draft_id' => ['required', 'integer:strict', 'min:1']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('confirm', $this->targets->draft($context, $input['draft_id']));
    }

    protected function perform(McpContext $context, array $input): array
    {
        $order = $this->action->handle($this->targets->draft($context, $input['draft_id']), $context->user);
        return ['order_id' => $order->id, 'draft_id' => $input['draft_id'], 'status' => $order->status->value, 'total_cents' => $order->total_price_cents, 'currency' => $order->currency];
    }
}
