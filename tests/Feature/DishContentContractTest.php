<?php

declare(strict_types=1);

use App\Actions\Menus\CreateMenuItemAction;
use App\Actions\Menus\UpdateMenuItemAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Data\Menus\MenuItemData;
use App\Enums\AuditLogAction;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\MenuItemVariant;
use App\Models\MenuOperation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\User;
use App\Services\Menus\DishPreviewQuery;
use App\Support\MoneyFormatter;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Content contracts']);
    $this->branch = Branch::factory()->for($this->organization)->create();
    $this->menu = Menu::factory()->for($this->branch)->active()->create();
    $this->category = MenuCategory::factory()->for($this->menu)->active()->create();
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['name' => 'Original dish', 'price_cents' => 1100]);
    $this->parameters = ['organization' => $this->organization, 'brand' => $this->branch->brand, 'branch' => $this->branch, 'item' => $this->item];
});

function dishContentData(string $name = 'Created original', string $price = '19.50'): MenuItemData
{
    return new MenuItemData(name: 'Legacy name must not win', description: 'Legacy description', weight: null, volume: null,
        calories: null, sortOrder: 0, price: $price, translations: [
            'en' => ['name' => $name, 'description' => 'Original description'],
            'lt' => ['name' => 'Lietuviškas patiekalas', 'description' => 'Aprašymas'],
            'ru' => ['name' => 'Русское блюдо', 'description' => 'Описание'],
        ]);
}

test('lost creation response repeats the completed UUID without another dish translation or receipt', function (): void {
    $requestId = (string) Str::uuid();
    $data = dishContentData();
    expect(fn () => DB::transaction(function () use ($requestId, $data): void {
        app(CreateMenuItemAction::class)->handle($this->actor, $this->branch, $this->menu, $this->category, null, $data, $requestId, unpublished: true);
        DB::afterCommit(fn () => throw new RuntimeException('Lost creation response'));
    }))->toThrow(RuntimeException::class, 'Lost creation response');
    $saved = MenuItem::query()->where('name', 'Created original')->sole();
    $attributes = $saved->getAttributes();
    $translations = $saved->translations()->orderBy('language_code')->get()->map->getAttributes()->all();
    $retry = app(CreateMenuItemAction::class)->handle($this->actor, $this->branch, $this->menu, $this->category, null, $data, $requestId, unpublished: true);
    expect($retry->id)->toBe($saved->id)
        ->and($retry->getAttributes())->toBe($attributes)
        ->and($retry->translations()->orderBy('language_code')->get()->map->getAttributes()->all())->toBe($translations)
        ->and($retry->name)->toBe('Created original')
        ->and($retry->description)->toBe('Original description')
        ->and($retry->is_available)->toBeFalse()
        ->and(MenuOperation::query()->where('request_id', $requestId)->count())->toBe(1)
        ->and(MenuItem::query()->where('name', 'Created original')->count())->toBe(1);
    expect(fn () => app(CreateMenuItemAction::class)->handle($this->actor, $this->branch, $this->menu, $this->category, null,
        dishContentData('Altered request'), $requestId, unpublished: true))->toThrow(AuthorizationException::class);
    $this->actor->organizations()->detach($this->organization->id);
    expect(fn () => app(CreateMenuItemAction::class)->handle($this->actor, $this->branch, $this->menu, $this->category, null,
        $data, $requestId, unpublished: true))->toThrow(AuthorizationException::class);
    expect(MenuItem::query()->where('name', 'Created original')->count())->toBe(1);
});

test('creation and update roll back all content when a translation write is vetoed', function (string $operation): void {
    MenuItemTranslation::factory()->for($this->item, 'item')->create(['language_code' => 'ru', 'name' => 'Прежнее имя']);
    MenuItemTranslation::factory()->for($this->item, 'item')->create(['language_code' => 'en', 'name' => 'Original dish']);
    $before = $this->item->fresh()->getAttributes();
    $translations = $this->item->translations()->orderBy('language_code')->get()->map->getAttributes()->all();
    $count = MenuItem::query()->count();
    if ($operation === 'create') {
        MenuItemTranslation::creating(fn (MenuItemTranslation $translation): bool => $translation->language_code !== 'en');
    } else {
        MenuItemTranslation::updating(fn (MenuItemTranslation $translation): bool => $translation->language_code !== 'en');
    }
    expect(function () use ($operation): void {
        if ($operation === 'create') {
            app(CreateMenuItemAction::class)->handle($this->actor, $this->branch, $this->menu, $this->category, null,
                dishContentData(), (string) Str::uuid(), unpublished: true);
        } else {
            app(UpdateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $this->menu, $this->category, null,
                dishContentData(), $this->item->fresh()->load('translations')->editorFingerprint(), contentOnly: true);
        }
    })->toThrow(RuntimeException::class);
    expect($this->item->fresh()->getAttributes())->toBe($before)
        ->and($this->item->translations()->orderBy('language_code')->get()->map->getAttributes()->all())->toBe($translations)
        ->and(MenuItem::query()->count())->toBe($count)
        ->and(MenuOperation::query()->count())->toBe(0);
})->with(['create', 'update']);

test('an unchanged displayed translation cannot defeat its content ABA revision', function (): void {
    $translation = MenuItemTranslation::factory()->for($this->item, 'item')->create(['language_code' => 'ru', 'name' => 'Первое имя']);
    $version = $this->item->fresh()->load('translations')->editorFingerprint();
    $translation->update(['name' => 'Промежуточное имя']);
    $translation->update(['name' => 'Первое имя']);
    expect($this->item->fresh()->load('translations')->editorFingerprint())->not->toBe($version);
    expect(fn () => app(UpdateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $this->menu, $this->category, null,
        dishContentData(), $version, contentOnly: true))->toThrow(ValidationException::class);
    expect($this->item->fresh()->name)->toBe('Original dish')->and($translation->fresh()->name)->toBe('Первое имя');
});

test('price permission is evaluated in the actual organization at apply time', function (): void {
    $secondOrganization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Independent price context']);
    $secondBranch = Branch::factory()->for($secondOrganization)->create();
    $secondMenu = Menu::factory()->for($secondBranch)->active()->create();
    $secondCategory = MenuCategory::factory()->for($secondMenu)->active()->create();
    $secondItem = MenuItem::factory()->for($secondMenu)->for($secondCategory, 'category')->create(['price_cents' => 1300]);
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)->assertSet('canChangePrices', true);
    PermissionUserOverride::factory()->forUser($this->actor)->forOrganization($this->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ChangePrices->value)->sole())->denied()->create();
    $card->set('editingItemForm.itemPrice', '99.00')->set('editingItemForm.itemTranslations', dishContentData('Scoped update')->translations)
        ->call('saveItem')->assertHasNoErrors()->assertSet('canChangePrices', false);
    app(UpdateMenuItemAction::class)->handle($this->actor, $secondBranch, $secondItem, $secondMenu, $secondCategory, null,
        dishContentData('Independent update', '45.00'), $secondItem->fresh()->load('translations')->editorFingerprint(), contentOnly: true);
    expect($this->item->fresh()->price_cents)->toBe(1100)
        ->and($this->item->fresh()->name)->toBe('Scoped update')
        ->and($secondItem->fresh()->price_cents)->toBe(4500);
});

test('dish translated errors use actual localized rules and canonical cleaned names', function (string $locale): void {
    $messages = [
        'en' => ['required' => 'The English name field is required.', 'unique' => 'The English name has already been taken.', 'max' => 'The English name field must not be greater than 180 characters.'],
        'lt' => ['required' => 'Laukas „Anglų pavadinimas“ privalomas.', 'unique' => 'Lauko „Anglų pavadinimas“ reikšmė jau naudojama.', 'max' => 'Laukas „Anglų pavadinimas“: simbolių skaičius — ne daugiau kaip 180.'],
        'ru' => ['required' => 'Поле «Название на языке: Английский» обязательно.', 'unique' => 'Значение поля «Название на языке: Английский» уже используется.', 'max' => 'Поле «Название на языке: Английский»: количество символов — не больше 180.'],
    ];
    $this->actor->update(['locale' => $locale]);
    app()->setLocale($locale);
    MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['name' => 'Occupied name']);
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters);
    foreach (['required' => '<b></b>', 'unique' => ' <b>Occupied  name</b> ', 'max' => str_repeat('Ж', 181)] as $rule => $input) {
        $card->call('discardMainChanges')->set('editingItemForm.itemName', '')
            ->set('editingItemForm.itemTranslations', dishContentData()->translations)
            ->set('editingItemForm.itemTranslations.en.name', $input)->call('saveItem')
            ->assertHasErrors('editingItemForm.itemTranslations.en.name')->assertSet('section', 'main');
        expect($card->instance()->getErrorBag()->first('editingItemForm.itemTranslations.en.name'))->toBe($messages[$locale][$rule]);
        expect($this->item->fresh()->name)->toBe('Original dish');
    }
})->with(['en', 'lt', 'ru']);

test('draft preview produces no persistence statements even without a preparation department', function (): void {
    $this->branch->kitchenDepartments()->delete();
    $before = $this->item->fresh()->getAttributes();
    $count = KitchenDepartment::query()->count();
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $preview = app(DishPreviewQuery::class)->for($this->actor, $this->branch, $this->item->id, 'en', null, [], [
            'menuId' => $this->menu->id, 'categoryId' => $this->category->id, 'kitchenDepartmentId' => null, 'data' => dishContentData('Unsaved preview'),
        ]);
        $writes = array_values(array_filter(DB::getQueryLog(), fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i', $query['query']) === 1));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($writes)->toBe([])->and($preview['name'])->toBe('Unsaved preview')
        ->and($this->item->fresh()->getAttributes())->toBe($before)
        ->and(KitchenDepartment::query()->count())->toBe($count);
});

test('independent sqlite creators with different request UUIDs cannot reserve the same normalized dish name twice', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'dish-content-concurrency-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'dish_content_concurrency', 'database.connections.dish_content_concurrency' => $connection]);
        DB::purge('dish_content_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'dish_content_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $actor = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Two creators']);
        $branch = Branch::factory()->for($organization)->create();
        $menu = Menu::factory()->for($branch)->active()->create();
        $category = MenuCategory::factory()->for($menu)->active()->create();
        $actorId = $actor->id;
        $branchId = $branch->id;
        $menuId = $menu->id;
        $categoryId = $category->id;
        $tasks = [];
        foreach (['Concurrent dish', ' <b>Concurrent  dish</b> '] as $name) {
            $requestId = (string) Str::uuid();
            $tasks[] = dishContentCreationTask($connection, $actorId, $branchId, $menuId, $categoryId, $requestId, $name);
        }
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'dish_content_concurrency']);
        DB::purge('dish_content_concurrency');
        $states = array_column($results, 'result');
        sort($states);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'pid'))->not->toContain(getmypid())
            ->and($states)->toBe(['conflict', 'created'])
            ->and(MenuItem::query()->where('category_id', $categoryId)->count())->toBe(1)
            ->and(MenuItem::query()->sole()->name)->toBe('Concurrent dish')
            ->and(MenuOperation::query()->count())->toBe(1);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('dish_content_concurrency');
        DB::purge('dish_content_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
});

/** @param array<string,mixed> $connection */
function dishContentCreationTask(array $connection, int $actorId, int $branchId, int $menuId, int $categoryId, string $requestId, string $name): Closure
{
    return static function () use ($connection, $actorId, $branchId, $menuId, $categoryId, $requestId, $name): array {
        config(['database.default' => 'dish_content_concurrency', 'database.connections.dish_content_concurrency' => $connection]);
        DB::purge('dish_content_concurrency');
        $actor = User::query()->findOrFail($actorId);
        $branch = Branch::query()->findOrFail($branchId);
        $menu = Menu::query()->findOrFail($menuId);
        $category = MenuCategory::query()->findOrFail($categoryId);
        if (MenuItem::query()->where('category_id', $categoryId)->where('name', 'Concurrent dish')->exists()) {
            throw new RuntimeException('Both creators must begin before either name is reserved.');
        }
        file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
        $deadline = microtime(true) + 5;
        while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (count(glob($connection['database'].'.ready.*')) !== 2) {
            throw new RuntimeException('Dish creators did not rendezvous.');
        }
        $data = new MenuItemData(name: $name, description: null, weight: null, volume: null, calories: null, sortOrder: 0, price: '10.00');
        try {
            $created = app(CreateMenuItemAction::class)->handle($actor, $branch, $menu, $category, null, $data, $requestId, unpublished: true);

            return ['result' => 'created', 'pid' => getmypid(), 'id' => $created->id];
        } catch (ValidationException $exception) {
            return ['result' => 'conflict', 'pid' => getmypid(), 'errors' => array_keys($exception->errors())];
        }
    };
}

test('the transactional name guard preserves translated field labels for real action failures', function (string $locale, string $name, string $expected): void {
    app()->setLocale($locale);
    $failure = null;
    try {
        app(CreateMenuItemAction::class)->handle($this->actor, $this->branch, $this->menu, $this->category, null,
            dishContentData($name), (string) Str::uuid(), unpublished: true);
    } catch (ValidationException $exception) {
        $failure = $exception;
    }
    expect($failure)->toBeInstanceOf(ValidationException::class);
    expect($failure->errors()['itemTranslations.en.name'][0] ?? null)->toBe($expected);
    expect(MenuItem::query()->count())->toBe(1)->and(MenuOperation::query()->count())->toBe(0);
})->with([
    ['en', '<b></b>', 'The English name field is required.'],
    ['lt', '<b></b>', 'Laukas „Anglų pavadinimas“ privalomas.'],
    ['ru', '<b></b>', 'Поле «Название на языке: Английский» обязательно.'],
    ['en', '<b>Original dish</b>', 'The English name has already been taken.'],
    ['lt', '<b>Original dish</b>', 'Lauko „Anglų pavadinimas“ reikšmė jau naudojama.'],
    ['ru', '<b>Original dish</b>', 'Значение поля «Название на языке: Английский» уже используется.'],
]);

test('content update refuses a revoked actor without changing any stored revision', function (): void {
    $before = $this->item->fresh()->getAttributes();
    $version = $this->item->fresh()->load('translations')->editorFingerprint();
    $this->actor->organizations()->detach($this->organization->id);
    expect(fn () => app(UpdateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $this->menu, $this->category, null,
        dishContentData(), $version, contentOnly: true))->toThrow(AuthorizationException::class);
    expect($this->item->fresh()->getAttributes())->toBe($before)->and($this->item->translations()->count())->toBe(0);
});

test('a name conflict discovered after form validation identifies the correct dish field', function (): void {
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('editingItemForm.itemTranslations', dishContentData('Racing name')->translations);
    $armed = true;
    DB::listen(function (QueryExecuted $query) use (&$armed): void {
        if ($armed && str_contains($query->sql, 'menu_items') && in_array('Racing name', $query->bindings, true)) {
            $armed = false;
            MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['name' => 'Racing name']);
        }
    });
    $card->call('saveItem')->assertHasErrors('editingItemForm.itemTranslations.en.name')
        ->assertSet('section', 'main')->assertDispatched('dish-main-invalid')
        ->assertSet('editingItemForm.itemTranslations.en.name', 'Racing name');
    expect($armed)->toBeFalse()
        ->and($card->instance()->getErrorBag()->first('editingItemForm.itemTranslations.en.name'))->toBe('The English name has already been taken.')
        ->and($this->item->fresh()->name)->toBe('Original dish');
});

test('an already open dish draft cannot be submitted by a different permitted account', function (): void {
    $secondActor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($this->organization)->forUser($secondActor)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('editingItemForm.itemTranslations', dishContentData('Original account draft')->translations);
    $this->actingAs($secondActor);
    $card->call('saveItem')->assertStatus(409);
    expect($this->item->fresh()->name)->toBe('Original dish');
});

test('a newly created dish saves and removes photos without replacing its unsaved main baseline', function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'dish-content-media-'.getmypid());
    Storage::fake('public');
    $creation = Livewire::actingAs($this->actor)->test(Dish::class, array_diff_key($this->parameters, ['item' => true]))
        ->set('editingItemForm.itemMenuId', (string) $this->menu->id)
        ->set('editingItemForm.itemCategoryId', (string) $this->category->id)
        ->set('editingItemForm.itemTranslations', dishContentData('New photo authoring')->translations)
        ->call('saveItem')->assertHasNoErrors();
    $item = MenuItem::query()->where('name', 'New photo authoring')->sole();
    $creation->assertSet('editingItemId', $item->id);
    $card = Livewire::actingAs($this->actor)->test(Dish::class, [...$this->parameters, 'item' => $item]);
    $version = $card->get('editingItemVersion');
    $baseline = $card->get('mainBaseline');
    $card->set('editingItemForm.itemTranslations.en.description', 'Unsaved while photos change')
        ->call('selectSection', 'photos')
        ->set('itemImageUploads.'.$item->id, [UploadedFile::fake()->image('first.png', 16, 16), UploadedFile::fake()->image('second.png', 16, 16)])
        ->call('saveItemImages', $item->id)->assertHasNoErrors()
        ->assertSet('mainBaseline', $baseline)->assertSet('editingItemVersion', $version)
        ->assertSet('editingItemForm.itemTranslations.en.description', 'Unsaved while photos change');
    $item->refresh();
    $gallery = $item->galleryImages()->sole();
    expect($item->image)->not->toBeNull()->and($item->media_version)->toBeGreaterThan(0);
    $card->call('removeItemGalleryImage', $item->id, $gallery->id, hash('sha256', $gallery->path), (string) Str::uuid())->assertHasNoErrors()
        ->call('removeItemImage', $item->id, hash('sha256', $item->image), (string) Str::uuid())->assertHasNoErrors()
        ->assertSet('mainBaseline', $baseline)->assertSet('editingItemVersion', $version)
        ->assertSet('editingItemForm.itemTranslations.en.description', 'Unsaved while photos change')
        ->call('selectSection', 'main')->call('saveItem')->assertHasNoErrors();
    expect($item->fresh()->description)->toBe('Unsaved while photos change')
        ->and($item->fresh()->image)->toBeNull()->and($item->galleryImages()->count())->toBe(0)
        ->and($item->fresh()->is_available)->toBeFalse();
    expect($card->get('mainBaseline')['itemTranslations']['en']['description'])->toBe('Unsaved while photos change');
});

test('main price audit and content rollback share one commit boundary', function (): void {
    $this->actingAs($this->actor);
    $before = $this->item->fresh()->getAttributes();
    $auditCount = AuditLog::query()->count();
    AuditLog::creating(fn (AuditLog $log): bool => $log->action !== AuditLogAction::MenuPriceChanged);
    expect(fn () => app(UpdateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $this->menu, $this->category, null,
        dishContentData('Price must roll back', '43.50'), $this->item->fresh()->load('translations')->editorFingerprint(), contentOnly: true))
        ->toThrow(RuntimeException::class, 'Required audit record could not be saved.');
    expect($this->item->fresh()->getAttributes())->toBe($before)
        ->and($this->item->translations()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($auditCount);
});

test('preview uses an absolute variant plus signed extras and rejects a stale unavailable option without writes', function (): void {
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->create(['price_cents' => 2300, 'is_available' => true]);
    $group = ModifierGroup::factory()->for($this->branch)->create(['is_required' => true, 'min_select' => 1, 'max_select' => 2]);
    $option = ModifierOption::factory()->for($group, 'group')->create(['price_delta_cents' => -300, 'is_available' => true]);
    $this->item->modifierGroups()->attach($group->id);
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('previewForm.variantId', (string) $variant->id)->set('previewForm.modifiers', [$group->id => [$option->id]])
        ->call('openPreview')->assertHasNoErrors();
    expect($card->get('preview')['formatted_price'])->toBe(MoneyFormatter::formatCents(2000, $this->branch->currency))
        ->and($card->get('preview')['configuration_error'])->toBeNull();
    $option->update(['is_available' => false]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $card->call('refreshPreview')->assertHasNoErrors();
        $writes = array_values(array_filter(DB::getQueryLog(), fn (array $query): bool => preg_match('/^\s*(insert|update|delete|replace|create|alter|drop)\b/i', $query['query']) === 1));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($writes)->toBe([])->and($card->get('preview')['formatted_price'])->toBeNull()
        ->and($card->get('preview')['configuration_error'])->not->toBeNull()
        ->and($card->get('preview')['is_available'])->toBeFalse()
        ->and($this->item->fresh()->price_cents)->toBe(1100)
        ->and($variant->fresh()->price_cents)->toBe(2300);
});

test('a completed dish creation repeats the price authorization used by its original request', function (): void {
    $requestId = (string) Str::uuid();
    $data = dishContentData();
    $action = app(CreateMenuItemAction::class);
    $saved = $action->handle($this->actor, $this->branch, $this->menu, $this->category, null, $data, $requestId, unpublished: true);
    PermissionUserOverride::factory()->forUser($this->actor)->forOrganization($this->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ChangePrices->value)->sole())->denied()->create();
    expect(fn () => $action->handle($this->actor, $this->branch, $this->menu, $this->category, null, $data, $requestId, unpublished: true))->toThrow(AuthorizationException::class);
    expect($saved->fresh()->price_cents)->toBe(1950)
        ->and(MenuOperation::query()->where('request_id', $requestId)->count())->toBe(1);
});
