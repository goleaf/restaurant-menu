<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Menus\MarkMenuCopySourceChangedAction;
use App\Actions\Menus\RunDishConfigurationCommandAction;
use App\Actions\Menus\ValidateDishConfigurationInputAction;
use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\User;
use App\Services\Menus\DishConfigurationData;
use App\Support\PlainText;

final class CreateModifierGroupAction
{
    public function __construct(
        private readonly SyncModifierGroupTranslationsAction $syncTranslations,
        private readonly RunDishConfigurationCommandAction $commands,
        private readonly ValidateDishConfigurationInputAction $inputs,
        private readonly DishConfigurationData $queries,
        private readonly MarkMenuCopySourceChangedAction $markCopySourceChanged,
    ) {}

    /** @param array{name: string, is_required: bool, min_select: int, max_select: int, sort_order: int, translations?: array<string, string|null>} $data */
    public function handle(User $actor, Branch $branch, array $data, ?string $requestId = null, ?MenuItem $item = null, ?int $expectedLinksVersion = null): ModifierGroup
    {
        $result = $this->commands->handle($actor, $branch, MenuOperationKind::ModifierChange, $branch->id,
            ['operation' => 'create_group', 'data' => $data, 'item_id' => $item?->id, 'expected_links_version' => $expectedLinksVersion], $requestId,
            function (User $actor, Branch $branch) use ($data, $item, $expectedLinksVersion): array {
                if ($item !== null) {
                    $item = $this->queries->item($branch, $item->id);
                    $this->commands->assertVersion($item->modifier_links_version, $expectedLinksVersion);
                }
                $data = $this->inputs->group($branch, $data);
                $group = $branch->modifierGroups()->create([
                    'name' => PlainText::required($data['name'], 160, squish: true),
                    'is_required' => $data['is_required'],
                    'min_select' => $data['min_select'],
                    'max_select' => $data['max_select'],
                    'sort_order' => $data['sort_order'],
                ]);

                if (! $group->exists) {
                    throw new \RuntimeException('The modifier group could not be saved.');
                }
                if (array_key_exists('translations', $data)) {
                    $this->syncTranslations->handle($group, $data['translations']);
                }

                if ($item !== null) {
                    $item->modifierGroups()->attach($group->id);
                    MenuItem::query()->whereKey($item->id)->increment('modifier_links_version');
                    $this->markCopySourceChanged->handle($item->id);
                }

                return ['id' => $group->id, 'entity_type' => 'modifier_group', 'entity_id' => $group->id,
                    'item_id' => $item?->id, 'menu_id' => $item?->menu_id,
                    'after' => $group->only(['name', 'is_required', 'min_select', 'max_select', 'sort_order'])];
            });

        return ModifierGroup::query()->where('branch_id', $branch->id)->whereKey($result['id'])->firstOrFail()->load('translations');
    }
}
