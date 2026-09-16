<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
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
final class RejectDraftTool extends RestaurantMutationTool
{
    protected string $name = 'reject_draft';

    protected string $description = 'Reject a submitted draft with an approved reason.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly RejectDraftOrderByWaiterAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::RejectDraft;
    }

    protected function rules(): array
    {
        return ['draft_id' => ['required', 'integer:strict', 'min:1'], 'reason' => ['required', 'string', 'max:500']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('reject', $this->targets->draft($context, $input['draft_id']));
    }

    protected function perform(McpContext $context, array $input): array
    {
        $draft = $this->action->handle($this->targets->draft($context, $input['draft_id']), $context->user, $input['reason']);

        return ['draft_id' => $draft->id, 'status' => $draft->status->value];
    }
}
