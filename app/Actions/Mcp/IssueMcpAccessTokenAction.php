<?php

declare(strict_types=1);

namespace App\Actions\Mcp;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Enums\AuditLogAction;
use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Models\Branch;
use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

final class IssueMcpAccessTokenAction
{
    public function __construct(private readonly RecordAuditLogAction $audit, private readonly McpAccess $access) {}

    /** @param list<string> $abilities */
    public function handle(User $user, Branch $branch, string $name, array $abilities, int $hours = 24): IssuedMcpAccessToken
    {
        $values = Validator::make(['name' => trim($name), 'abilities' => $abilities, 'hours' => $hours], [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
            'abilities' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::enum(McpAbility::class)],
            'hours' => ['required', 'integer', 'between:1,720'],
        ])->validate();

        return DB::transaction(function () use ($user, $branch, $values, $abilities, $hours): IssuedMcpAccessToken {
            $user = User::query()->select(['id', 'locale'])->whereKey($user->id)->firstOrFail();
            $branch = $this->access->authorizedBranch($user, $branch->id, $branch->organization_id);
            $plainTextToken = 'rm_mcp_'.bin2hex(random_bytes(32));
            $token = new McpAccessToken;
            $token->forceFill([
                'user_id' => $user->id, 'organization_id' => $branch->organization_id, 'branch_id' => $branch->id,
                'name' => $values['name'], 'token_hash' => hash('sha256', $plainTextToken),
                'abilities' => $abilities, 'expires_at' => now()->addHours($hours),
            ]);
            if (! $token->save()) {
                throw new RuntimeException('MCP credential could not be saved.');
            }
            $this->audit->handle(AuditLogAction::McpTokenIssued, 'mcp_access_token', $token->id,
                actorUser: $user, organizationId: $branch->organization_id, branchId: $branch->id,
                newValues: ['abilities' => $abilities, 'expires_at' => $token->expires_at]);

            return new IssuedMcpAccessToken($token, $plainTextToken);
        }, 3);
    }
}
