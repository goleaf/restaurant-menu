<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Mcp\McpResponse;
use App\Mcp\McpTargets;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class RestaurantMutationTool extends Tool
{
    public function __construct(
        private readonly McpAccess $access,
        private readonly ExecuteMcpMutationAction $execute,
        protected readonly McpTargets $targets,
        private readonly McpResponse $responses,
    ) {}

    abstract protected function ability(): McpAbility;

    /** @return array<string, list<string>> */
    abstract protected function rules(): array;

    /** @param array<string, mixed> $input */
    abstract protected function authorize(McpContext $context, array $input): AuthorizationResponse;

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    abstract protected function perform(McpContext $context, array $input): array;

    public function shouldRegister(Request $request): bool
    {
        return $this->responses->available(fn (): bool => $this->access->listed($this->ability()));
    }

    final public function handle(Request $request): Response|ResponseFactory
    {
        return $this->responses->run(function () use ($request): array {
            $this->access->context($this->ability());
            $rules = ['confirmed' => ['required', 'boolean:strict', 'accepted'],
                'idempotency_key' => ['required', 'string', 'uuid'], ...$this->rules()];
            if (array_diff(array_keys($request->all()), array_keys($rules)) !== []) {
                throw ValidationException::withMessages(['arguments' => __('mcp.errors.invalid_arguments')]);
            }
            $input = $request->validate($rules);
            $key = $input['idempotency_key'];
            unset($input['idempotency_key'], $input['confirmed']);
            ksort($input);

            return $this->execute->handle($this->ability(), $key, $input,
                fn (McpContext $context): AuthorizationResponse => $this->authorize($context, $input),
                fn (McpContext $context): array => $this->perform($context, $input));
        });
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        $properties = [
            'confirmed' => $schema->boolean()->required()->description('Must be true only after the user approves this exact action and arguments.'),
            'idempotency_key' => $schema->string()->required()->description('UUID for one approved intent. Reuse on retries with identical arguments; use a new UUID for a new intent.'),
        ];
        foreach ($this->rules() as $key => $rules) {
            $property = match ($key) {
                'closed', 'is_available' => $schema->boolean(),
                'method' => $schema->string()->enum(['cash', 'card_terminal', 'other']),
                'status' => $schema->string()->enum(['accepted', 'in_progress', 'ready', 'cancelled']),
                'reason', 'note' => $schema->string()->max($this->ability() === McpAbility::SetOrderingPause ? 255 : 500),
                'until' => $schema->string()->description('Optional branch-local YYYY-MM-DD HH:MM:SS reopening time.'),
                'tips_cents' => $schema->integer()->min(0)->max(100000000),
                default => $schema->integer()->min(1),
            };
            $properties[$key] = in_array('required', $rules, true) ? $property->required() : $property;
        }

        return $properties;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $definition = parent::toArray();
        $definition['inputSchema']['additionalProperties'] = false;

        return $definition;
    }
}
