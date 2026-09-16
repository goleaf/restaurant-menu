<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\McpAbility;
use App\Actions\Mcp\ReadRestaurantMcpAction;
use App\Mcp\McpAccess;
use App\Mcp\McpResponse;
use App\Services\Mcp\McpReadQueries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class RestaurantReadTool extends Tool
{
    public function __construct(
        private readonly McpAccess $access,
        private readonly McpReadQueries $queries,
        private readonly McpResponse $responses,
        private readonly ReadRestaurantMcpAction $read,
    ) {}

    abstract protected function ability(): McpAbility;

    public function shouldRegister(Request $request): bool
    {
        return $this->responses->available(function (): bool {
            $context = $this->access->context($this->ability());
            $this->queries->authorize($context, $this->ability());

            return true;
        });
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        return $this->responses->run(function () use ($request): array {
            $rules = $this->rules();
            if (array_diff(array_keys($request->all()), array_keys($rules)) !== []) {
                throw ValidationException::withMessages(['arguments' => __('mcp.errors.invalid_arguments')]);
            }

            return $this->read->handle($this->ability(), $request->validate($rules));
        });
    }

    /** @return array<string, mixed> */
    public function schema(JsonSchema $schema): array
    {
        $properties = [];
        foreach (array_keys($this->rules()) as $key) {
            $properties[$key] = match ($key) {
                'limit' => $schema->integer()->min(1)->max(50)->default(25)->description('Maximum page size; the response includes has_more and next_after_id.'),
                'after_id' => $schema->integer()->min(0)->default(0)->description('Exclusive ascending keyset cursor from the previous response.'),
                'mine_only' => $schema->boolean()->default(true)->description('Use exact assigned waiter areas; no assignments means all areas in this branch.'),
                'period' => $schema->string()->enum(['today', 'yesterday', 'last7', 'custom'])->default('today'),
                'date_from', 'date_to' => $schema->string()->description('Inclusive branch-local YYYY-MM-DD date; required for custom, maximum 31 calendar days.'),
                'table_session_id' => $schema->integer()->min(1)->required(),
                default => $schema->integer()->min(1)->description('Read the child item page of this exact branch-owned record instead of the parent list.'),
            };
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

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        $pagination = ['limit' => ['sometimes', 'integer:strict', 'between:1,50'], 'after_id' => ['sometimes', 'integer:strict', 'min:0']];
        $waiter = ['mine_only' => ['sometimes', 'boolean:strict']];

        return match ($this->ability()) {
            McpAbility::BranchContext => [],
            McpAbility::PaymentSummary => ['table_session_id' => ['required', 'integer:strict', 'min:1']],
            McpAbility::BranchReport => [
                'period' => ['sometimes', 'string', 'in:today,yesterday,last7,custom'],
                'date_from' => ['required_if:period,custom', 'prohibited_unless:period,custom', 'string', 'date_format:Y-m-d'],
                'date_to' => ['required_if:period,custom', 'prohibited_unless:period,custom', 'string', 'date_format:Y-m-d'],
            ],
            McpAbility::ListOrders => [...$pagination, ...$waiter, 'order_id' => ['sometimes', 'integer:strict', 'min:1']],
            McpAbility::ListDrafts => [...$pagination, ...$waiter, 'draft_id' => ['sometimes', 'integer:strict', 'min:1']],
            McpAbility::ListWaiterCalls => [...$pagination, ...$waiter],
            McpAbility::ListDepartmentTickets => [...$pagination, 'ticket_id' => ['sometimes', 'integer:strict', 'min:1']],
            default => $pagination,
        };
    }
}
