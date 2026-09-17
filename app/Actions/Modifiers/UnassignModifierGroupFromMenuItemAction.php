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
use Illuminate\Support\Facades\Gate;

final class UnassignModifierGroupFromMenuItemAction
{
    public function __construct(
        private readonly ForgetBranchCacheAction $forgetBranchCache,
        private readonly MarkMenuCopySourceChangedAction $markCopySourceChanged,
        private readonly RunDishConfigurationCommandAction $commands,
        private readonly DishConfigurationData $queries,
    ) {}

    public function handle(User $actor, Branch $branch, MenuItem $item, ModifierGroup $group, ?int $expectedVersion = null, ?int $expectedGroupVersion = null, ?string $requestId = null): void
    {
        $this->commands->handle($actor, $branch, MenuOperationKind::ModifierChange, $item->id,
            ['operation' => 'detach_group', 'group_id' => $group->id, 'expected_version' => $expectedVersion, 'expected_group_version' => $expectedGroupVersion], $requestId,
            function (User $actor, Branch $branch) use ($item, $group, $expectedVersion, $expectedGroupVersion): array {
                $item = $this->queries->item($branch, $item->id);
                $group = $this->queries->group($branch, $group->id);
                $this->commands->assertVersion($item->modifier_links_version, $expectedVersion);
                $this->commands->assertVersion($group->content_version, $expectedGroupVersion);
                $requiresPrices = $group->options()->where('price_delta_cents', '!=', 0)->exists();
                if ($requiresPrices) {
                    Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                }
                $changed = $item->modifierGroups()->detach($group->id) > 0;
                if ($changed) {
                    MenuItem::query()->whereKey($item->id)->increment('modifier_links_version');
                    ModifierGroup::query()->whereKey($group->id)->increment('content_version');
                    $this->markCopySourceChanged->handle($item->id);
                    $this->forgetBranchCache->handle($branch->id);
                }

                return ['required_abilities' => $requiresPrices ? ['changeMenuPrices'] : [], 'item_id' => $item->id, 'menu_id' => $item->menu_id, 'entity_type' => 'menu_item',
                    'entity_id' => $item->id, 'changed' => $changed, 'after' => ['group_id' => $group->id]];
            });
    }
}
