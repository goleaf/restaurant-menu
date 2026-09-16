<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Mcp\McpResponse;
use App\Services\Mcp\McpReadQueries;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

abstract class RestaurantReviewPrompt extends Prompt
{
    public function __construct(
        private readonly McpAccess $access,
        private readonly McpReadQueries $queries,
        private readonly McpResponse $responses,
    ) {}

    /** @return array<string, McpAbility> */
    abstract protected function focusAbilities(): array;

    abstract protected function instruction(): string;

    abstract protected function focusDescription(): string;

    public function shouldRegister(Request $request): bool
    {
        return $this->responses->available(function (): bool {
            $this->access->context(McpAbility::BranchContext);
            foreach (array_unique($this->focusAbilities(), SORT_REGULAR) as $ability) {
                if ($this->responses->available(function () use ($ability): bool {
                    $context = $this->access->context($ability);
                    $this->queries->authorize($context, $ability);

                    return true;
                })) {
                    return true;
                }
            }

            return false;
        });
    }

    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [new Argument('focus', $this->focusDescription())];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->responses->respond(function () use ($request): ResponseFactory {
            $this->access->context(McpAbility::BranchContext);
            if (array_diff(array_keys($request->all()), ['focus']) !== []) {
                throw ValidationException::withMessages(['arguments' => __('mcp.errors.invalid_arguments')]);
            }
            $abilities = $this->focusAbilities();
            $values = $request->validate(['focus' => ['sometimes', 'required', 'string', 'max:20', Rule::in(array_keys($abilities))]]);
            $focus = $values['focus'] ?? 'overview';
            $ability = $abilities[$focus];
            $context = $this->access->context($ability);
            $this->queries->authorize($context, $ability);
            $data = $this->queries->handle($context, McpAbility::BranchContext, []);

            return Response::make([
                Response::text($this->instruction()."\n\n".__('mcp.guide.instructions')),
                Response::text(json_encode(
                    ['focus' => $focus, ...$data],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )),
            ]);
        });
    }
}
