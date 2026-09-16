<?php

declare(strict_types=1);

namespace App\Actions\Mcp;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RevokeMcpAccessTokenAction
{
    public function __construct(private readonly RecordAuditLogAction $audit) {}

    public function handle(User $actor, int $tokenId): void
    {
        DB::transaction(function () use ($actor, $tokenId): void {
            $actor = User::query()->whereKey($actor->id)->firstOrFail();
            $token = McpAccessToken::query()->select(['id', 'user_id', 'organization_id', 'branch_id', 'revoked_at'])
                ->whereKey($tokenId)->lockForUpdate()->firstOrFail();
            if ($token->user_id !== $actor->id && ! $actor->isSuperadmin()) {
                throw new AuthorizationException;
            }
            if ($token->revoked_at !== null) {
                return;
            }
            $token->revoked_at = now();
            if (! $token->save()) {
                throw new RuntimeException('MCP credential could not be revoked.');
            }
            $this->audit->handle(AuditLogAction::McpTokenRevoked, 'mcp_access_token', $token->id,
                actorUser: $actor, organizationId: $token->organization_id, branchId: $token->branch_id);
        }, 3);
    }
}
