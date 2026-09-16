<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\McpAbility;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\McpAccessToken;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
    $this->membership = OrganizationUser::factory()
        ->forOrganization($this->branch->organization)
        ->forUser($this->user)->forSystemRole(SystemRole::Owner)->active()->create();
});

test('MCP CLI issues a read-only credential once with bounded defaults and safe metadata', function (): void {
    $this->freezeTime();

    expect(Artisan::call('restaurant:mcp-token:issue', [
        'user' => $this->user->id, 'branch' => $this->branch->id,
    ]))->toBe(0);

    $output = Artisan::output();
    $token = McpAccessToken::query()->sole();
    preg_match_all('/rm_mcp_[a-f0-9]{64}/', $output, $matches);

    expect($matches[0])->toHaveCount(1)
        ->and($token->token_hash)->toBe(hash('sha256', $matches[0][0]))
        ->and($token->name)->toBe('Local assistant')
        ->and($token->abilities)->toBe(McpAbility::readOnly())
        ->and($token->expires_at->equalTo(now()->addDay()))->toBeTrue()
        ->and($output)->toContain(__('mcp.cli.keep_secret'), $token->name, $token->expires_at->toIso8601String())
        ->not->toContain($token->token_hash);
});

test('MCP CLI requires explicit opt-in before issuing mutation capabilities', function (): void {
    $input = ['user' => $this->user->id, 'branch' => $this->branch->id, '--ability' => ['set_ordering_pause']];

    expect(Artisan::call('restaurant:mcp-token:issue', $input))->toBe(1)
        ->and(Artisan::output())->toContain(__('mcp.cli.write_required'))->not->toContain('rm_mcp_')
        ->and(McpAccessToken::query()->count())->toBe(0)
        ->and(Artisan::call('restaurant:mcp-token:issue', [...$input, '--write' => true, '--hours' => '2']))->toBe(0)
        ->and(McpAccessToken::query()->sole()->abilities)->toBe(['set_ordering_pause']);
});

test('MCP CLI write opt-in alone does not expand default capabilities', function (): void {
    expect(Artisan::call('restaurant:mcp-token:issue', [
        'user' => $this->user->id, 'branch' => $this->branch->id, '--write' => true,
    ]))->toBe(0)
        ->and(McpAccessToken::query()->sole()->abilities)->toBe(McpAbility::readOnly());
});

test('MCP CLI validates original issue inputs without coercion', function (string $field, mixed $value): void {
    expect(Artisan::call('restaurant:mcp-token:issue', [
        'user' => $this->user->id, 'branch' => $this->branch->id, $field => $value,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain(__('mcp.cli.invalid_input'))->not->toContain('rm_mcp_')
        ->and(McpAccessToken::query()->count())->toBe(0);
})->with([
    'malformed user' => ['user', '1wrong'],
    'fractional user' => ['user', '1.5'],
    'boolean user' => ['user', true],
    'array user' => ['user', ['1']],
    'malformed branch' => ['branch', '1wrong'],
    'zero branch' => ['branch', '0'],
    'overflow branch' => ['branch', '999999999999999999999999'],
    'malformed hours' => ['--hours', '24wrong'],
    'fractional hours' => ['--hours', '1.5'],
    'zero hours' => ['--hours', '0'],
    'unbounded hours' => ['--hours', '721'],
    'boolean hours' => ['--hours', true],
    'unknown ability' => ['--ability', ['run_sql']],
    'wildcard ability' => ['--ability', ['*']],
    'duplicate abilities' => ['--ability', ['branch_context', 'branch_context']],
    'empty ability' => ['--ability', ['']],
    'empty name' => ['--name', '  '],
    'long name' => ['--name', str_repeat('a', 101)],
]);

test('MCP CLI hides denied and missing issue targets without issuing a credential', function (string $target): void {
    $branch = match ($target) {
        'foreign' => Branch::factory()->create()->id,
        'missing' => 999999,
        default => $this->branch->id,
    };

    if ($target === 'suspended') {
        $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    }

    expect(Artisan::call('restaurant:mcp-token:issue', [
        'user' => $target === 'missing user' ? 999999 : $this->user->id, 'branch' => $branch,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain(__('mcp.cli.unavailable'))->not->toContain('rm_mcp_')
        ->and(McpAccessToken::query()->count())->toBe(0);
})->with(['foreign', 'missing', 'missing user', 'suspended']);

test('MCP CLI reports unexpected issue failure without printing or reporting sensitive exception text', function (): void {
    Exceptions::fake();
    AuditLog::creating(function (): never {
        throw new RuntimeException('private fixture credential');
    });

    expect(Artisan::call('restaurant:mcp-token:issue', [
        'user' => $this->user->id, 'branch' => $this->branch->id,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain(__('mcp.cli.failed'))->not->toContain('private fixture credential', 'rm_mcp_')
        ->and(McpAccessToken::query()->count())->toBe(0);

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'MCP token issuance failed (RuntimeException).'
        && $exception->getPrevious() === null);
    Exceptions::assertReportedCount(1);
});

test('MCP CLI lists only bounded owner metadata with keyset continuation', function (): void {
    $tokens = McpAccessToken::factory()->count(3)->for($this->user)->for($this->branch)
        ->sequence(['name' => 'First client'], ['name' => 'Second client'], ['name' => 'Third client'])->create();
    $foreign = McpAccessToken::factory()->create(['name' => 'Foreign client']);

    $queries = countDatabaseQueries(function () use ($tokens): void {
        expect(Artisan::call('restaurant:mcp-token:list', ['user' => $this->user->id, '--limit' => '2']))->toBe(0)
            ->and(Artisan::output())->toContain('First client', 'Second client', __('mcp.cli.next_page', ['id' => $tokens[1]->id]))
            ->not->toContain('Third client', 'Foreign client', 'token_hash', 'rm_mcp_');
    });

    expect($queries)->toBeLessThanOrEqual(2)
        ->and(Artisan::output())->not->toContain(...$tokens->pluck('token_hash')->all())
        ->not->toContain($foreign->token_hash)
        ->and(Artisan::call('restaurant:mcp-token:list', [
            'user' => $this->user->id, '--limit' => '2', '--after-id' => (string) $tokens[1]->id,
        ]))->toBe(0)
        ->and(Artisan::output())->toContain('Third client')->not->toContain('First client', 'Second client', 'Foreign client');
});

test('MCP CLI list columns include expired and revoked credentials scoped to the requested branch', function (): void {
    $expired = McpAccessToken::factory()->for($this->user)->for($this->branch)->expired()->create(['name' => 'Expired client']);
    $revoked = McpAccessToken::factory()->for($this->user)->for($this->branch)->revoked()->create(['name' => 'Revoked client']);
    McpAccessToken::factory()->for($this->user)->create(['name' => 'Other branch client']);

    $this->artisan('restaurant:mcp-token:list', ['user' => $this->user->id, '--branch' => (string) $this->branch->id])
        ->expectsTable([
            __('mcp.cli.id'), __('mcp.cli.name'), __('mcp.cli.branch'), __('mcp.cli.abilities'), __('mcp.cli.expires'), __('mcp.cli.revoked'),
        ], [
            [$expired->id, $expired->name, $expired->branch_id, 'branch_context', $expired->expires_at->toIso8601String(), '—'],
            [$revoked->id, $revoked->name, $revoked->branch_id, 'branch_context', $revoked->expires_at->toIso8601String(), $revoked->revoked_at->toIso8601String()],
        ])->assertSuccessful();
});

test('MCP CLI lists an empty page safely', function (): void {
    expect(Artisan::call('restaurant:mcp-token:list', ['user' => $this->user->id]))->toBe(0)
        ->and(Artisan::output())->toContain(__('mcp.cli.empty'));
});

test('MCP CLI rejects invalid listing inputs', function (string $field, mixed $value): void {
    expect(Artisan::call('restaurant:mcp-token:list', ['user' => $this->user->id, $field => $value]))->toBe(1)
        ->and(Artisan::output())->toContain(__('mcp.cli.invalid_input'));
})->with([
    'malformed user' => ['user', '1wrong'],
    'boolean user' => ['user', true],
    'malformed branch' => ['--branch', '1wrong'],
    'empty branch' => ['--branch', ''],
    'invalid cursor' => ['--after-id', '-1'],
    'coerced cursor' => ['--after-id', '1wrong'],
    'zero limit' => ['--limit', '0'],
    'large limit' => ['--limit', '101'],
    'fractional limit' => ['--limit', '1.5'],
]);

test('MCP CLI revokes once and reports replay without another success or audit', function (): void {
    $issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Revoke client', ['branch_context']);
    $input = ['user' => $this->user->id, 'token' => $issued->record->id];
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();

    expect(Artisan::call('restaurant:mcp-token:revoke', $input))->toBe(0)
        ->and(Artisan::output())->toContain(__('mcp.cli.revoked_success'))
        ->not->toContain($issued->plainTextToken, $issued->record->token_hash);
    $revokedAt = $issued->record->fresh()->revoked_at;
    $this->travel(5)->minutes();

    expect(Artisan::call('restaurant:mcp-token:revoke', $input))->toBe(0)
        ->and(Artisan::output())->toContain(__('mcp.cli.already_revoked'))->not->toContain(__('mcp.cli.revoked_success'))
        ->and($issued->record->fresh()->revoked_at->equalTo($revokedAt))->toBeTrue()
        ->and(AuditLog::query()->where('entity_type', 'mcp_access_token')->count())->toBe(2);
});

test('MCP CLI gives the same safe error for foreign and missing revoke targets', function (): void {
    $token = McpAccessToken::factory()->create();

    expect(Artisan::call('restaurant:mcp-token:revoke', ['user' => $this->user->id, 'token' => $token->id]))->toBe(1);
    $deniedOutput = Artisan::output();

    expect(Artisan::call('restaurant:mcp-token:revoke', ['user' => $this->user->id, 'token' => 999999]))->toBe(1)
        ->and(Artisan::output())->toBe($deniedOutput)->toContain(__('mcp.cli.unavailable'))
        ->and($token->fresh()->revoked_at)->toBeNull();
});

test('MCP CLI rejects malformed revoke identifiers', function (string $field, mixed $value): void {
    $token = McpAccessToken::factory()->for($this->user)->for($this->branch)->create();

    expect(Artisan::call('restaurant:mcp-token:revoke', [
        'user' => $this->user->id, 'token' => $token->id, $field => $value,
    ]))->toBe(1)
        ->and(Artisan::output())->toContain(__('mcp.cli.invalid_input'))
        ->and($token->fresh()->revoked_at)->toBeNull();
})->with([
    'malformed user' => ['user', '1wrong'],
    'boolean user' => ['user', true],
    'malformed token' => ['token', '1wrong'],
    'zero token' => ['token', '0'],
    'boolean token' => ['token', true],
]);
