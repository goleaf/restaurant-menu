<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Mcp\IssueMcpAccessTokenAction;
use App\Enums\McpAbility;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

#[Signature('restaurant:mcp-token:issue {user} {branch} {--name=Local assistant} {--ability=*} {--hours=24} {--write}')]
#[Description('Issue one expiring branch-scoped MCP credential; plaintext is shown once.')]
final class IssueMcpTokenCommand extends Command
{
    use McpCommandInput;

    public function handle(IssueMcpAccessTokenAction $issue): int
    {
        try {
            $userId = $this->integerInput($this->argument('user'));
            $branchId = $this->integerInput($this->argument('branch'));
            $hours = $this->integerInput($this->option('hours'), maximum: 720);
            $name = $this->option('name');
            $abilities = $this->option('ability');
            $values = Validator::make([
                'name' => is_string($name) ? trim($name) : $name,
                'abilities' => $abilities === [] ? McpAbility::readOnly() : $abilities,
            ], [
                'name' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
                'abilities' => ['required', 'array', 'list', 'min:1', 'max:20'],
                'abilities.*' => ['required', 'string', 'distinct', Rule::enum(McpAbility::class)],
            ])->validate();
            foreach ($values['abilities'] as $ability) {
                if (McpAbility::from($ability)->isMutation() && $this->option('write') !== true) {
                    $this->error(__('mcp.cli.write_required'));

                    return self::FAILURE;
                }
            }
            $user = User::query()->select(['id'])->whereKey($userId)->firstOrFail();
            $branch = Branch::query()->select(['id', 'organization_id'])->whereKey($branchId)->firstOrFail();
            $issued = $issue->handle($user, $branch, $values['name'], $values['abilities'], $hours);
        } catch (ValidationException) {
            $this->error(__('mcp.cli.invalid_input'));

            return self::FAILURE;
        } catch (AuthorizationException|ModelNotFoundException) {
            $this->error(__('mcp.cli.unavailable'));

            return self::FAILURE;
        } catch (Throwable $exception) {
            report(new RuntimeException('MCP token issuance failed ('.class_basename($exception).').'));
            $this->error(__('mcp.cli.failed'));

            return self::FAILURE;
        }

        $this->line(OutputFormatter::escape($issued->record->name));
        $this->line($issued->record->expires_at->toIso8601String());
        $this->warn(__('mcp.cli.keep_secret'));
        $this->line($issued->plainTextToken);

        return self::SUCCESS;
    }
}
