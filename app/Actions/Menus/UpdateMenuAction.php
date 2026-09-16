<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateMenuAction
{
    public function __construct(
        private readonly SyncMenuTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{name: string, status: MenuStatus|string, sort_order: int, translations?: array<string, string|null>}  $data
     */
    public function handle(Menu $menu, array $data, User $actor): Menu
    {
        return DB::transaction(function () use ($menu, $data, $actor): Menu {
            $currentMenu = Menu::query()
                ->select(['id', 'branch_id', 'name', 'status', 'sort_order', 'created_at', 'updated_at', 'deleted_at'])
                ->with('branch:id,organization_id,deleted_at')
                ->where('branch_id', $menu->getRawOriginal('branch_id'))
                ->whereKey($menu->getKey())
                ->firstOrFail();
            Gate::forUser(User::query()->select(['id'])->whereKey($actor->getKey())->first())
                ->authorize('update', $currentMenu);

            $currentMenu->fill([
                'name' => $data['name'],
                'sort_order' => $data['sort_order'],
            ]);
            $currentMenu->forceFill([
                'status' => $data['status'] instanceof MenuStatus
                    ? $data['status']
                    : MenuStatus::from($data['status']),
            ])->saveOrFail();

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($currentMenu, $data['translations']);
            }

            return $menu->refresh()->load('translations');
        }, attempts: 3);
    }
}
