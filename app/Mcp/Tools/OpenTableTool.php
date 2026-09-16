<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\TableSessions\OpenTableSessionForServicePointAction;
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
final class OpenTableTool extends RestaurantMutationTool
{
    protected string $name = 'open_table';

    protected string $description = 'Open a table visit after approval; retries return the original visit.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly OpenTableSessionForServicePointAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::OpenTable;
    }

    protected function rules(): array
    {
        return ['service_point_id' => ['required', 'integer:strict', 'min:1']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('openTable', $this->targets->servicePoint($context, $input['service_point_id']));
    }

    protected function perform(McpContext $context, array $input): array
    {
        $session = $this->action->handle($this->targets->servicePoint($context, $input['service_point_id']), $context->user);
        return ['table_session_id' => $session->id, 'service_point_id' => $session->service_point_id, 'status' => $session->status->value];
    }
}
