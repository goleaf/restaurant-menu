<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\User;
use App\Services\Menus\DishPreviewQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\DB;

test('saved dish preview preserves guest translation presence independently of interface language', function (string $locale, ?array $translation, string $expectedName, ?string $expectedDescription) {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Preview parity']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create([
        'name' => 'Base dish', 'description' => 'Base description',
    ]);
    if ($translation !== null) {
        MenuItemTranslation::factory()->for($item, 'item')->create([
            'language_code' => $locale, ...$translation,
        ]);
    }
    app()->setLocale($locale === 'ru' ? 'lt' : 'ru');
    $guest = app(GetGuestMenuForBranchAction::class)->handle($branch->id, $locale)['categories'][0]['items'][0];
    $before = $item->fresh()->getAttributes();

    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $preview = app(DishPreviewQuery::class)->for($actor, $branch, $item->id, $locale, null, []);
        $queries = collect(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $writes = $queries->filter(fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace|create|drop|alter)\b/i', $query['query']) === 1);
    $translationQueries = $queries->filter(fn (array $query): bool => str_contains($query['query'], 'from "menu_item_translations"'));
    expect($writes)->toBeEmpty()
        ->and($translationQueries)->toHaveCount(1)
        ->and($item->fresh()->getAttributes())->toBe($before)
        ->and($guest['name'])->toBe($expectedName)
        ->and($guest['description'])->toBe($expectedDescription)
        ->and($preview['source'])->toBe('saved')
        ->and($preview['language'])->toBe($locale)
        ->and($preview['name'])->toBe($guest['name'])
        ->and($preview['description'])->toBe($guest['description']);
})->with(['en', 'lt', 'ru'])->with([
    'missing translation' => [null, 'Base dish', 'Base description'],
    'null description' => [['name' => 'Translated dish', 'description' => null], 'Translated dish', null],
    'empty description' => [['name' => 'Translated dish', 'description' => ''], 'Translated dish', ''],
    'whitespace description' => [['name' => 'Translated dish', 'description' => '   '], 'Translated dish', '   '],
    'populated description' => [['name' => 'Translated dish', 'description' => 'Translated description'], 'Translated dish', 'Translated description'],
    'empty name with explicit description' => [['name' => '', 'description' => 'Translated description'], 'Base dish', 'Translated description'],
]);
