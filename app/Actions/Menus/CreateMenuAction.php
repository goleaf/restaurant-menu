<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Menu;
use Illuminate\Support\Facades\DB;

final class CreateMenuAction
{
    public function __construct(
        private readonly SyncMenuTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{name: string, status: MenuStatus|string, sort_order: int, translations?: array<string, string|null>}  $data
     */
    public function handle(Branch $branch, array $data): Menu
    {
        return DB::transaction(function () use ($branch, $data): Menu {
            $menu = $branch->menus()->make([
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

            return $menu->load('translations');
        }, attempts: 3);
    }
}
