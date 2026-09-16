<?php

declare(strict_types=1);

namespace App\Mcp;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RuntimeException;
use Throwable;

final class McpResponse
{
    /** @param Closure(): array<string, mixed> $operation */
    public function run(Closure $operation): Response|ResponseFactory
    {
        return $this->respond(fn (): ResponseFactory => Response::structured($operation()));
    }

    /** @param Closure(): (Response|ResponseFactory) $operation */
    public function respond(Closure $operation): Response|ResponseFactory
    {
        try {
            return $operation();
        } catch (AuthenticationException|AuthorizationException|ModelNotFoundException) {
            return Response::error(__('mcp.errors.request_denied'));
        } catch (ValidationException) {
            return Response::error(__('mcp.errors.invalid_arguments'));
        } catch (Throwable) {
            $this->reportFailure();

            return Response::error(__('mcp.errors.operation_failed'));
        }
    }

    /** @param Closure(): bool $authorize */
    public function available(Closure $authorize): bool
    {
        try {
            return $authorize();
        } catch (AuthenticationException|AuthorizationException|ModelNotFoundException) {
            return false;
        } catch (Throwable) {
            $this->reportFailure();

            return false;
        }
    }

    private function reportFailure(): void
    {
        report(new RuntimeException('Restaurant MCP operation failed.'));
    }
}
