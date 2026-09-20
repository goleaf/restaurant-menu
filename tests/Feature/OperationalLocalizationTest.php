<?php

use App\Actions\KitchenDepartments\SeedKitchenDepartmentsForBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\KitchenDepartmentType;
use App\Livewire\Organizations\Brands\Branches\Menu\KitchenDepartments;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\User;
use App\Services\Menus\DishQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

test('standard department names follow the interface locale without changing stored names', function (string $locale, array $names): void {
    $storedNames = ['Kitchen', 'Bar', 'Dessert', 'Hookah'];
    app()->setLocale($locale);

    foreach (array_slice(KitchenDepartmentType::cases(), 0, 4) as $index => $type) {
        $department = KitchenDepartment::factory()->make(['branch_id' => 1, 'type' => $type, 'name' => $storedNames[$index]]);
        $before = $department->getAttributes();
        expect(countDatabaseQueries(fn (): string => $department->localizedName()))->toBe(0);
        expect($department->localizedName())->toBe($names[$index])
            ->and($department->name)->toBe($storedNames[$index])
            ->and($department->getAttributes())->toBe($before);

        $department->name = $names[$index];
        app()->setLocale('en');
        expect($department->localizedName())->toBe($storedNames[$index]);
        app()->setLocale($locale);

        $department->name = 'Chef’s '.$storedNames[$index];
        expect($department->localizedName())->toBe('Chef’s '.$storedNames[$index]);
    }

    expect(KitchenDepartment::factory()->make(['branch_id' => 1, 'type' => KitchenDepartmentType::Custom, 'name' => 'Kitchen'])->localizedName())->toBe('Kitchen');
    expect(array_column(KitchenDepartmentType::defaultSeedRows(), 'name'))->toBe($storedNames);
})->with([
    'English' => ['en', ['Kitchen', 'Bar', 'Dessert', 'Hookah']],
    'Lithuanian' => ['lt', ['Virtuvė', 'Baras', 'Desertai', 'Kaljanai']],
    'Russian' => ['ru', ['Кухня', 'Бар', 'Десерты', 'Кальяны']],
]);

test('department management and dish selectors present translated defaults and literal custom names', function (string $locale, array $names): void {
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create(['locale' => $locale]);
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Localization restaurant']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    app(SeedKitchenDepartmentsForBranchAction::class)->handle($branch);
    $custom = KitchenDepartment::factory()->for($branch)->create(['name' => 'Chef’s counter']);
    KitchenDepartment::factory()->create(['name' => 'Kitchen', 'type' => KitchenDepartmentType::Kitchen]);
    $before = $branch->kitchenDepartments()->get()->map->getAttributes()->all();
    app()->setLocale($locale);

    $this->actingAs($owner)->get(route('organizations.brands.branches.menu.index', [$organization, $brand, $branch, 'section' => 'departments']))->assertOk()->assertSeeText($names);
    Livewire::actingAs($owner)->test(KitchenDepartments::class, ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id])
        ->assertViewHas('kitchenDepartmentRows', fn (array $rows): bool => collect($rows)->pluck('name')->contains('Chef’s counter') && array_diff($names, array_column($rows, 'name')) === [])
        ->call('startEditingKitchenDepartment', $custom->id)->assertSet('editingDepartmentName', 'Chef’s counter');

    $editor = app(DishQuery::class)->editor($branch, null, '', '', '');
    expect(array_column($editor['activeKitchenDepartmentOptions'], 'label'))->toContain(...$names)
        ->and($branch->kitchenDepartments()->get()->map->getAttributes()->all())->toBe($before);

    $filtered = app(DishQuery::class)->editor($branch, null, '', '', '', ['menu' => '', 'category' => '', 'department' => $names[0]]);
    expect(array_column($filtered['activeKitchenDepartmentOptions'], 'label'))->toBe([$names[0]]);
})->with([
    'English' => ['en', ['Kitchen', 'Bar', 'Dessert', 'Hookah']],
    'Lithuanian' => ['lt', ['Virtuvė', 'Baras', 'Desertai', 'Kaljanai']],
    'Russian' => ['ru', ['Кухня', 'Бар', 'Десерты', 'Кальяны']],
]);

test('branch operational labels and descriptions are translated without changing their values', function (string $locale): void {
    app()->setLocale('en');
    $english = [...array_column(BranchServiceMode::options(), 'label'), ...array_column(BranchServiceMode::options(), 'description'), ...array_column(BranchOrderFlowMode::options(), 'label')];
    app()->setLocale($locale);
    $translated = [...array_column(BranchServiceMode::options(), 'label'), ...array_column(BranchServiceMode::options(), 'description'), ...array_column(BranchOrderFlowMode::options(), 'label')];

    foreach ($translated as $index => $label) {
        expect($label)->not->toBe($english[$index])->not->toStartWith('ui.branch.');
    }
    expect(array_column(BranchServiceMode::options(), 'value'))->toBe(BranchServiceMode::values())
        ->and(array_column(BranchOrderFlowMode::options(), 'value'))->toBe(BranchOrderFlowMode::values());
})->with(['lt', 'ru']);
