<?php

declare(strict_types=1);

use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\McpAbility;
use App\Enums\SystemRole;
use App\Mcp\McpContext;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\OrganizationUser;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    config(['restaurant-mcp.enabled' => true, 'restaurant-mcp.writes_enabled' => true]);
    $this->branch = Branch::factory()->create();
    KitchenDepartment::factory()->for($this->branch)->create(['type' => KitchenDepartmentType::Kitchen]);
    $this->user = User::factory()->create(['locale' => 'ru']);
    OrganizationUser::factory()->forOrganization($this->branch->organization)->forUser($this->user)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $this->issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Catalog fixture',
        array_map(fn (McpAbility $ability): string => $ability->value, McpAbility::cases()), 24);
    $this->meta = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => new stdClass];
    $this->call = function (string $name, array $arguments = []) {
        $response = $this->withToken($this->issued->plainTextToken)
            ->withHeaders(['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => $name])
            ->postJson('/mcp/restaurant', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => $arguments, '_meta' => $this->meta]]);
        if ($response->baseResponse instanceof StreamedResponse) {
            $response->assertHeader('Cache-Control', 'no-store, private');
            preg_match_all('/^data: (.+)$/m', $response->streamedContent(), $matches);
            expect($matches[1])->not->toBeEmpty();

            return TestResponse::fromBaseResponse(response(end($matches[1]), $response->getStatusCode(), ['Content-Type' => 'application/json']));
        }

        return $response;
    };
});

test('native tool search exposes nineteen authorized specialist tools with complete schemas', function (): void {
    $response = ($this->call)('search_tools', ['query' => '', 'limit' => 50])->assertOk();
    $catalog = json_decode($response->json('result.content.0.text'), true, flags: JSON_THROW_ON_ERROR);
    expect($catalog['ok'])->toBeTrue()->and($catalog['hasMore'])->toBeFalse()->and($catalog['tools'])->toHaveCount(19);
    $pause = collect($catalog['tools'])->firstWhere('name', 'set_ordering_pause');
    expect($pause['inputSchema']['required'])->toContain('confirmed', 'idempotency_key', 'closed')
        ->and($pause['inputSchema']['additionalProperties'])->toBeFalse()
        ->and($pause['annotations']['idempotentHint'])->toBeTrue();
});

test('native tool search hides disabled mutations and stale abilities', function (): void {
    config(['restaurant-mcp.writes_enabled' => false]);
    $response = ($this->call)('search_tools', ['query' => '', 'limit' => 50])->assertOk();
    $catalog = json_decode($response->json('result.content.0.text'), true, flags: JSON_THROW_ON_ERROR);
    expect($catalog['tools'])->toHaveCount(9);
    $this->issued->record->forceFill(['abilities' => ['branch_context']])->save();
    $response = ($this->call)('search_tools', ['query' => '', 'limit' => 50])->assertOk();
    expect(json_decode($response->json('result.content.0.text'), true)['tools'])->toBeEmpty();
});

test('native execution has bounded batches and preserves earlier committed results on failure', function (): void {
    $key = (string) Str::uuid();
    $call = ['name' => 'set_ordering_pause', 'arguments' => ['closed' => true, 'reason' => 'Break', 'confirmed' => true, 'idempotency_key' => $key]];
    $response = ($this->call)('execute_tools', ['calls' => [$call, ['name' => 'nonexistent'], $call]])->assertOk();
    $result = json_decode($response->json('result.content.0.text'), true, flags: JSON_THROW_ON_ERROR);
    expect($result['ok'])->toBeFalse()->and($result['results'])->toHaveCount(2)
        ->and($result['results'][0]['structuredContent']['paused'])->toBeTrue()
        ->and($this->branch->fresh()->is_temporarily_closed)->toBeTrue();
    $this->assertDatabaseCount('mcp_mutation_receipts', 1);
    $this->branch->forceFill(['is_temporarily_closed' => false])->save();
    ($this->call)('execute_tools', ['calls' => [$call]])->assertOk()->assertJsonPath('result.isError', false);
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    ($this->call)('execute_tools', ['calls' => array_fill(0, 6, $call)])->assertOk()->assertJsonPath('result.isError', true);
});

test('real MCP HTTP execution sanitizes unexpected action failures and restores request context', function (bool $debug): void {
    config(['app.debug' => $debug]);
    Exceptions::fake();
    $browserUser = User::factory()->create();
    $this->actingAs($browserUser, 'web');
    app()->setLocale('lt');
    $mock = Mockery::mock(UpdateBranchTemporaryClosureAction::class);
    $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('fixture-private-credential-message'));
    app()->instance(UpdateBranchTemporaryClosureAction::class, $mock);
    $response = ($this->call)('execute_tools', ['calls' => [[
        'name' => 'set_ordering_pause', 'arguments' => ['closed' => true, 'reason' => 'Break', 'confirmed' => true, 'idempotency_key' => (string) Str::uuid()],
    ]]])->assertOk()->assertJsonPath('result.isError', true);
    expect($response->getContent())->not->toContain('fixture-private-credential-message')
        ->and(Auth::getDefaultDriver())->toBe('web')->and(Auth::guard('web')->id())->toBe($browserUser->id)
        ->and(app()->getLocale())->toBe('lt')->and(request()->attributes->has(McpContext::class))->toBeFalse();
    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (RuntimeException $reported): bool => $reported->getMessage() === 'Restaurant MCP operation failed.' && $reported->getPrevious() === null);
    $this->assertDatabaseCount('mcp_mutation_receipts', 0);
})->with([true, false]);
