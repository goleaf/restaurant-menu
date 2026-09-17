<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\MarkMenuCopySourceChangedAction;
use App\Actions\Menus\RunDishConfigurationCommandAction;
use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\User;
use App\Services\Menus\DishConfigurationData;
use App\Support\PlainText;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CloneModifierGroupForMenuItemAction
{
    public function __construct(
        private readonly RunDishConfigurationCommandAction $commands,
        private readonly DishConfigurationData $queries,
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly MarkMenuCopySourceChangedAction $markCopySourceChanged,
    ) {}

    public function handle(User $actor, Branch $branch, MenuItem $item, ModifierGroup $source, string $name,
        int $expectedLinksVersion, int $expectedGroupVersion, string $requestId): ModifierGroup
    {
        $result = $this->commands->handle($actor, $branch, MenuOperationKind::CloneModifierGroup, $item->id,
            ['operation' => 'clone_group', 'source_group_id' => $source->id, 'name' => $name,
                'expected_links_version' => $expectedLinksVersion, 'expected_group_version' => $expectedGroupVersion], $requestId,
            function (User $actor, Branch $branch) use ($item, $source, $name, $expectedLinksVersion, $expectedGroupVersion): array {
                $item = $this->queries->item($branch, $item->id);
                $source = $this->queries->group($branch, $source->id, true);
                $this->commands->assertVersion($item->modifier_links_version, $expectedLinksVersion);
                $this->commands->assertVersion($source->content_version, $expectedGroupVersion);
                if (! $item->modifierGroups()->whereKey($source->id)->exists()) {
                    throw ValidationException::withMessages(['configuration' => __('dish.errors.configuration_changed')]);
                }
                $requiresPrices = $source->options->contains(fn ($option): bool => $option->price_delta_cents !== 0);
                if ($requiresPrices) {
                    Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                }
                if ($source->options->count() > 50) {
                    throw ValidationException::withMessages(['configuration' => __('dish.errors.clone_limit')]);
                }
                $name = PlainText::required($name, 160, squish: true);
                if (ModifierGroup::query()->where('branch_id', $branch->id)->where('name', $name)->exists()) {
                    throw ValidationException::withMessages(['cloneName' => __('dish.errors.group_name_exists')]);
                }
                $copy = $branch->modifierGroups()->create(['name' => $name,
                    ...$source->only(['is_required', 'min_select', 'max_select', 'sort_order'])]);
                if (! $copy->exists) {
                    throw new RuntimeException('The modifier group copy could not be saved.');
                }
                foreach ($source->translations as $translation) {
                    if (! $copy->translations()->create($translation->only(['language_code', 'name']))->exists) {
                        throw new RuntimeException('The modifier group translation copy could not be saved.');
                    }
                }
                foreach ($source->options as $option) {
                    $copyOption = $copy->options()->create($option->only(['name', 'price_delta_cents', 'is_available', 'sort_order']));
                    if (! $copyOption->exists) {
                        throw new RuntimeException('The modifier option copy could not be saved.');
                    }
                    foreach ($option->translations as $translation) {
                        if (! $copyOption->translations()->create($translation->only(['language_code', 'name']))->exists) {
                            throw new RuntimeException('The modifier option translation copy could not be saved.');
                        }
                    }
                }
                $item->modifierGroups()->detach($source->id);
                $item->modifierGroups()->attach($copy->id);
                MenuItem::query()->whereKey($item->id)->increment('modifier_links_version');
                ModifierGroup::query()->whereKey($source->id)->increment('content_version');
                $this->markCopySourceChanged->handle($item->id);
                $this->forgetBranchCache->handle($branch->id);

                return ['required_abilities' => $requiresPrices ? ['changeMenuPrices'] : [], 'id' => $copy->id, 'item_id' => $item->id, 'menu_id' => $item->menu_id,
                    'entity_type' => 'menu_item', 'entity_id' => $item->id,
                    'before' => ['group_id' => $source->id], 'after' => ['group_id' => $copy->id, 'name' => $name]];
            });

        return $this->queries->group($branch, $result['id'], true);
    }
}
