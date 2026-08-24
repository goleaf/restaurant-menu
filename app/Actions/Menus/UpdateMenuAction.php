<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;

final class UpdateMenuAction
{
    public function __construct(
        private readonly SyncMenuTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{name: string, status: MenuStatus|string, sort_order: int, translations?: array<string, string|null>}  $data
     */
    public function handle(Menu $menu, array $data): Menu
    {
        return DB::transaction(function () use ($menu, $data): Menu {
            $menu->fill([
                'name' => $data['name'],
                'sort_order' => $data['sort_order'],
            ]);
            $menu->forceFill([
                'status' => $data['status'] instanceof MenuStatus
                    ? $data['status']
                    : MenuStatus::from($data['status']),
            ])->saveOrFail();

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($menu, $data['translations']);
            }

            return $menu->refresh()->load('translations');
        }, attempts: 3);
    }
}
