<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Data\Menus\MenuItemData;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

final class CreateMenuItemAction
{
    public function __construct(
        private readonly BuildMenuItemAttributesAction $buildAttributes,
        private readonly SyncMenuItemTranslationsAction $syncTranslations,
    ) {}

    public function handle(
        User $actor,
        Branch $branch,
        Menu $menu,
        MenuCategory $category,
        ?int $kitchenDepartmentId,
        MenuItemData $data,
        ?string $requestId = null,
        bool $unpublished = false,
        bool $namesValidatedAsBatch = false,
    ): MenuItem {
        return DB::transaction(function () use ($actor, $branch, $menu, $category, $kitchenDepartmentId, $data, $requestId, $unpublished, $namesValidatedAsBatch): MenuItem {
            $actor = User::query()->select(['id'])->whereKey($actor->getKey())->first();
            if (! $actor instanceof User) {
                throw new AuthorizationException;
            }
            $branch = Branch::query()
                ->select(['id', 'organization_id', 'brand_id', 'timezone', 'deleted_at'])
                ->where('organization_id', $branch->getRawOriginal('organization_id'))
                ->whereKey($branch->getKey())->firstOrFail();
            $menu = Menu::query()
                ->select(['id', 'branch_id', 'deleted_at'])
                ->where('branch_id', $branch->id)
                ->whereKey($menu->getKey())->firstOrFail();
            $menu->setRelation('branch', $branch);
            Gate::forUser($actor)->authorize('update', $menu);
            $category = MenuCategory::query()
                ->select(['id', 'menu_id', 'deleted_at'])
                ->where('menu_id', $menu->id)
                ->whereKey($category->getKey())->firstOrFail();

            $digest = hash('sha256', serialize([$menu->id, $category->id, $kitchenDepartmentId, $data, $unpublished]));
            if ($requestId !== null) {
                if (! Str::isUuid($requestId)) {
                    throw new AuthorizationException;
                }
                $receipt = MenuOperation::query()->where('request_id', $requestId)->first();
                if ($receipt !== null) {
                    if ($receipt->actor_user_id !== $actor->id || $receipt->branch_id !== $branch->id || $receipt->kind !== MenuOperationKind::CreateItem
                        || $receipt->completed_at === null || ($receipt->payload['digest'] ?? null) !== $digest) {
                        throw new AuthorizationException;
                    }

                    foreach ($receipt->payload['required_abilities'] ?? [] as $ability) {
                        Gate::forUser($actor)->authorize($ability, $menu);
                    }

                    return MenuItem::query()->where('menu_id', $menu->id)->whereKey($receipt->result_id)->firstOrFail()->load('translations');
                }
            }
            $requiredAbilities = [];
            if ($data->price !== null && Gate::forUser($actor)->allows('changePrice', $menu)) {
                $requiredAbilities[] = 'changePrice';
            }
            if (! $unpublished && ($data->isAvailable !== null || $data->hiddenUntil !== null) && Gate::forUser($actor)->allows('changeAvailability', $menu)) {
                $requiredAbilities[] = 'changeAvailability';
            }
            $attributes = $this->buildAttributes->handle(
                actor: $actor,
                branch: $branch,
                menu: $menu,
                category: $category,
                kitchenDepartmentId: $kitchenDepartmentId,
                data: $data,
                namesValidatedAsBatch: $namesValidatedAsBatch,
            );
            if ($unpublished) {
                $attributes['is_available'] = false;
                $attributes['hidden_until'] = null;
            }
            $item = $menu->items()->create($attributes);
            if (! $item->exists) {
                throw new RuntimeException('The menu item creation was cancelled.');
            }

            $this->syncTranslations->handle($item, $data->translations ?? []);

            if ($requestId !== null) {
                $receipt = new MenuOperation;
                $receipt->forceFill(['request_id' => $requestId, 'actor_user_id' => $actor->id, 'branch_id' => $branch->id,
                    'menu_id' => $menu->id, 'kind' => MenuOperationKind::CreateItem, 'target_id' => $item->id, 'result_id' => $item->id,
                    'phase' => MenuOperationPhase::Completed, 'processed_count' => 1, 'payload' => ['digest' => $digest, 'required_abilities' => $requiredAbilities], 'completed_at' => now()]);
                if (! $receipt->save()) {
                    throw new RuntimeException('The dish creation receipt was cancelled.');
                }
            }

            return $item->refresh()->load('translations');
        }, attempts: 3);
    }
}
