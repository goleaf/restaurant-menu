<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Menus\RunDishConfigurationCommandAction;
use App\Actions\Menus\ValidateDishConfigurationInputAction;
use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\User;
use App\Services\Menus\DishConfigurationData;

final class CreateModifierOptionAction
{
    public function __construct(
        private readonly BuildModifierOptionAttributesAction $buildAttributes,
        private readonly SyncModifierOptionTranslationsAction $syncTranslations,
        private readonly RunDishConfigurationCommandAction $commands,
        private readonly ValidateDishConfigurationInputAction $inputs,
        private readonly DishConfigurationData $queries,
    ) {}

    /** @param array{name: string, price_delta?: string|int, is_available?: bool, sort_order: int, translations?: array<string, string|null>} $data */
    public function handle(User $actor, Branch $branch, ModifierGroup $group, array $data, ?int $expectedVersion = null, ?string $requestId = null): ModifierOption
    {
        $result = $this->commands->handle($actor, $branch, MenuOperationKind::ModifierChange, $group->id,
            ['operation' => 'create_option', 'data' => $data, 'expected_version' => $expectedVersion], $requestId,
            function (User $actor, Branch $branch) use ($group, $data, $expectedVersion): array {
                $group = $this->queries->group($branch, $group->id);
                $this->commands->assertVersion($group->content_version, $expectedVersion);
                $data = $this->inputs->option($group, $data);
                $before = [];
                $option = $group->options()->create($this->buildAttributes->handle($actor, $branch, $data));
                if (! $option->exists) {
                    throw new \RuntimeException('The modifier option could not be saved.');
                }

                if (array_key_exists('translations', $data)) {
                    $this->syncTranslations->handle($option, $data['translations']);
                }

                return ['required_abilities' => array_values(array_filter([$option->price_delta_cents !== 0 ? 'changeMenuPrices' : null, ! $option->is_available ? 'changeMenuAvailability' : null])), 'id' => $option->id, 'entity_type' => 'modifier_group', 'entity_id' => $group->id, 'before' => $before,
                    'after' => $option->only(['id', 'name', 'price_delta_cents', 'is_available', 'sort_order']),
                    'changed' => $group->content_version !== $group->fresh()->content_version];
            });

        return $this->queries->option($branch, $result['id']);
    }
}
