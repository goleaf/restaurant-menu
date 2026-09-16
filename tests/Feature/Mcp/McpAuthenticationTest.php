<?php

declare(strict_types=1);

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\McpAbility;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Http\Middleware\AuthenticateRestaurantMcp;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Symfony\Component\HttpFoundation\Response;

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

test('MCP rejects ambiguous authorization headers instead of searching them for a bearer', function (string $header): void {
    $this->withHeader('Authorization', str_replace('{token}', $this->issued->plainTextToken, $header))
        ->postJson('/mcp/restaurant', $this->rpc)->assertUnauthorized();
})->with(['Basic invalid, Bearer {token}', 'Bearer invalid, Bearer {token}', 'Prefix Bearer {token}', 'Bearer {token}, extra']);

test('MCP rejects archived parents even for a superadmin', function (string $target, bool $superadmin): void {
    if ($superadmin) {
        $role = Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail();
        $this->user->roles()->attach($role);
    }
    $this->branch->{$target}->delete();

    $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertForbidden();
})->with(['organization', 'brand'])->with([false, true]);

test('MCP catalogue reauthorizes stale contexts against current access and configuration', function (string $change): void {
    $access = app(McpAccess::class);
    $context = $access->resolve($this->issued->record->id);
    request()->attributes->set(McpContext::class, $context);

    match ($change) {
        'revoked' => $this->issued->record->forceFill(['revoked_at' => now()])->save(),
        'expired' => $this->issued->record->forceFill(['expires_at' => now()])->save(),
        'capability' => $this->issued->record->forceFill(['abilities' => []])->save(),
        'suspended' => $this->membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save(),
        'archived' => $this->branch->brand->delete(),
        'disabled' => config(['restaurant-mcp.enabled' => false]),
        'inactive' => $this->branch->forceFill(['is_active' => false])->save(),
    };

    expect($access->listed(McpAbility::BranchContext))->toBeFalse();
})->with(['revoked', 'expired', 'capability', 'suspended', 'archived', 'disabled', 'inactive']);

test('MCP refuses a token whose persisted branch binding changed after context creation', function (): void {
    $access = app(McpAccess::class);
    request()->attributes->set(McpContext::class, $access->resolve($this->issued->record->id));
    $other = Branch::factory()->for($this->branch->brand)->create(['organization_id' => $this->branch->organization_id]);
    $this->issued->record->forceFill(['branch_id' => $other->id])->save();

    expect(fn () => $access->context(McpAbility::BranchContext))
        ->toThrow(AuthorizationException::class);
});

test('MCP middleware restores the prior browser guard and locale after a scoped call', function (): void {
    $browserUser = User::factory()->create();
    $this->actingAs($browserUser);
    app()->setLocale('lt');
    $request = Request::create('/mcp/restaurant', 'POST', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$this->issued->plainTextToken,
    ]);
    app()->instance('request', $request);

    $response = app(AuthenticateRestaurantMcp::class)->handle($request, function (): Response {
        expect(auth()->id())->toBe($this->user->id)->and(app()->getLocale())->toBe('en');

        return response()->json(['ok' => true]);
    });

    expect($response->getStatusCode())->toBe(200)
        ->and(auth()->getDefaultDriver())->toBe('web')
        ->and(auth()->id())->toBe($browserUser->id)
        ->and(app()->getLocale())->toBe('lt')
        ->and($request->attributes->has(McpContext::class))->toBeFalse();
});

test('MCP authentication throttles invalid credentials independently of browser sessions', function (): void {
    config(['restaurant-mcp.requests_per_minute' => 2]);
    $this->withHeader('REMOTE_ADDR', '198.51.100.77');
    $this->withToken('invalid')->postJson('/mcp/restaurant', $this->rpc)->assertUnauthorized();
    $this->withToken('invalid')->postJson('/mcp/restaurant', $this->rpc)->assertUnauthorized();
    $this->withToken('invalid')->postJson('/mcp/restaurant', $this->rpc)->assertTooManyRequests()
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Retry-After');
});

test('MCP denies inactive subscriptions and suspended branch assignments on current calls', function (string $change): void {
    if ($change === 'subscription') {
        OrganizationSubscription::factory()->for($this->branch->organization)->inactive()->create();
    } else {
        BranchUser::factory()->forBranch($this->branch)->forUser($this->user)->suspended()->create();
    }

    $this->withToken($this->issued->plainTextToken)->postJson('/mcp/restaurant', $this->rpc)->assertForbidden();
})->with(['subscription', 'assignment']);

test('MCP rechecks write configuration for a previously authorized context', function (): void {
    $this->issued->record->forceFill(['abilities' => ['set_ordering_pause']])->save();
    $access = app(McpAccess::class);
    request()->attributes->set(McpContext::class, $access->resolve($this->issued->record->id));
    config(['restaurant-mcp.writes_enabled' => true]);
    expect($access->listed(McpAbility::SetOrderingPause))->toBeTrue();
    config(['restaurant-mcp.writes_enabled' => false]);

    expect($access->listed(McpAbility::SetOrderingPause))->toBeFalse()
        ->and(fn () => $access->context(McpAbility::SetOrderingPause))
        ->toThrow(AuthorizationException::class);
});

test('MCP accepts its exact configured origin and rejects cleartext production transport', function (): void {
    config(['app.url' => 'https://restaurant.example:8443/app']);
    $request = Request::create('https://restaurant.example:8443/mcp/restaurant', 'POST', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$this->issued->plainTextToken,
        'HTTP_ORIGIN' => 'https://restaurant.example:8443',
    ]);
    $middleware = app(AuthenticateRestaurantMcp::class);
    $this->app->instance('env', 'production');
    try {
        expect($middleware->handle($request, fn () => response()->json(['ok' => true]))->getStatusCode())->toBe(200);
        $request->server->set('HTTPS', 'off');
        expect($middleware->handle($request, fn () => response()->json(['ok' => true]))->getStatusCode())->toBe(403);
    } finally {
        $this->app->instance('env', 'testing');
    }
});

test('MCP restores context and returns a non-cacheable safe failure when downstream code throws', function (): void {
    Exceptions::fake();
    $browserUser = User::factory()->create();
    $this->actingAs($browserUser);
    app()->setLocale('lt');
    $request = Request::create('/mcp/restaurant', 'POST', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$this->issued->plainTextToken,
    ]);
    app()->instance('request', $request);

    $response = app(AuthenticateRestaurantMcp::class)->handle($request, function (): never {
        throw new RuntimeException('private fixture credential');
    });

    expect($response->getStatusCode())->toBe(500)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->not->toContain('private fixture credential', $this->issued->plainTextToken)
        ->and(auth()->id())->toBe($browserUser->id)
        ->and(app()->getLocale())->toBe('lt')
        ->and($request->attributes->has(McpContext::class))->toBeFalse();
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'MCP request failed (RuntimeException).'
        && $exception->getPrevious() === null);
});

test('MCP rejects credential rotation after context creation', function (): void {
    $access = app(McpAccess::class);
    request()->attributes->set(McpContext::class, $access->resolve($this->issued->record->id));
    $this->issued->record->forceFill(['token_hash' => hash('sha256', 'rotated fixture')])->save();

    expect(fn () => $access->context(McpAbility::BranchContext))
        ->toThrow(AuthorizationException::class);
});

test('MCP rejects duplicate credential and origin headers rather than selecting the first value', function (string $header): void {
    $request = Request::create('/mcp/restaurant', 'POST', server: [
        'HTTP_AUTHORIZATION' => 'Bearer '.$this->issued->plainTextToken,
    ]);
    $request->headers->set($header, $header === 'Authorization'
        ? ['Bearer '.$this->issued->plainTextToken, 'Bearer invalid']
        : [config('app.url'), 'https://attacker.example']);

    $response = app(AuthenticateRestaurantMcp::class)->handle($request, fn () => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe($header === 'Authorization' ? 401 : 403);
})->with(['Authorization', 'Origin']);
