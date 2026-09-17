<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Menus\RunDishConfigurationCommandAction;
use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\ModifierOption;
use App\Models\User;
use App\Services\Menus\DishConfigurationData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class DeleteModifierOptionAction
{
    public function __construct(private readonly RunDishConfigurationCommandAction $commands, private readonly DishConfigurationData $queries) {}

    public function handle(User $actor, Branch $branch, ModifierOption|int $option, ?int $expectedVersion = null, ?string $requestId = null, ?int $expectedGroupId = null): void
    {
        $optionId = $option instanceof ModifierOption ? $option->id : $option;
        $expectedGroupId ??= $option instanceof ModifierOption ? $option->modifier_group_id : null;
        if ($expectedGroupId === null) {
            throw new AuthorizationException;
        }
        $this->commands->handle($actor, $branch, MenuOperationKind::ModifierChange, $optionId,
            ['operation' => 'delete_option', 'group_id' => $expectedGroupId, 'expected_version' => $expectedVersion], $requestId,
            function (User $actor, Branch $branch) use ($optionId, $expectedGroupId, $expectedVersion): array {
                $option = $this->queries->option($branch, $optionId);
                if ($option->modifier_group_id !== $expectedGroupId) {
                    throw new AuthorizationException;
                }
                $group = $this->queries->group($branch, $expectedGroupId);
                $this->commands->assertVersion($group->content_version, $expectedVersion);
                if ($option->price_delta_cents !== 0) {
                    Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                }
                $before = $option->only(['id', 'name']);
                if ($option->deleteOrFail() !== true) {
                    throw new RuntimeException('The modifier could not be deleted.');
                }

                return ['required_abilities' => $option->price_delta_cents !== 0 ? ['changeMenuPrices'] : [], 'entity_type' => 'modifier_group', 'entity_id' => $group->id, 'before' => $before];
            });
    }
}
