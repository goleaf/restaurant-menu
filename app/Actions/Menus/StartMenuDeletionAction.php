<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class StartMenuDeletionAction
{
    public function __construct(private readonly EnsureMenuOperationAccessAction $access) {}

    public function handle(User $actor, Branch $branch, Menu|MenuCategory $target, string $requestId): MenuOperation
    {
        return DB::transaction(function () use ($actor, $branch, $target, $requestId): MenuOperation {
            $branch = $this->access->handle($actor, $branch, $requestId);
            $kind = $target instanceof Menu ? MenuOperationKind::DeleteMenu : MenuOperationKind::DeleteCategory;
            $existing = MenuOperation::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($existing !== null) {
                $this->access->assertOwner($existing, $actor, $branch);
                if ($existing->kind !== $kind || $existing->target_id !== (int) $target->id) {
                    throw new AuthorizationException;
                }

                return $existing;
            }
            $menuId = $target instanceof Menu ? $target->id : $target->menu_id;
            $menu = Menu::query()->select(['id', 'branch_id'])->whereKey($menuId)->where('branch_id', $branch->id)->first();
            if (! $menu instanceof Menu || ($target instanceof MenuCategory && ! MenuCategory::query()->whereKey($target->id)->where('menu_id', $menu->id)->exists())) {
                throw new AuthorizationException;
            }
            if (MenuOperation::query()->where('active_scope', 'menu:'.$menu->id)->exists()) {
                throw ValidationException::withMessages(['operation' => __('menu.operations.errors.already_running')]);
            }
            $operation = new MenuOperation;
            $operation->forceFill(['request_id' => $requestId, 'branch_id' => $branch->id, 'actor_user_id' => $actor->id, 'menu_id' => $menu->id,
                'kind' => $kind, 'target_id' => $target->id, 'active_scope' => 'menu:'.$menu->id,
                'phase' => $kind === MenuOperationKind::DeleteMenu ? MenuOperationPhase::Items : MenuOperationPhase::Discovering]);
            if ($operation->save() !== true) {
                throw new RuntimeException('The menu operation could not be saved.');
            }
            if ($target instanceof MenuCategory && ! $operation->categories()->create(['menu_category_id' => $target->id])->exists) {
                throw new RuntimeException('The menu traversal could not be saved.');
            }

            return $operation;
        });
    }
}
