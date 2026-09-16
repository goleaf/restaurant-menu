<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Mcp\McpResponse;
use App\Services\Mcp\McpReadQueries;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

trait AuthorizesRestaurantContext
{
    public function __construct(
        private readonly McpAccess $access,
        private readonly McpReadQueries $queries,
        private readonly McpResponse $responses,
    ) {}

    public function shouldRegister(Request $request): bool
    {
        return $this->responses->available(function (): bool {
            $this->access->context(McpAbility::BranchContext);

            return true;
        });
    }

    private function resourceContext(Request $request): McpContext
    {
        $context = $this->access->context(McpAbility::BranchContext);
        if ($request->all() !== [] || ($request->uri() !== null && $request->uri() !== $this->uri())) {
            throw ValidationException::withMessages(['resource' => __('mcp.errors.invalid_arguments')]);
        }

        return $context;
    }
}
