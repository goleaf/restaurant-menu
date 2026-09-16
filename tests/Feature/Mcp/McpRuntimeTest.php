<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\User;
use Composer\InstalledVersions;
use Database\Seeders\SystemPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    config(['restaurant-mcp.enabled' => true]);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->forUser($this->user)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $this->issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Protocol fixture', ['branch_context'], 24);
    $this->meta = [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientCapabilities' => new stdClass,
    ];
});

test('MCP stable runtime is a production dependency with compatible development tooling', function (): void {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require']['laravel/mcp'] ?? null)->toBe('^1.0')
        ->and(collect($lock['packages'])->firstWhere('name', 'laravel/mcp')['version'] ?? null)->toBe('v1.0.0')
        ->and(InstalledVersions::getPrettyVersion('laravel/mcp'))->toBe('v1.0.0')
        ->and(InstalledVersions::getPrettyVersion('laravel/boost'))->toBe('v2.9.0');
});

test('MCP discovers the current protocol without initializing a persistent session', function (): void {
    $response = $this->withToken($this->issued->plainTextToken)
        ->withHeaders(['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'server/discover'])
        ->postJson('/mcp/restaurant', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'server/discover', 'params' => ['_meta' => $this->meta],
        ])->assertOk()->assertJsonPath('result.supportedVersions', ['2026-07-28']);

    expect($response->headers->has('MCP-Session-Id'))->toBeFalse()
        ->and($response->headers->getCookies())->toBeEmpty()
        ->and($response->json('result.cacheScope'))->toBe('private')
        ->and($response->json('result.ttlMs'))->toBe(0);
});

test('MCP retains both supported legacy initialization versions', function (string $version): void {
    $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
        'params' => ['protocolVersion' => $version, 'capabilities' => new stdClass, 'clientInfo' => ['name' => 'Fixture', 'version' => '1']],
    ])->assertOk()->assertJsonPath('result.protocolVersion', $version);
})->with(['2025-11-25', '2025-06-18']);

test('MCP processes a standalone modern tool call without handshake state', function (): void {
    $this->withToken($this->issued->plainTextToken)
        ->withHeaders(['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'branch_context'])
        ->postJson('/mcp/restaurant', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'branch_context', 'arguments' => [], '_meta' => $this->meta],
        ])->assertOk()->assertJsonPath('result.structuredContent.branch.id', $this->branch->id);
});

test('MCP rejects mismatched modern transport headers', function (array $headers): void {
    $this->withToken($this->issued->plainTextToken)->withHeaders($headers)
        ->postJson('/mcp/restaurant', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'branch_context', 'arguments' => [], '_meta' => $this->meta],
        ])->assertBadRequest()->assertJsonPath('error.code', -32020)
        ->assertHeader('Cache-Control', 'no-store, private');
})->with([
    'missing' => [[]],
    'version' => [['MCP-Protocol-Version' => '2025-11-25', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'branch_context']],
    'method' => [['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/list', 'Mcp-Name' => 'branch_context']],
    'name' => [['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'other']],
    'missing-name' => [['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call']],
]);

test('MCP validates modern metadata before running tools', function (): void {
    unset($this->meta['io.modelcontextprotocol/clientCapabilities']);
    $this->withToken($this->issued->plainTextToken)
        ->withHeaders(['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'branch_context'])
        ->postJson('/mcp/restaurant', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'branch_context', 'arguments' => [], '_meta' => $this->meta],
        ])->assertBadRequest()->assertJsonPath('error.code', -32602);
});
