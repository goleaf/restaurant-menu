<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Models\MenuCategory;
use App\Support\PlainText;
use Illuminate\Support\Facades\DB;

final class UpdateMenuCategoryAction
{
    public function __construct(
        private readonly SyncMenuCategoryTranslationsAction $syncTranslations,
    ) {}

    /**
     * @param  array{name: string, description: string|null, icon: string|null, sort_order: int, is_active: bool, translations?: array<string, array{name?: string|null, description?: string|null}>}  $data
     */
    public function handle(MenuCategory $category, array $data): MenuCategory
    {
        return DB::transaction(function () use ($category, $data): MenuCategory {
            $category->updateOrFail([
                'name' => PlainText::required($data['name'], 160, squish: true),
                'description' => PlainText::optional($data['description'], 1000),
                'icon' => $data['icon'],
                'sort_order' => $data['sort_order'],
                'is_active' => $data['is_active'],
            ]);

            if (array_key_exists('translations', $data)) {
                $this->syncTranslations->handle($category, $data['translations']);
            }

            return $category->refresh()->load('translations');
        }, attempts: 3);
    }
}
