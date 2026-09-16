<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Resource;

#[Cacheable(ttlMs: 60000)]
final class OperationsGuideResource extends Resource
{
    use AuthorizesRestaurantContext;

    protected string $uri = 'restaurant://operations/guide';

    public function title(): string
    {
        return __('mcp.resources.guide_title');
    }

    public function description(): string
    {
        return __('mcp.resources.guide_description');
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->responses->respond(function () use ($request): Response {
            $this->resourceContext($request);

            return Response::text(__('mcp.guide.instructions'));
        });
    }
}
