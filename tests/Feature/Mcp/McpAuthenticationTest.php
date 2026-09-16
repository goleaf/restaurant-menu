<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    config(['restaurant-mcp.enabled' => true]);
    $this->branch = Branch::factory()->create(['name' => 'MCP authorized branch']);
    $this->user = User::factory()->create(['locale' => 'en']);
    $this->membership = OrganizationUser::factory()->forOrganization($this->branch->organization)
        ->forUser($this->user)->forSystemRole(SystemRole::Owner)->active()->create();
    $this->issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Authentication fixture', ['branch_context'], 24);
    $this->rpc = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'branch_context', 'arguments' => []]];
});

test('MCP transport is disabled by default and refuses a valid token while disabled', function (): void {
    config(['restaurant-mcp.enabled' => false]);
    $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertNotFound();
});

test('MCP authenticates a scoped bearer without a browser session', function (): void {
    $response = $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertOk();
    expect($response->getContent())->toContain('MCP authorized branch')->not->toContain($this->user->email, $this->issued->plainTextToken)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->getCookies())->toBeEmpty();
});

test('MCP rejects browser identity without the bearer', function (): void {
    $this->actingAs($this->user)->postJson('/mcp/restaurant', $this->rpc)->assertUnauthorized();
});

test('MCP rejects missing malformed expired and revoked credentials', function (string $state): void {
    $token = $this->issued->plainTextToken;
    if ($state === 'expired') {
        $this->issued->record->forceFill(['expires_at' => now()->subSecond()])->save();
    } elseif ($state === 'revoked') {
        $this->issued->record->forceFill(['revoked_at' => now()])->save();
    } else {
        $token = $state;
    }
    $this->withToken($token)->postJson('/mcp/restaurant', $this->rpc)->assertUnauthorized()
        ->assertHeader('Cache-Control', 'no-store, private');
})->with(['', 'malformed', 'rm_mcp_'.str_repeat('a', 64), 'expired', 'revoked']);

test('MCP rechecks membership on the next stateless call', function (): void {
    $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertOk();
    $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertForbidden();
});

test('MCP refuses browser origins other than the configured application origin', function (string $origin): void {
    $this->withToken($this->issued->plainTextToken)->withHeader('Origin', $origin)
        ->postJson('/mcp/restaurant', $this->rpc)->assertForbidden();
})->with(['https://attacker.example', 'null', 'https://ruflo.test.attacker.example']);

test('MCP enforces the body limit before processing tool arguments', function (): void {
    $this->rpc['params']['arguments']['padding'] = str_repeat('x', 65537);
    $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertStatus(413);
});

test('MCP refuses ungranted tools and cross-branch input', function (): void {
    $this->rpc['params']['arguments'] = ['branch_id' => Branch::factory()->create()->id];
    $response = $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertOk();
    expect($response->json('result.isError'))->toBeTrue();
});
