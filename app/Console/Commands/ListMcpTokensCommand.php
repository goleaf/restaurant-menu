<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

#[Signature('restaurant:mcp-token:list {user} {--branch=} {--after-id=0} {--limit=25}')]
#[Description('List bounded MCP credential metadata owned by one user, without secrets.')]
final class ListMcpTokensCommand extends Command
{
    use McpCommandInput;

    public function handle(): int
    {
        try {
            $userId = $this->integerInput($this->argument('user'));
            $branchOption = $this->option('branch');
            $branchId = $branchOption === null ? null : $this->integerInput($branchOption);
            $afterId = $this->integerInput($this->option('after-id'), minimum: 0);
            $limit = $this->integerInput($this->option('limit'), maximum: 100);
            User::query()->select(['id'])->whereKey($userId)->firstOrFail();
            $tokens = McpAccessToken::query()->ownerMetadata($userId)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->where('id', '>', $afterId)->orderBy('id')->limit($limit + 1)->get();
        } catch (ValidationException) {
            $this->error(__('mcp.cli.invalid_input'));

            return self::FAILURE;
        } catch (ModelNotFoundException) {
            $this->error(__('mcp.cli.unavailable'));

            return self::FAILURE;
        } catch (Throwable $exception) {
            report(new RuntimeException('MCP token listing failed ('.class_basename($exception).').'));
            $this->error(__('mcp.cli.failed'));

            return self::FAILURE;
        }
        if ($tokens->isEmpty()) {
            $this->info(__('mcp.cli.empty'));

            return self::SUCCESS;
        }
        $page = $tokens->take($limit);
        $this->table([
            __('mcp.cli.id'), __('mcp.cli.name'), __('mcp.cli.branch'), __('mcp.cli.abilities'), __('mcp.cli.expires'), __('mcp.cli.revoked'),
        ], $page->map(fn (McpAccessToken $token): array => [
            $token->id, OutputFormatter::escape($token->name), $token->branch_id,
            implode(', ', $token->abilities), $token->expires_at->toIso8601String(),
            $token->revoked_at?->toIso8601String() ?? '—',
        ])->all());
        if ($tokens->count() > $limit) {
            $this->line(__('mcp.cli.next_page', ['id' => $page->last()->id]));
        }

        return self::SUCCESS;
    }
}
