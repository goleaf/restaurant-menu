<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Enums\McpAbility;
use App\Models\Branch;
use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;

final class McpAccess
{
    public function resolve(int $tokenId): McpContext
    {
        $token = McpAccessToken::query()->usable()->whereKey($tokenId)->first();
        if (! $token instanceof McpAccessToken) {
            throw new AuthenticationException;
        }
        $user = User::query()->select(['id', 'locale'])->whereKey($token->user_id)->first();
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'timezone', 'currency', 'is_active', 'is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until', 'deleted_at'])
            ->whereKey($token->branch_id)->where('organization_id', $token->organization_id)->where('is_active', true)
            ->whereHas('organization')->whereHas('brand')->first();
        if (! $user instanceof User || ! $branch instanceof Branch) {
            throw new AuthorizationException;
        }
        Gate::forUser($user)->authorize('view', $branch);

        return new McpContext($token, $user, $branch);
    }

    public function context(McpAbility $ability): McpContext
    {
        $initial = request()->attributes->get(McpContext::class);
        if (! $initial instanceof McpContext || ! config('restaurant-mcp.enabled')) {
            throw new AuthenticationException;
        }
        $context = $this->resolve($initial->token->id);
        if (! in_array($ability->value, $context->token->abilities, true)
            || ($ability->isMutation() && ! config('restaurant-mcp.writes_enabled'))) {
            throw new AuthorizationException;
        }

        return $context;
    }

    public function listed(McpAbility $ability): bool
    {
        $context = request()->attributes->get(McpContext::class);

        return $context instanceof McpContext
            && in_array($ability->value, $context->token->abilities, true)
            && (! $ability->isMutation() || config('restaurant-mcp.writes_enabled'));
    }
}
