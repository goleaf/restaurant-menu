<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Menus\RunDishConfigurationCommandAction;
use App\Actions\Menus\ValidateDishConfigurationInputAction;
use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\User;
use App\Services\Menus\DishConfigurationData;
use App\Support\PlainText;
use Illuminate\Support\Facades\Gate;

final class UpdateModifierGroupAction
{
    public function __construct(
        private readonly SyncModifierGroupTranslationsAction $syncTranslations,
        private readonly RunDishConfigurationCommandAction $commands,
        private readonly ValidateDishConfigurationInputAction $inputs,
        private readonly DishConfigurationData $queries,
    ) {}

    /** @param array{name: string, is_required: bool, min_select: int, max_select: int, sort_order: int, translations?: array<string, string|null>} $data */
    public function handle(User $actor, Branch $branch, ModifierGroup $group, array $data, ?int $expectedVersion = null, ?string $requestId = null): ModifierGroup
    {
        $result = $this->commands->handle($actor, $branch, MenuOperationKind::ModifierChange, $group->id,
            ['operation' => 'update_group', 'data' => $data, 'expected_version' => $expectedVersion], $requestId,
            function (User $actor, Branch $branch) use ($group, $data, $expectedVersion): array {
                $group = $this->queries->group($branch, $group->id);
                $this->commands->assertVersion($group->content_version, $expectedVersion);
                $data = $this->inputs->group($branch, $data, $group);
                $before = $group->only(['name', 'is_required', 'min_select', 'max_select', 'sort_order']);
                $requiresPrices = ($group->is_required !== $data['is_required'] || $group->min_select !== $data['min_select'] || $group->max_select !== $data['max_select'])
                    && $group->options()->where('price_delta_cents', '!=', 0)->exists();
                if ($requiresPrices) {
                    Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                }
                if ($group->updateOrFail([
                    'name' => PlainText::required($data['name'], 160, squish: true),
                    'is_required' => $data['is_required'],
                    'min_select' => $data['min_select'],
                    'max_select' => $data['max_select'],
                    'sort_order' => $data['sort_order'],
                ]) !== true) {
                    throw new \RuntimeException('The modifier group could not be saved.');
                }

                if (array_key_exists('translations', $data)) {
                    $this->syncTranslations->handle($group, $data['translations']);
                }

                return ['required_abilities' => $requiresPrices ? ['changeMenuPrices'] : [], 'id' => $group->id, 'entity_type' => 'modifier_group', 'entity_id' => $group->id,
                    'before' => $before, 'after' => $group->only(array_keys($before)),
                    'changed' => $group->content_version !== $group->fresh()->content_version];
            });

        return $this->queries->group($branch, $result['id']);
    }
}
