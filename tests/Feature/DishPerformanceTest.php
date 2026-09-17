<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Dish performance fixture']);
    $this->branch = Branch::factory()->for($this->organization)->create();
    $this->menu = Menu::factory()->for($this->branch)->active()->create(['name' => 'ZZ selected menu']);
    $this->category = MenuCategory::factory()->for($this->menu)->active()->create(['name' => 'ZZ selected category']);
    $this->department = KitchenDepartment::factory()->for($this->branch)->active()->create(['name' => 'ZZ selected department']);
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create([
        'name' => 'Measured dish', 'kitchen_department_id' => $this->department->id,
    ]);
    $this->parameters = ['organization' => $this->organization, 'brand' => $this->branch->brand, 'branch' => $this->branch, 'item' => $this->item];
});

/** @param Closure(): Testable $operation
 * @return array{0: Testable, 1: array<string, mixed>}
 */
function measureDishScreen(Closure $operation, ?string $expectedError = null): array
{
    $models = [];
    $measuring = false;
    Event::listen('eloquent.retrieved: *', function (string $event, array $payload) use (&$models, &$measuring): void {
        if ($measuring && ($payload[0] ?? null) instanceof Model) {
            $class = $payload[0]::class;
            $models[$class] = ($models[$class] ?? 0) + 1;
        }
    });
    gc_collect_cycles();
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    $allocated = memory_get_usage(true);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $measuring = true;
    $started = hrtime(true);
    try {
        $screen = $operation()->assertOk();
        if ($expectedError === null) {
            $screen->assertHasNoErrors();
        } else {
            $screen->assertHasErrors($expectedError);
        }
        $elapsed = (hrtime(true) - $started) / 1_000_000;
        $queries = count(DB::getQueryLog());
        $memoryDelta = memory_get_usage() - $memory;
        $peakDelta = memory_get_peak_usage() - $memory;
        $allocatedDelta = memory_get_usage(true) - $allocated;
        $html = $screen->html();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $embedded);
        $embeddedBytes = array_sum(array_map(fn (string $value): int => strlen(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $embedded[1]));
    } finally {
        $measuring = false;
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    ksort($models);

    return [$screen, [
        'queries' => $queries, 'models_retrieved' => array_sum($models), 'models_by_class' => $models,
        'memory_delta_bytes' => $memoryDelta, 'memory_peak_delta_bytes' => $peakDelta, 'allocated_delta_bytes' => $allocatedDelta,
        'html_bytes' => strlen($html), 'snapshot_bytes' => strlen(json_encode($screen->snapshot, JSON_THROW_ON_ERROR)),
        'embedded_snapshots_count' => count($embedded[1]), 'embedded_snapshots_bytes' => $embeddedBytes, 'elapsed_ms' => round($elapsed, 3),
    ]];
}

test('dish main cost stays bounded beside other dishes and exposes the selected child graph cost', function (): void {
    $measurements = [];
    $actor = $this->actor->fresh();
    [$cold, $measurements['first_mount']] = measureDishScreen(fn (): Testable => Livewire::actingAs($actor)->test(Dish::class, $this->parameters));
    unset($cold);
    foreach (['one_dish', 'forty_other_dishes', 'heavy_selected_graph'] as $fixture) {
        if ($fixture === 'forty_other_dishes') {
            MenuItem::factory()->count(40)->for($this->menu)->for($this->category, 'category')->create();
        } elseif ($fixture === 'heavy_selected_graph') {
            MenuItemVariant::factory()->count(100)->for($this->item, 'item')->create(['price_cents' => 1200, 'is_available' => true]);
            $groups = ModifierGroup::factory()->count(3)->for($this->branch)->optional()->create();
            foreach ($groups as $group) {
                ModifierOption::factory()->count(60)->for($group, 'group')->create(['is_available' => true, 'price_delta_cents' => 100]);
            }
            $this->item->modifierGroups()->attach($groups->modelKeys());
        }
        $actor = $this->actor->fresh();
        [$screen, $measurements[$fixture]] = measureDishScreen(fn (): Testable => Livewire::actingAs($actor)->test(Dish::class, $this->parameters));
        expect($measurements[$fixture]['queries'])->toBeLessThan(100)
            ->and($measurements[$fixture]['snapshot_bytes'])->toBeLessThan(20000);
        expect($screen->get('visitedSections'))->toBe(['main']);
    }
    expect($measurements['forty_other_dishes']['queries'])->toBe($measurements['one_dish']['queries'])
        ->and($measurements['forty_other_dishes']['models_by_class'][MenuItem::class])->toBe($measurements['one_dish']['models_by_class'][MenuItem::class])
        ->and($measurements['heavy_selected_graph']['models_by_class'][MenuItemVariant::class])->toBe(100)
        ->and($measurements['heavy_selected_graph']['models_by_class'][ModifierOption::class])->toBe(180);
    foreach (['variants', 'modifiers', 'main'] as $section) {
        [$screen, $measurements['visited_'.$section]] = measureDishScreen(fn (): Testable => $screen->call('selectSection', $section));
    }
    expect($screen->get('visitedSections'))->toBe(['main', 'variants', 'modifiers']);
    fwrite(STDOUT, 'DISH_INDEPENDENT_PERFORMANCE '.json_encode(['context' => [
        'runtime' => PHP_VERSION, 'database' => 'isolated SQLite :memory:', 'first_mount_includes_lazy_initialization' => true,
        'subsequent_comparisons' => 'Same PHP process; fresh actor instance; fixture writes excluded; query log included in memory',
        'html_scope' => 'Livewire Testable response markup; omitted existing child islands are not total browser DOM',
    ], 'measurements' => $measurements], JSON_THROW_ON_ERROR).PHP_EOL);
});

test('dish selector searches stay bounded retain selected values and preserve the actual main error', function (): void {
    Menu::factory()->count(25)->for($this->branch)->sequence(fn ($sequence): array => ['name' => sprintf('Alpha menu %02d', $sequence->index)])->create();
    MenuCategory::factory()->count(25)->for($this->menu)->sequence(fn ($sequence): array => ['name' => sprintf('Alpha category %02d', $sequence->index)])->create();
    KitchenDepartment::factory()->count(25)->for($this->branch)->sequence(fn ($sequence): array => ['name' => sprintf('Alpha department %02d', $sequence->index)])->create();
    $actor = $this->actor->fresh();
    [$screen, $before] = measureDishScreen(fn (): Testable => Livewire::actingAs($actor)->test(Dish::class, $this->parameters));
    $fields = ['menuOptions' => (string) $this->menu->id, 'editingItemCategoryOptions' => (string) $this->category->id, 'activeKitchenDepartmentOptions' => (string) $this->department->id];
    foreach ($fields as $field => $selected) {
        expect($screen->viewData($field))->toHaveCount(21)
            ->and(array_column($screen->viewData($field), 'value'))->toContain($selected);
    }
    $screen->set('editingItemForm.itemTranslations.en.name', '')->call('saveItem')->assertHasErrors('editingItemForm.itemTranslations.en.name');
    $message = $screen->instance()->getErrorBag()->first('editingItemForm.itemTranslations.en.name');
    [$screen, $after] = measureDishScreen(fn (): Testable => $screen->update(updates: [
        'search.menu' => 'Alpha menu 00', 'search.category' => 'Alpha category 00', 'search.department' => 'Alpha department 00',
    ], calls: []), expectedError: 'editingItemForm.itemTranslations.en.name');
    foreach ($fields as $field => $selected) {
        expect($screen->viewData($field))->toHaveCount(2)
            ->and(array_column($screen->viewData($field), 'value'))->toContain($selected);
    }
    expect($screen->instance()->getErrorBag()->first('editingItemForm.itemTranslations.en.name'))->toBe($message)
        ->and($screen->get('editingItemForm.itemTranslations.en.name'))->toBe('')
        ->and($after['queries'])->toBeLessThan(100);
    fwrite(STDOUT, 'DISH_SELECTOR_PERFORMANCE '.json_encode(['before' => $before, 'after' => $after,
        'filtered_option_counts' => array_map(fn (string $field): int => count($screen->viewData($field)), array_keys($fields)),
        'snapshot_bytes' => strlen(json_encode($screen->snapshot, JSON_THROW_ON_ERROR)), 'main_error_retained' => true], JSON_THROW_ON_ERROR).PHP_EOL);
});
