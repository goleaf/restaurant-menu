<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Enums\McpAbility;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Resource;

#[Cacheable(ttlMs: 0)]
final class BranchContextResource extends Resource
{
    use AuthorizesRestaurantContext;

    protected string $uri = 'restaurant://branch/context';

    protected string $mimeType = 'application/json';

    public function title(): string
    {
        return __('mcp.resources.context_title');
    }

    public function description(): string
    {
        return __('mcp.resources.context_description');
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->responses->respond(function () use ($request): Response {
            $context = $this->resourceContext($request);

            return Response::text(json_encode(
                $this->queries->handle($context, McpAbility::BranchContext, []),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        });
    }
}
