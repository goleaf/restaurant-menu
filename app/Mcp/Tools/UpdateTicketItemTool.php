<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Kitchen\UpdateKitchenTicketItemStatusAction;
use App\Actions\Bar\UpdateBarTicketItemStatusAction;
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
final class UpdateTicketItemTool extends RestaurantMutationTool
{
    protected string $name = 'update_ticket_item';

    protected string $description = 'Move a kitchen or bar ticket item forward to an approved status.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly UpdateKitchenTicketItemStatusAction $kitchen,
        private readonly UpdateBarTicketItemStatusAction $bar,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::UpdateTicketItem;
    }

    protected function rules(): array
    {
        return ['ticket_item_id' => ['required', 'integer:strict', 'min:1'], 'status' => ['required', 'string', 'in:accepted,in_progress,ready,cancelled']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('updateStatus', $this->targets->ticketItem($context, $input['ticket_item_id'])->kitchenTicket);
    }

    protected function perform(McpContext $context, array $input): array
    {
        $target = $this->targets->ticketItem($context, $input['ticket_item_id']);
        $action = $target->kitchenTicket->kitchenDepartment->type === \App\Enums\KitchenDepartmentType::Bar ? $this->bar : $this->kitchen;
        $item = $action->handle($target->id, \App\Enums\KitchenTicketItemStatus::from($input['status']), $context->user);
        return ['ticket_item_id' => $item->id, 'status' => $item->status->value];
    }
}
