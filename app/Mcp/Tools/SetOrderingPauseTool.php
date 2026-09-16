<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Dashboard\SaveDashboardOrderingAction;
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
final class SetOrderingPauseTool extends RestaurantMutationTool
{
    protected string $name = 'set_ordering_pause';

    protected string $description = 'Pause or resume ordering in the authorized branch after approval.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly SaveDashboardOrderingAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::SetOrderingPause;
    }

    protected function rules(): array
    {
        return ['closed' => ['required', 'boolean:strict'], 'reason' => ['required_if:closed,true', 'string', 'max:255'], 'until' => ['sometimes', 'string', 'date_format:Y-m-d H:i:s']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('manageSettings', $context->branch);
    }

    protected function perform(McpContext $context, array $input): array
    {
        $branch = $this->action->handle($context->user, $context->branch->id, $input['closed'], $input['reason'] ?? null, $input['until'] ?? null);
        return ['branch_id' => $branch->id, 'paused' => $branch->is_temporarily_closed, 'until' => $branch->temporary_closed_until?->toIso8601String()];
    }
}
