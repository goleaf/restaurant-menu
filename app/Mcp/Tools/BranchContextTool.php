<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
final class BranchContextTool extends Tool
{
    protected string $name = 'branch_context';

    protected string $description = 'Read the exact authorized restaurant branch, its timezone and currency. No branch selector is accepted.';

    public function __construct(private readonly McpAccess $access) {}

    public function shouldRegister(Request $request): bool
    {
        return $this->access->listed(McpAbility::BranchContext);
    }

    public function handle(Request $request): Response
    {
        try {
            $context = $this->access->context(McpAbility::BranchContext);
            $request->validate(['branch_id' => ['prohibited']]);

            return Response::structured([
                'branch' => ['id' => $context->branch->id, 'name' => $context->branch->name, 'timezone' => $context->branch->timezone, 'currency' => $context->branch->currency],
                'abilities' => $context->token->abilities,
                'writes_enabled' => (bool) config('restaurant-mcp.writes_enabled'),
            ]);
        } catch (AuthenticationException|AuthorizationException) {
            return Response::error(__('mcp.errors.request_denied'));
        }
    }
}
