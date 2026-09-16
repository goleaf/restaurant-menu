<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Mcp\RevokeMcpAccessTokenAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

#[Signature('restaurant:mcp-token:revoke {user} {token}')]
#[Description('Revoke an owned MCP credential; repeating revocation is safe.')]
final class RevokeMcpTokenCommand extends Command
{
    use McpCommandInput;

    public function handle(RevokeMcpAccessTokenAction $revoke): int
    {
        try {
            $userId = $this->integerInput($this->argument('user'));
            $tokenId = $this->integerInput($this->argument('token'));
            $user = User::query()->select(['id'])->whereKey($userId)->firstOrFail();
            $revoked = $revoke->handle($user, $tokenId);
        } catch (ValidationException) {
            $this->error(__('mcp.cli.invalid_input'));

            return self::FAILURE;
        } catch (AuthorizationException|ModelNotFoundException) {
            $this->error(__('mcp.cli.unavailable'));

            return self::FAILURE;
        } catch (Throwable $exception) {
            report(new RuntimeException('MCP token revocation failed ('.class_basename($exception).').'));
            $this->error(__('mcp.cli.failed'));

            return self::FAILURE;
        }
        $this->info(__($revoked ? 'mcp.cli.revoked_success' : 'mcp.cli.already_revoked'));

        return self::SUCCESS;
    }
}
