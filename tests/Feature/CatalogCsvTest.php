<?php

declare(strict_types=1);

use App\Actions\Menus\ContinueMenuOperationAction;
use App\Actions\Menus\ImportCatalogCsvAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Menu\CatalogTransfer;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuOperation;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use App\Services\Menus\CatalogCsv;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

function catalogCsvContext(): array
{
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => fake()->unique()->company()]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($brand)->for($organization)->create();
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create();

    return [$actor, $branch, $menu, $category];
}

function catalogCsvContents(array $rows): string
{
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, CatalogCsv::HEADERS, escape: '');
    foreach ($rows as $row) {
        fputcsv($stream, $row, escape: '');
    }
    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);

    return $contents;
}

function catalogCsvRow(MenuCategory $category, string $id = '', string $name = 'Soup', string $price = '12.34'): array
{
    return [$id, (string) $category->id, $price, $name, "First paragraph\n\nSecond paragraph", 'Sriuba', 'Daržovės', 'Суп', 'Овощи'];
}

function applyCatalogCsv(User $actor, Branch $branch, Menu $menu, string $contents, ?array $preview = null, ?string $requestId = null): MenuOperation
{
    $preview ??= app(CatalogCsv::class)->preview($branch, $menu->id, $contents);

    return app(ImportCatalogCsvAction::class)->handle($actor, $branch, $menu->id, $contents, $preview['versions'], $preview['hash'], $requestId ?? Str::uuid()->toString());
}

test('CSV preview is read only and atomic apply creates three translations unavailable with exact cents', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $contents = catalogCsvContents([catalogCsvRow($category)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    expect($preview['errors'])->toBe([])->and($preview['create_count'])->toBe(1)->and($menu->items()->count())->toBe(0);
    $operation = applyCatalogCsv($actor, $branch, $menu, $contents, $preview);
    $item = $menu->items()->with('translations')->firstOrFail();
    expect($operation->completed_at)->not->toBeNull()->and($item->price_cents)->toBe(1234)
        ->and($item->is_available)->toBeFalse()->and($item->translations->pluck('name', 'language_code')->all())
        ->toBe(['en' => 'Soup', 'lt' => 'Sriuba', 'ru' => 'Суп']);
});

test('CSV lost response retry returns durable receipt and cannot duplicate created dishes', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $contents = catalogCsvContents([catalogCsvRow($category)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    $requestId = Str::uuid()->toString();
    $first = applyCatalogCsv($actor, $branch, $menu, $contents, $preview, $requestId);
    $retry = applyCatalogCsv($actor, $branch, $menu, $contents, $preview, $requestId);
    expect($retry->id)->toBe($first->id)->and($menu->items()->count())->toBe(1);
    $changed = catalogCsvContents([catalogCsvRow($category, name: 'Different')]);
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $changed, requestId: $requestId))->toThrow(AuthorizationException::class);
});

test('CSV row errors reject foreign categories IDs duplicate IDs and malformed money before writes', function (string $case): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $row = catalogCsvRow($category, (string) $item->id);
    if ($case === 'category') {
        $row[1] = (string) MenuCategory::factory()->create()->id;
    }
    if ($case === 'id') {
        $row[0] = (string) MenuItem::factory()->create()->id;
    }
    if ($case === 'price') {
        $row[2] = '12.345';
    }
    $contents = catalogCsvContents($case === 'duplicate' ? [$row, $row] : [$row]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    expect($preview['errors'])->not->toBeEmpty();
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $preview))->toThrow(ValidationException::class);
    expect($menu->items()->count())->toBe(1)->and(MenuOperation::query()->count())->toBe(0);
})->with(['category', 'id', 'price', 'duplicate']);

test('CSV update detects a stale preview and leaves all rows unchanged', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $contents = catalogCsvContents([catalogCsvRow($category, name: 'New soup'), catalogCsvRow($category, (string) $item->id)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    $item->update(['name' => 'Concurrent change']);
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $preview))->toThrow(ValidationException::class);
    expect($menu->items()->count())->toBe(1)->and($item->fresh()->name)->toBe('Concurrent change');
});

test('CSV import rechecks revoked actor rights after preview and on completed replay', function (bool $completed): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $contents = catalogCsvContents([catalogCsvRow($category)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    $requestId = Str::uuid()->toString();
    if ($completed) {
        applyCatalogCsv($actor, $branch, $menu, $contents, $preview, $requestId);
    }
    $actor->organizations()->detach($branch->organization_id);
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $preview, $requestId))->toThrow(AuthorizationException::class);
    expect($menu->items()->count())->toBe($completed ? 1 : 0);
})->with([false, true]);

test('CSV update preserves absent operational fields including null department', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create([
        'kitchen_department_id' => null, 'weight' => '250.50', 'volume' => '30', 'calories' => 345,
        'is_available' => false, 'sort_order' => 77, 'hidden_until' => now()->addHour(),
        'allergens' => ['milk'], 'dietary_labels' => ['vegetarian'],
    ]);
    $fields = ['kitchen_department_id', 'weight', 'volume', 'calories', 'is_available', 'sort_order', 'hidden_until', 'allergens', 'dietary_labels'];
    $before = $item->fresh()->only($fields);
    applyCatalogCsv($actor, $branch, $menu, catalogCsvContents([catalogCsvRow($category, (string) $item->id)]));
    expect($item->fresh()->only($fields))->toEqual($before);
});

test('CSV rejects non UTF8 nul oversized files and excess rows', function (string $case): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $contents = match ($case) {
        'encoding' => "\xff\xfe",
        'nul' => "\0",
        'size' => str_repeat('x', CatalogCsv::MAX_BYTES + 1),
        'rows' => catalogCsvContents(array_fill(0, 101, catalogCsvRow($category))),
        'header' => 'unexpected,header',
    };
    expect(fn () => app(CatalogCsv::class)->preview($branch, $menu->id, $contents))->toThrow(ValidationException::class);
    expect($menu->items()->count())->toBe(0);
})->with(['encoding', 'nul', 'size', 'rows', 'header']);

test('CSV enforces all locale names and permits intentionally empty descriptions', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $row = catalogCsvRow($category);
    $row[5] = '';
    expect(app(CatalogCsv::class)->preview($branch, $menu->id, catalogCsvContents([$row]))['errors'])->not->toBeEmpty();
    $row[5] = 'Sriuba';
    $row[4] = $row[6] = $row[8] = '';
    applyCatalogCsv($actor, $branch, $menu, catalogCsvContents([$row]));
    expect($menu->items()->firstOrFail()->translations()->whereNotNull('description')->exists())->toBeFalse();
});

test('CSV export formula escaping is reversible and does not evaluate spreadsheet cells', function (string $name): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => $name]);
    foreach (['en', 'lt', 'ru'] as $locale) {
        MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => $locale, 'name' => $name, 'description' => "Line one\n\nLine two"]);
    }
    $batch = app(CatalogCsv::class)->export($branch, $menu->id);
    $csv = substr($batch['contents'], 3);
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $csv);
    rewind($stream);
    fgetcsv($stream, escape: '');
    $cells = fgetcsv($stream, escape: '');
    fclose($stream);
    expect($cells[3])->toBe("'".$name);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $batch['contents']);
    expect($preview['rows'][0]['name_en'])->toBe($name)->and($preview['rows'][0]['description_en'])->toBe("Line one\n\nLine two");
})->with(['=SUM(1,2)', '+cmd', '-formula', '@reference', "'literal"]);

test('CSV export batches are explicit ordered and cover every row exactly once', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $items = MenuItem::factory()->count(102)->for($menu)->for($category, 'category')->create();
    $service = app(CatalogCsv::class);
    $first = $service->export($branch, $menu->id);
    $last = $service->export($branch, $menu->id, $first['last_id']);
    expect($first['count'])->toBe(100)->and($first['has_more'])->toBeTrue()
        ->and($last['count'])->toBe(2)->and($last['has_more'])->toBeFalse()
        ->and($last['last_id'])->toBe($items->last()->id)->and($last['first_id'])->toBeGreaterThan($first['last_id']);
});

test('CSV batch rolls back earlier changes when a later item write is vetoed', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Original']);
    $contents = catalogCsvContents([catalogCsvRow($category, name: 'New soup'), catalogCsvRow($category, (string) $item->id)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    $dispatcher = MenuItem::getEventDispatcher();
    MenuItem::setEventDispatcher(clone $dispatcher);
    MenuItem::updating(fn (MenuItem $updating): bool => $updating->id !== $item->id);
    try {
        expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $preview))->toThrow(RuntimeException::class);
    } finally {
        MenuItem::setEventDispatcher($dispatcher);
    }
    expect($menu->items()->count())->toBe(1)->and($item->fresh()->name)->toBe('Original')->and(MenuOperation::query()->count())->toBe(0);
});

test('CSV batch rejects translation write veto and rolls back item plus receipt', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $dispatcher = MenuItemTranslation::getEventDispatcher();
    MenuItemTranslation::setEventDispatcher(clone $dispatcher);
    MenuItemTranslation::creating(fn ($translation): bool => $translation->language_code !== 'ru');
    try {
        expect(fn () => applyCatalogCsv($actor, $branch, $menu, catalogCsvContents([catalogCsvRow($category)])))->toThrow(RuntimeException::class);
    } finally {
        MenuItemTranslation::setEventDispatcher($dispatcher);
    }
    expect($menu->items()->count())->toBe(0)->and(MenuOperation::query()->count())->toBe(0);
});

test('CSV Livewire performs preview and import using only the uploaded file', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $file = UploadedFile::fake()->createWithContent('catalog.csv', catalogCsvContents([catalogCsvRow($category)]));
    $component = Livewire\Livewire::actingAs($actor)->test(CatalogTransfer::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->set('form.menuId', (string) $menu->id)->set('form.file', $file)->call('previewImport')->assertHasNoErrors()->assertSet('canApply', true);
    expect($menu->items()->count())->toBe(0);
    $component->call('applyImport')->assertHasNoErrors()->assertSet('canApply', false);
    expect($menu->items()->count())->toBe(1);
});

test('CSV preview and export batch their reads instead of querying each dish', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $items = MenuItem::factory()->count(100)->for($menu)->for($category, 'category')->create();
    $rows = $items->map(fn (MenuItem $item): array => catalogCsvRow($category, (string) $item->id, name: 'Soup '.$item->id))->all();
    $single = countDatabaseQueries(fn () => app(CatalogCsv::class)->preview($branch, $menu->id, catalogCsvContents([$rows[0]])));
    $batch = countDatabaseQueries(fn () => app(CatalogCsv::class)->preview($branch, $menu->id, catalogCsvContents($rows)));
    $export = countDatabaseQueries(fn () => app(CatalogCsv::class)->export($branch, $menu->id));
    expect($single)->toBe(5)->and($batch)->toBe(5)->and($export)->toBe(3);
});

test('synchronous catalogue receipt kinds cannot enter deletion continuation while pending', function (MenuOperationKind $kind): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $operation = MenuOperation::factory()->create(['branch_id' => $branch->id, 'menu_id' => $menu->id, 'actor_user_id' => $actor->id,
        'kind' => $kind, 'target_id' => $menu->id, 'phase' => MenuOperationPhase::Items, 'completed_at' => null]);
    expect(fn () => app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id))->toThrow(AuthorizationException::class);
    expect($item->fresh()->trashed())->toBeFalse()->and($operation->fresh()->processed_count)->toBe(0);
})->with([MenuOperationKind::CatalogImport, MenuOperationKind::BulkItems]);

test('CSV changed upload fingerprint and forged stale versions cannot authorize writes', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $contents = catalogCsvContents([catalogCsvRow($category, (string) $item->id)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    $badHash = [...$preview, 'hash' => str_repeat('0', 64)];
    $badVersions = [...$preview, 'versions' => []];
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $badHash))->toThrow(ValidationException::class);
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $badVersions))->toThrow(ValidationException::class);
    expect(MenuOperation::query()->count())->toBe(0);
});

test('CSV Livewire requires preview handles invalid uploads and downloads sample plus successive batches', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    MenuItem::factory()->count(101)->for($menu)->for($category, 'category')->create();
    $component = Livewire\Livewire::actingAs($actor)->test(CatalogTransfer::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->call('applyImport')->assertHasErrors('form.file')
        ->call('downloadSample')->assertFileDownloaded('catalog-template.csv')
        ->call('exportCatalog')->assertSet('exportHasMore', true)
        ->call('exportCatalog', true)->assertSet('exportHasMore', false);
    $file = UploadedFile::fake()->createWithContent('invalid.csv', 'unexpected header');
    $component->set('form.file', $file)->call('previewImport')->assertHasErrors('form.file')->call('discardImport')->assertHasNoErrors()->assertSet('form.file', null);
});

test('CSV Livewire preview state is locked and fresh hydration rejects revoked membership', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $component = Livewire\Livewire::actingAs($actor)->test(CatalogTransfer::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ]);
    expect(fn () => $component->set('canApply', true))->toThrow(CannotUpdateLockedPropertyException::class);
    $actor->organizations()->detach($branch->organization_id);
    $component->call('downloadSample')->assertForbidden();
});

test('CSV checks specific price and create availability permissions again on apply and replay', function (SystemPermission $permission, bool $replay): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $contents = catalogCsvContents([catalogCsvRow($category)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    $requestId = Str::uuid()->toString();
    if ($replay) {
        applyCatalogCsv($actor, $branch, $menu, $contents, $preview, $requestId);
    }
    PermissionUserOverride::factory()->forUser($actor)
        ->forPermission(Permission::query()->where('code', $permission->value)->firstOrFail())->denied()->create();
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $preview, $requestId))->toThrow(AuthorizationException::class);
    expect($menu->items()->count())->toBe($replay ? 1 : 0);
})->with([SystemPermission::ChangePrices, SystemPermission::ChangeAvailability])->with([false, true]);

test('CSV cannot replay another actors receipt even when both can edit that menu', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $contents = catalogCsvContents([catalogCsvRow($category)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    $requestId = Str::uuid()->toString();
    applyCatalogCsv($actor, $branch, $menu, $contents, $preview, $requestId);
    $other = User::factory()->create();
    OrganizationUser::factory()->forOrganization($branch->organization)->forUser($other)->forSystemRole(SystemRole::Owner)->active()->create();
    expect(fn () => applyCatalogCsv($other, $branch, $menu, $contents, $preview, $requestId))->toThrow(AuthorizationException::class);
    expect($menu->items()->count())->toBe(1);
});

test('CSV normalizes plain text and rejects names made entirely of markup', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $bad = catalogCsvRow($category, name: '<b></b>');
    expect(app(CatalogCsv::class)->preview($branch, $menu->id, catalogCsvContents([$bad]))['errors'])->not->toBeEmpty();
    $row = catalogCsvRow($category, name: '<b>Soup</b>');
    $row[4] = "First <b>paragraph</b>\r\n\r\nSecond";
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, catalogCsvContents([$row]));
    expect($preview['rows'][0]['name_en'])->toBe('Soup')->and($preview['rows'][0]['description_en'])->toBe("First paragraph\n\nSecond");
});

test('CSV Livewire keeps failed imports retryable and requires a fresh preview after a stale edit', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $file = UploadedFile::fake()->createWithContent('catalog.csv', catalogCsvContents([catalogCsvRow($category, (string) $item->id)]));
    $component = Livewire::actingAs($actor)->test(CatalogTransfer::class, [
        'organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id,
    ])->set('form.file', $file)->call('previewImport')->assertSet('canApply', true);
    $dispatcher = MenuItem::getEventDispatcher();
    MenuItem::setEventDispatcher(clone $dispatcher);
    MenuItem::updating(fn (): bool => false);
    try {
        $component->call('applyImport')->assertHasErrors('form.file')->assertSet('canApply', true)->assertSet('success', '');
    } finally {
        MenuItem::setEventDispatcher($dispatcher);
    }
    $item->update(['name' => 'Edited elsewhere']);
    $component->call('applyImport')->assertHasErrors('form.file')->assertSet('canApply', false)
        ->call('previewImport')->assertSet('canApply', true)->call('applyImport')->assertHasNoErrors();
    expect($item->fresh()->name)->toBe('Soup');
});

test('CSV preview rejects duplicate final category names including normalized markup', function (bool $existing): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $rows = [catalogCsvRow($category, name: '  <b>Soup</b>  ')];
    if ($existing) {
        MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Soup']);
    } else {
        $rows[] = catalogCsvRow($category, name: 'Soup');
    }
    $contents = catalogCsvContents($rows);
    expect(app(CatalogCsv::class)->preview($branch, $menu->id, $contents)['errors'])->not->toBeEmpty();
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents))->toThrow(ValidationException::class);
    expect($menu->items()->count())->toBe($existing ? 1 : 0);
})->with([false, true]);

test('CSV validates final names after moves and permits swapping names atomically', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $destination = MenuCategory::factory()->for($menu)->create();
    $first = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Soup']);
    $second = MenuItem::factory()->for($menu)->for($destination, 'category')->create(['name' => 'Soup']);
    $collision = catalogCsvContents([catalogCsvRow($destination, (string) $first->id, 'Soup')]);
    expect(app(CatalogCsv::class)->preview($branch, $menu->id, $collision)['errors'])->not->toBeEmpty();
    $swap = catalogCsvContents([catalogCsvRow($destination, (string) $first->id, 'Soup'), catalogCsvRow($category, (string) $second->id, 'Soup')]);
    expect(app(CatalogCsv::class)->preview($branch, $menu->id, $swap)['errors'])->toBe([]);
    applyCatalogCsv($actor, $branch, $menu, $swap);
    expect($first->fresh()->category_id)->toBe($destination->id)->and($second->fresh()->category_id)->toBe($category->id);
});

test('CSV apply detects a new name collision after preview and writes nothing', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    $contents = catalogCsvContents([catalogCsvRow($category)]);
    $preview = app(CatalogCsv::class)->preview($branch, $menu->id, $contents);
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Soup']);
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, $contents, $preview))->toThrow(ValidationException::class);
    expect($menu->items()->count())->toBe(1)->and(MenuOperation::query()->count())->toBe(0);
});

test('CSV import does not write into a menu with an unfinished catalogue operation', function (): void {
    [$actor, $branch, $menu, $category] = catalogCsvContext();
    MenuOperation::factory()->create(['branch_id' => $branch->id, 'menu_id' => $menu->id, 'actor_user_id' => $actor->id,
        'kind' => MenuOperationKind::DeleteMenu, 'target_id' => $menu->id, 'active_scope' => 'menu:'.$menu->id, 'completed_at' => null]);
    expect(fn () => applyCatalogCsv($actor, $branch, $menu, catalogCsvContents([catalogCsvRow($category)])))->toThrow(ValidationException::class);
    expect($menu->items()->count())->toBe(0);
});
