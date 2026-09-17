<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Mcp\ExecuteMcpMutationAction;
use App\Actions\Menus\SetMenuItemAvailabilityAction;
use App\Enums\McpAbility;
use App\Mcp\McpAccess;
use App\Mcp\McpContext;
use App\Mcp\McpResponse;
use App\Mcp\McpTargets;
use Illuminate\Auth\Access\Response as AuthorizationResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class SetMenuAvailabilityTool extends RestaurantMutationTool
{
    protected string $name = 'set_menu_availability';

    protected string $description = 'Change availability of one menu item after approval.';

    public function __construct(McpAccess $access, ExecuteMcpMutationAction $execute, McpTargets $targets, McpResponse $responses,
        private readonly SetMenuItemAvailabilityAction $action,
    ) {
        parent::__construct($access, $execute, $targets, $responses);
    }

    protected function ability(): McpAbility
    {
        return McpAbility::SetMenuAvailability;
    }

    protected function rules(): array
    {
        return ['menu_item_id' => ['required', 'integer:strict', 'min:1'], 'is_available' => ['required', 'boolean:strict'],
            'expected_version' => ['required', 'integer:strict', 'min:0'], 'timezone' => ['required', 'string', 'timezone'], 'request_id' => ['required', 'string', 'uuid']];
    }

    protected function authorize(McpContext $context, array $input): AuthorizationResponse
    {
        return Gate::forUser($context->user)->authorize('changeAvailability', $this->targets->menuItem($context, $input['menu_item_id'])->menu);
    }

    protected function perform(McpContext $context, array $input): array
    {
        if ($context->branch->timezone !== $input['timezone']) {
            throw ValidationException::withMessages(['timezone' => __('availability.errors.timezone_changed')]);
        }
        $item = $this->action->handle($context->user, $context->branch, $this->targets->menuItem($context, $input['menu_item_id']), $input['is_available'], $input['expected_version'], $input['request_id']);

        return ['menu_item_id' => $item->id, 'is_available' => $item->is_available, 'availability_version' => $item->availability_version];
    }
}
