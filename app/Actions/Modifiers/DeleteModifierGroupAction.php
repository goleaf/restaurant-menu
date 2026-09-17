<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Menus\RunDishConfigurationCommandAction;
use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\User;
use App\Services\Menus\DishConfigurationData;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class DeleteModifierGroupAction
{
    public function __construct(private readonly RunDishConfigurationCommandAction $commands, private readonly DishConfigurationData $queries) {}

    public function handle(User $actor, Branch $branch, ModifierGroup|int $group, ?int $expectedVersion = null, ?string $requestId = null): void
    {
        $groupId = $group instanceof ModifierGroup ? $group->id : $group;
        $this->commands->handle($actor, $branch, MenuOperationKind::ModifierChange, $groupId,
            ['operation' => 'delete_group', 'expected_version' => $expectedVersion], $requestId,
            function (User $actor, Branch $branch) use ($groupId, $expectedVersion): array {
                $group = $this->queries->group($branch, $groupId);
                $this->commands->assertVersion($group->content_version, $expectedVersion);
                $requiresPrices = $group->options()->where('price_delta_cents', '!=', 0)->exists();
                if ($requiresPrices) {
                    Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                }
                $before = $group->only(['id', 'name']);
                if ($group->deleteOrFail() !== true) {
                    throw new RuntimeException('The modifier could not be deleted.');
                }

                return ['required_abilities' => $requiresPrices ? ['changeMenuPrices'] : [], 'entity_type' => 'modifier_group', 'entity_id' => $group->id, 'before' => $before];
            });
    }
}
