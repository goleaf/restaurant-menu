<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Enums\McpAbility;
use App\Models\Branch;
use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
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
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return new McpContext($token, $user, $this->authorizedBranch($user, $token->branch_id, $token->organization_id));
    }

    public function authorizedBranch(User $user, int $branchId, int $organizationId): Branch
    {
        $branch = Branch::query()->select(['id', 'organization_id', 'brand_id', 'name', 'timezone', 'currency', 'is_active', 'is_temporarily_closed', 'temporary_closed_reason', 'temporary_closed_until', 'deleted_at'])
            ->whereKey($branchId)->where('organization_id', $organizationId)->where('is_active', true)
            ->whereHas('organization', fn (Builder $query) => $query->whereNull('organizations.deleted_at'))
            ->whereHas('brand', fn (Builder $query) => $query->whereNull('brands.deleted_at')->where('organization_id', $organizationId))
            ->first();
        if (! $branch instanceof Branch) {
            throw new AuthorizationException;
        }
        Gate::forUser($user)->authorize('view', $branch);

        return $branch;
    }

    public function context(McpAbility $ability): McpContext
    {
        $initial = request()->attributes->get(McpContext::class);
        if (! $initial instanceof McpContext || ! config('restaurant-mcp.enabled')) {
            throw new AuthenticationException;
        }
        $context = $this->resolve($initial->token->id);
        if ($initial->user->id !== $context->user->id
            || $initial->branch->id !== $context->branch->id
            || $initial->token->organization_id !== $context->token->organization_id
            || ! hash_equals($initial->token->token_hash, $context->token->token_hash)
            || ! in_array($ability->value, $context->token->abilities, true)
            || ($ability->isMutation() && ! config('restaurant-mcp.writes_enabled'))) {
            throw new AuthorizationException;
        }

        return $context;
    }

    public function listed(McpAbility $ability): bool
    {
        try {
            $this->context($ability);

            return true;
        } catch (AuthenticationException|AuthorizationException) {
            return false;
        }
    }
}
