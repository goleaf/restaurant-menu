<?php

declare(strict_types=1);

use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\McpAbility;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Models\Branch;
use App\Models\McpMutationReceipt;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    config(['restaurant-mcp.enabled' => true, 'restaurant-mcp.writes_enabled' => true]);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->branch->organization)->forUser($this->user)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $this->issued = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Receipt fixture', ['set_ordering_pause'], 24);
    request()->attributes->set(McpContext::class, app(McpAccess::class)->resolve($this->issued->record->id));
    $this->key = (string) Str::uuid();
    $this->authorize = fn (McpContext $context) => Gate::forUser($context->user)->authorize('manageSettings', $context->branch);
    $this->operation = function (McpContext $context): array {
        $context->branch->forceFill(['is_temporarily_closed' => true])->save();

        return ['branch_id' => $context->branch->id, 'paused' => true];
    };
});

test('MCP mutation receipts return the first result without overwriting a later change', function (): void {
    $action = app(ExecuteMcpMutationAction::class);
    $first = $action->handle(McpAbility::SetOrderingPause, $this->key, ['closed' => true], $this->authorize, $this->operation);
    $this->branch->forceFill(['is_temporarily_closed' => false])->save();
    $again = $action->handle(McpAbility::SetOrderingPause, strtoupper($this->key), ['closed' => true], $this->authorize,
        function (): never {
            throw new RuntimeException('Replay must not execute');
        });

    expect($again)->toBe($first)->and($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    $this->assertDatabaseCount('mcp_mutation_receipts', 1);
});

test('MCP mutation receipts reject changed arguments on an existing request key', function (): void {
    $action = app(ExecuteMcpMutationAction::class);
    $action->handle(McpAbility::SetOrderingPause, $this->key, ['closed' => true], $this->authorize, $this->operation);
    expect(fn () => $action->handle(McpAbility::SetOrderingPause, $this->key, ['closed' => false], $this->authorize, $this->operation))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('mcp_mutation_receipts', 1);
});

test('MCP mutation receipts reauthorize before replay', function (): void {
    $action = app(ExecuteMcpMutationAction::class);
    $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation);
    expect(fn () => $action->handle(McpAbility::SetOrderingPause, $this->key, [],
        function (): never {
            throw new AuthorizationException;
        }, $this->operation))->toThrow(AuthorizationException::class);
});

test('MCP mutation receipts fail closed for a non-throwing authorization denial', function (mixed $decision): void {
    expect(fn () => app(ExecuteMcpMutationAction::class)->handle(McpAbility::SetOrderingPause, $this->key, [],
        fn () => $decision, $this->operation))->toThrow(AuthorizationException::class);
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    $this->assertDatabaseCount('mcp_mutation_receipts', 0);
})->with([false, null, Response::deny()]);

test('MCP mutation receipts refuse a revoked token even for a completed request', function (): void {
    $action = app(ExecuteMcpMutationAction::class);
    $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation);
    $this->issued->record->forceFill(['revoked_at' => now()])->save();
    expect(fn () => $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation))
        ->toThrow(AuthenticationException::class);
});

test('MCP mutation and receipt roll back together on a rejected receipt save', function (): void {
    McpMutationReceipt::saving(static fn (): bool => false);
    try {
        expect(fn () => app(ExecuteMcpMutationAction::class)->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation))
            ->toThrow(RuntimeException::class);
    } finally {
        McpMutationReceipt::flushEventListeners();
    }
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    $this->assertDatabaseCount('mcp_mutation_receipts', 0);
});

test('MCP mutation failure leaves no committed receipt or partial domain state', function (): void {
    expect(fn () => app(ExecuteMcpMutationAction::class)->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize,
        function (McpContext $context): never {
            $context->branch->forceFill(['is_temporarily_closed' => true])->save();
            throw ValidationException::withMessages(['state' => 'Fixture conflict']);
        }))->toThrow(ValidationException::class);
    expect($this->branch->fresh()->is_temporarily_closed)->toBeFalse();
    $this->assertDatabaseCount('mcp_mutation_receipts', 0);
});

test('MCP mutation keys are mandatory UUIDs', function (string $key): void {
    expect(fn () => app(ExecuteMcpMutationAction::class)->handle(McpAbility::SetOrderingPause, $key, [], $this->authorize, $this->operation))
        ->toThrow(ValidationException::class);
})->with(['', 'invalid', '123']);

test('MCP mutation receipts have a valid factory and hide request fingerprints and results', function (): void {
    $receipt = McpMutationReceipt::factory()->create();
    expect($receipt->token->user_id)->toBe($receipt->user_id)
        ->and($receipt->branch->organization_id)->toBe($receipt->organization->id)
        ->and($receipt->toArray())->not->toHaveKeys(['idempotency_key', 'input_hash', 'result']);
});

test('MCP receipt keys are isolated per token', function (): void {
    $action = app(ExecuteMcpMutationAction::class);
    $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation);
    $other = app(IssueMcpAccessTokenAction::class)->handle($this->user, $this->branch, 'Second fixture', ['set_ordering_pause'], 24);
    request()->attributes->set(McpContext::class, app(McpAccess::class)->resolve($other->record->id));
    $second = $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, fn (): array => ['second' => true]);
    expect($second)->toBe(['second' => true]);
    $this->assertDatabaseCount('mcp_mutation_receipts', 2);
});

test('MCP receipt cannot be replayed with another mutation ability', function (): void {
    $this->issued->record->forceFill(['abilities' => ['set_ordering_pause', 'open_table']])->save();
    $action = app(ExecuteMcpMutationAction::class);
    $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation);
    expect(fn () => $action->handle(McpAbility::OpenTable, $this->key, [], $this->authorize, $this->operation))->toThrow(ValidationException::class);
});

test('MCP replay checks current domain permission write gate and token ability', function (string $change): void {
    $action = app(ExecuteMcpMutationAction::class);
    $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation);
    if ($change === 'permission') {
        foreach ([SystemPermission::ManageSettings, SystemPermission::ManageBranches] as $permission) {
            PermissionUserOverride::factory()->forOrganization($this->branch->organization)->create([
                'user_id' => $this->user->id, 'enabled' => false,
                'permission_id' => Permission::query()->where('code', $permission->value)->firstOrFail()->id,
            ]);
        }
    } elseif ($change === 'gate') {
        config(['restaurant-mcp.writes_enabled' => false]);
    } else {
        $this->issued->record->forceFill(['abilities' => ['branch_context']])->save();
    }
    expect(fn () => $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation))->toThrow(AuthorizationException::class);
})->with(['permission', 'gate', 'ability']);

test('MCP receipts reject a corrupted identity association', function (string $field): void {
    $action = app(ExecuteMcpMutationAction::class);
    $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation);
    $foreign = Branch::factory()->create();
    $value = match ($field) {
        'user_id' => User::factory()->create()->id,
        'organization_id' => $foreign->organization_id,
        default => $foreign->id,
    };
    McpMutationReceipt::query()->where('idempotency_key', $this->key)->firstOrFail()->forceFill([$field => $value])->save();
    expect(fn () => $action->handle(McpAbility::SetOrderingPause, $this->key, [], $this->authorize, $this->operation))->toThrow(ValidationException::class);
})->with(['user_id', 'organization_id', 'branch_id']);
