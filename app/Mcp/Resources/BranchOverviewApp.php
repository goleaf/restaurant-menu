<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Enums\McpAbility;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\AppMeta;
use Laravel\Mcp\Server\Attributes\Cacheable;

#[AppMeta(prefersBorder: true)]
#[Cacheable(ttlMs: 0)]
final class BranchOverviewApp extends AppResource
{
    use AuthorizesRestaurantContext;

    protected string $uri = 'ui://restaurant/branch-overview';

    public function title(): string
    {
        return __('mcp.resources.overview_title');
    }

    public function description(): string
    {
        return __('mcp.resources.overview_description');
    }

    /** @return array<string, mixed> */
    public function resolvedAppMeta(): array
    {
        return [...parent::resolvedAppMeta(), 'csp' => [
            'connectDomains' => [], 'resourceDomains' => [], 'frameDomains' => [], 'baseUriDomains' => [],
        ]];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->responses->respond(function () use ($request): Response {
            $context = $this->resourceContext($request);
            $data = $this->queries->handle($context, McpAbility::BranchContext, []);

            return Response::view('mcp.branch-overview', [
                'branch' => $data['branch'], 'locale' => $context->user->preferredLocale(),
            ]);
        });
    }
}
