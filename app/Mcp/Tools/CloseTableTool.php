<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Actions\TableSessions\CloseTableSessionAction;
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
final class CloseTableTool extends RestaurantMutationTool
{
    protected string $name = 'close_table';

    protected string $description = 'Close a settled or otherwise domain-eligible table visit after approval.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly CloseTableSessionAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::CloseTable;
    }

    protected function rules(): array
    {
        return ['table_session_id' => ['required', 'integer:strict', 'min:1']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('close', $this->targets->session($context, $input['table_session_id']));
    }

    protected function perform(McpContext $context, array $input): array
    {
        $session = $this->action->handle($this->targets->session($context, $input['table_session_id']), $context->user);

        return ['table_session_id' => $session->id, 'status' => $session->status->value];
    }
}
