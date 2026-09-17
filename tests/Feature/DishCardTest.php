<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Livewire\Organizations\Brands\Branches\Menu\Index;
use App\Livewire\Organizations\Brands\Branches\Menu\Variants;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\TableSession;
use App\Models\User;
use App\Support\MoneyFormatter;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = (new CreateOrganizationAction)->handle($this->actor, ['name' => 'Dish organization']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->menu = Menu::factory()->for($this->branch)->create();
    $this->category = MenuCategory::factory()->for($this->menu)->create();
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create();
    $this->parameters = ['organization' => $this->organization, 'brand' => $this->brand, 'branch' => $this->branch, 'item' => $this->item];
});

test('dish main save stays in its scoped card and uses original English content', function () {
    Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('editingItemForm.itemName', 'Obsolete base')
        ->set('editingItemForm.itemTranslations.en.name', 'English dish')
        ->set('editingItemForm.itemTranslations.en.description', 'English description')
        ->set('editingItemForm.itemTranslations.lt.name', 'Lietuviškas patiekalas')
        ->set('editingItemForm.itemTranslations.ru.name', 'Русское блюдо')
        ->call('saveItem')->assertHasNoErrors()
        ->assertSet('editingItemId', $this->item->id)
        ->assertSet('editingItemForm.itemName', 'English dish');
    expect($this->item->fresh()->name)->toBe('English dish');
    expect($this->item->fresh()->description)->toBe('English description');
    expect($this->item->translations()->where('language_code', 'lt')->first()->name)->toBe('Lietuviškas patiekalas');
});

test('dish section changes retain main draft without saving', function () {
    $name = $this->item->name;
    Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('editingItemForm.itemDescription', 'Unsaved draft')
        ->call('selectSection', 'photos')->assertSet('section', 'photos')
        ->call('selectSection', 'main')->assertSet('editingItemForm.itemDescription', 'Unsaved draft');
    expect($this->item->fresh()->name)->toBe($name);
});

test('dish rejects an item outside its restaurant before showing content', function () {
    $foreign = MenuItem::factory()->create();
    expect(fn () => Livewire::actingAs($this->actor)->test(Dish::class, [...$this->parameters, 'item' => $foreign]))->toThrow(ModelNotFoundException::class);
});

test('dish opening creation does not insert a placeholder', function () {
    $count = MenuItem::query()->count();
    Livewire::actingAs($this->actor)->test(Dish::class, array_diff_key($this->parameters, ['item' => true]))
        ->assertSet('editingItemId', null);
    expect(MenuItem::query()->count())->toBe($count);
});

test('dish measurements cover one selected resource beside a bounded catalogue', function () {
    MenuItem::factory()->count(40)->for($this->menu)->for($this->category, 'category')->create();
    $measurements = [];
    foreach (['catalogue' => Catalog::class, 'dish' => Dish::class] as $name => $component) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $memory = memory_get_usage(true);
        $start = hrtime(true);
        $screen = Livewire::actingAs($this->actor)->test($component, $name === 'dish' ? $this->parameters : [
            'organizationId' => $this->organization->id, 'brandId' => $this->brand->id, 'branchId' => $this->branch->id,
        ]);
        $measurements[$name] = ['queries' => count(DB::getQueryLog()), 'html_bytes' => strlen($screen->html()),
            'snapshot_bytes' => strlen(json_encode($screen->snapshot, JSON_THROW_ON_ERROR)), 'memory_delta' => memory_get_usage(true) - $memory,
            'elapsed_ms' => (hrtime(true) - $start) / 1_000_000];
        DB::disableQueryLog();
    }
    expect($measurements['dish']['queries'])->toBeLessThan(100);
    expect($measurements['dish']['snapshot_bytes'])->toBeLessThan(20000);
    fwrite(STDOUT, 'DISH_MEASUREMENTS '.json_encode($measurements, JSON_THROW_ON_ERROR).PHP_EOL);
});

test('dish detects content ABA but permits independent stop list changes', function () {
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters);
    $name = $this->item->name;
    $this->item->update(['name' => 'Changed elsewhere']);
    $this->item->update(['name' => $name]);
    $card->set('editingItemForm.itemTranslations.en.name', 'Card name')
        ->set('editingItemForm.itemTranslations.lt.name', 'Kortelė')
        ->set('editingItemForm.itemTranslations.ru.name', 'Карточка')
        ->call('saveItem')->assertHasErrors('editingItemVersion');
    $card->call('discardMainChanges');
    $this->item->refresh()->update(['is_available' => false]);
    $card->set('editingItemForm.itemTranslations.en.name', 'Saved during stop')
        ->set('editingItemForm.itemTranslations.lt.name', 'Kortelė')
        ->set('editingItemForm.itemTranslations.ru.name', 'Карточка')
        ->call('saveItem')->assertHasNoErrors();
    expect($this->item->fresh()->is_available)->toBeFalse();
});

test('dish creation continues into an unavailable saved resource', function () {
    $card = Livewire::actingAs($this->actor)->test(Dish::class, array_diff_key($this->parameters, ['item' => true]))
        ->set('editingItemForm.itemMenuId', (string) $this->menu->id)
        ->set('editingItemForm.itemCategoryId', (string) $this->category->id)
        ->set('editingItemForm.itemName', 'Created English')
        ->set('editingItemForm.itemTranslations.en.name', 'Created English')
        ->set('editingItemForm.itemTranslations.lt.name', 'Sukurtas patiekalas')
        ->set('editingItemForm.itemTranslations.ru.name', 'Созданное блюдо')
        ->set('editingItemForm.itemIsAvailable', true)
        ->call('saveItem')->assertHasNoErrors();
    $id = $card->get('editingItemId');
    expect($id)->not->toBeNull();
    expect(MenuItem::query()->findOrFail($id)->is_available)->toBeFalse();
    expect($card->effects['redirect'])->toContain('/menu/items/'.$id);
});

test('dish preview uses absolute variant price and leaves persistence untouched', function () {
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->create(['price_cents' => 1800, 'is_available' => true]);
    $before = $this->item->fresh()->getAttributes();
    $drafts = DraftOrder::query()->count();
    $sessions = TableSession::query()->count();
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('previewForm.variantId', (string) $variant->id)->call('openPreview')->assertHasNoErrors();
    expect($card->get('preview.formatted_price'))->toBe(MoneyFormatter::formatCents(1800, $this->branch->currency));
    expect($this->item->fresh()->getAttributes())->toBe($before);
    expect(DraftOrder::query()->count())->toBe($drafts);
    expect(TableSession::query()->count())->toBe($sessions);
});

test('dish preview without preparation departments never seeds them', function () {
    $this->branch->kitchenDepartments()->delete();
    $before = KitchenDepartment::query()->count();
    Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('editingItemForm.itemKitchenDepartmentId', '')
        ->set('editingItemForm.itemTranslations.en.name', 'Preview English')
        ->set('editingItemForm.itemTranslations.lt.name', 'Peržiūra')
        ->set('editingItemForm.itemTranslations.ru.name', 'Предпросмотр')
        ->set('previewForm.source', 'draft')->call('openPreview')->assertHasNoErrors();
    expect(KitchenDepartment::query()->count())->toBe($before);
});

test('dish main accepts the original language without a hidden legacy input', function () {
    Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('editingItemForm.itemName', '')
        ->set('editingItemForm.itemTranslations.en.name', 'Original alone')
        ->set('editingItemForm.itemTranslations.lt.name', 'Originalas')
        ->set('editingItemForm.itemTranslations.ru.name', 'Оригинал')
        ->call('saveItem')->assertHasNoErrors();
    expect($this->item->fresh()->name)->toBe('Original alone');
});

test('legacy variant links preserve a verified dish while an empty link returns to selection', function () {
    $parameters = array_diff_key($this->parameters, ['item' => true]);
    Livewire::actingAs($this->actor)->withQueryParams(['section' => 'variants', 'item' => $this->item->id])
        ->test(Index::class, $parameters)
        ->assertRedirect(route('organizations.brands.branches.menu.dish.edit', [...$parameters, 'item' => $this->item, 'section' => 'variants']));
    Livewire::actingAs($this->actor)->withQueryParams(['section' => 'variants'])
        ->test(Index::class, $parameters)
        ->assertSet('section', 'catalog')->assertDontSeeLivewire(Variants::class);
});
