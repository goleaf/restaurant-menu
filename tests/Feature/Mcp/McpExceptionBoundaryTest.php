<?php

declare(strict_types=1);

use App\Mcp\McpResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;

test('MCP response boundary reports only a safe exception without its original payload', function (bool $debug): void {
    config(['app.debug' => $debug]);
    Exceptions::fake();
    $private = new RuntimeException('fixture-private-credential-message');
    $response = app(McpResponse::class)->run(function () use ($private): never {
        throw $private;
    });

    expect((string) $response->content())->not->toContain($private->getMessage())
        ->toBe(__('mcp.errors.operation_failed'));
    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (RuntimeException $reported): bool => $reported !== $private
        && $reported->getMessage() === 'Restaurant MCP operation failed.' && $reported->getPrevious() === null);
})->with([true, false]);

test('MCP response boundary returns safe validation and access failures without reporting them', function (Throwable $exception, string $key): void {
    Exceptions::fake();
    $response = app(McpResponse::class)->run(function () use ($exception): never {
        throw $exception;
    });
    expect((string) $response->content())->toBe(__($key));
    Exceptions::assertNothingReported();
})->with([
    'access' => [new AuthorizationException('private denied detail'), 'mcp.errors.request_denied'],
    'validation' => [fn () => ValidationException::withMessages(['input' => 'private invalid detail']), 'mcp.errors.invalid_arguments'],
]);

test('MCP discovery failures hide a tool and never expose the original exception', function (): void {
    Exceptions::fake();
    expect(app(McpResponse::class)->available(function (): never {
        throw new RuntimeException('fixture-private-discovery');
    }))->toBeFalse();
    Exceptions::assertReported(fn (RuntimeException $reported): bool => $reported->getMessage() === 'Restaurant MCP operation failed.');
});
