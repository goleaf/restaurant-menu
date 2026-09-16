<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Actions\Waiter\MarkKitchenTicketItemServedAction;
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
final class ServeTicketItemTool extends RestaurantMutationTool
{
    protected string $name = 'serve_ticket_item';

    protected string $description = 'Mark a ready ticket item served after approval.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly MarkKitchenTicketItemServedAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::ServeTicketItem;
    }

    protected function rules(): array
    {
        return ['ticket_item_id' => ['required', 'integer:strict', 'min:1']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('markServed', $this->targets->ticketItem($context, $input['ticket_item_id'])->kitchenTicket->order);
    }

    protected function perform(McpContext $context, array $input): array
    {
        $item = $this->action->handle($this->targets->ticketItem($context, $input['ticket_item_id']), $context->user);

        return ['ticket_item_id' => $item->id, 'served' => $item->served_at !== null];
    }
}
