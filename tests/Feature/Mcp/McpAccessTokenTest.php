<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Actions\Mcp\RevokeMcpAccessTokenAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\McpAccessToken;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
    $this->membership = OrganizationUser::factory()
        ->forOrganization($this->branch->organization)
        ->forUser($this->user)->forSystemRole(SystemRole::Owner)->active()->create();
});

test('MCP credentials store only a hidden digest and retain their exact tenant scope', function (): void {
    $issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Local assistant', ['branch_context'], 24);
    $token = $issued->record->fresh();

    expect($issued->plainTextToken)->toMatch('/\Arm_mcp_[a-f0-9]{64}\z/')
        ->and($token->token_hash)->toBe(hash('sha256', $issued->plainTextToken))
        ->and($token->user_id)->toBe($this->user->id)
        ->and($token->branch_id)->toBe($this->branch->id)
        ->and($token->organization_id)->toBe($this->branch->organization_id)
        ->and($token->abilities)->toBe(['branch_context'])
        ->and($token->expires_at->isFuture())->toBeTrue()
        ->and($token->toArray())->not->toHaveKey('token_hash')
        ->and($token->toJson())->not->toContain($issued->plainTextToken)
        ->and(AuditLog::query()->where('entity_type', 'mcp_access_token')->count())->toBe(1)
        ->and(AuditLog::query()->where('entity_type', 'mcp_access_token')->firstOrFail()->toJson())
        ->not->toContain($issued->plainTextToken, $token->token_hash);
});

test('MCP issue rechecks current membership before persisting credentials', function (): void {
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();

    expect(fn () => app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Suspended', ['branch_context'], 24))
        ->toThrow(AuthorizationException::class);
    expect(McpAccessToken::query()->count())->toBe(0);
});

test('MCP credentials cannot be issued for a foreign branch', function (): void {
    $other = Branch::factory()->create();

    expect(fn () => app(IssueMcpAccessTokenAction::class)->handle($this->user, $other, 'Foreign', ['branch_context'], 24))
        ->toThrow(AuthorizationException::class);
    expect(McpAccessToken::query()->count())->toBe(0);
});

test('MCP issue rejects unbounded lifetimes and unknown capabilities', function (array $abilities, int $hours): void {
    expect(fn () => app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Invalid', $abilities, $hours))
        ->toThrow(ValidationException::class);
    expect(McpAccessToken::query()->count())->toBe(0);
})->with([
    'zero lifetime' => [['branch_context'], 0],
    'over thirty days' => [['branch_context'], 721],
    'empty abilities' => [[], 24],
    'wildcard' => [['*'], 24],
    'unknown operation' => [['run_sql'], 24],
]);

test('MCP credential revocation is repeat safe and remains available after suspension', function (): void {
    $issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Revoke', ['branch_context'], 24);
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $revoke = app(RevokeMcpAccessTokenAction::class);
    $revoke->handle($this->user, $issued->record->id);
    $revokedAt = $issued->record->fresh()->revoked_at;
    $this->travel(5)->minutes();
    $revoke->handle($this->user, $issued->record->id);

    expect($issued->record->fresh()->revoked_at->equalTo($revokedAt))->toBeTrue()
        ->and(AuditLog::query()->where('entity_type', 'mcp_access_token')->count())->toBe(2);
});

test('MCP credential revocation rejects a different user', function (): void {
    $issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Owned', ['branch_context'], 24);

    expect(fn () => app(RevokeMcpAccessTokenAction::class)->handle(User::factory()->create(), $issued->record->id))
        ->toThrow(AuthorizationException::class);
    expect($issued->record->fresh()->revoked_at)->toBeNull();
});

test('MCP credential issuance rolls back if its audit cannot be persisted', function (): void {
    AuditLog::creating(fn (): bool => false);

    expect(fn () => app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Atomic', ['branch_context'], 24))
        ->toThrow(RuntimeException::class);
    expect(McpAccessToken::query()->count())->toBe(0);
});

test('MCP credential factory states preserve ownership and expiration semantics', function (): void {
    $token = McpAccessToken::factory()->create();

    expect($token->branch->organization_id)->toBe($token->organization_id)
        ->and($token->user)->toBeInstanceOf(User::class)
        ->and($token->expires_at->isFuture())->toBeTrue()
        ->and(McpAccessToken::factory()->expired()->create()->expires_at->isPast())->toBeTrue()
        ->and(McpAccessToken::factory()->revoked()->create()->revoked_at)->not->toBeNull();
});
