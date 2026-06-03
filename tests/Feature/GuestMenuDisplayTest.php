<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('active guest sees active branch menu on guest table page', function () {
    Storage::fake('public');
    [$qrCode, $branch, , , $activeGuest] = createGuestMenuDisplayContext();

    [$menu, $category, $availableItem, $unavailableItem] = createGuestMenuRows($branch);
    Storage::disk('public')->put((string) $availableItem->image, 'image');

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $activeGuest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('guestCanAddItems', true)
        ->assertSee('data-component="guest-menu"', false)
        ->assertSeeText($menu->name)
        ->assertSeeText($category->name)
        ->assertSeeText($availableItem->name)
        ->assertSeeText($unavailableItem->name)
        ->assertSeeText('14.50 EUR')
        ->assertSeeText('Недоступно')
        ->assertSee(Storage::disk('public')->url((string) $availableItem->image), false)
        ->assertDontSeeText('Draft only dish')
        ->assertDontSeeText('Other branch dish')
        ->assertDontSeeText('Добавить');
});

test('guest menu component uses cached active menu payload', function () {
    [$qrCode, $branch] = createGuestMenuDisplayContext();
    [$menu, $category, $availableItem] = createGuestMenuRows($branch);
    $action = app(GetGuestMenuForBranchAction::class);
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id);

    config()->set('cache.default', 'array');

    Cache::store('array')->forget($cacheKey);
    Cache::store(GetGuestMenuForBranchAction::cacheStore())->forget($cacheKey);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSee('data-component="guest-menu"', false)
        ->assertSeeText($availableItem->name);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeTrue()
        ->and(Cache::store('array')->has($cacheKey))->toBeFalse()
        ->and($action->handle($branch->id)['categories'][0]['items'][0]['name'])->toBe($availableItem->name);

    $category->update(['name' => 'Updated cached category']);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    $action->handle($branch->id);
    $menu->update(['name' => 'Updated cached menu']);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse();

    $action->handle($branch->id);
    $availableItem->update(['price' => '18.75']);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse()
        ->and($action->handle($branch->id)['categories'][0]['items'][0]['price'])->toBe('18.75');

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSeeText('18.75 EUR')
        ->assertDontSeeText('14.50 EUR');

    expect($qrCode->public_token)->not->toBeEmpty();
});

test('guest menu uses selected language translations with default fallback', function () {
    [$qrCode, $branch] = createGuestMenuDisplayContext('en');
    [$menu, $category, $availableItem, $unavailableItem] = createGuestMenuRows($branch);
    $action = app(GetGuestMenuForBranchAction::class);
    $ltCacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'lt');
    $ruCacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'ru');

    $categoryTranslation = MenuCategoryTranslation::factory()
        ->for($category, 'category')
        ->create([
            'language_code' => 'lt',
            'name' => 'Picos LT',
            'description' => 'Karsti klasikiniai patiekalai',
        ]);
    $itemTranslation = MenuItemTranslation::factory()
        ->for($availableItem, 'item')
        ->create([
            'language_code' => 'lt',
            'name' => 'Margarita LT',
            'description' => 'Pomidorai, mozzarella, bazilikas',
        ]);

    Cache::store(GetGuestMenuForBranchAction::cacheStore())->forget($ltCacheKey);
    Cache::store(GetGuestMenuForBranchAction::cacheStore())->forget($ruCacheKey);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSet('language', 'en')
        ->set('language', 'lt')
        ->assertSeeText($menu->name)
        ->assertSeeText('Picos LT')
        ->assertSeeText('Margarita LT')
        ->assertSeeText('Truffle pizza')
        ->assertDontSeeText('Margherita');

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($ltCacheKey))->toBeTrue()
        ->and($action->handle($branch->id, 'ru')['categories'][0]['name'])->toBe('Pizza')
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($ruCacheKey))->toBeTrue();

    $itemTranslation->update(['name' => 'Atnaujinta Margarita']);
    $categoryTranslation->update(['description' => 'Atnaujintas aprasymas']);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($ltCacheKey))->toBeFalse()
        ->and(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($ruCacheKey))->toBeFalse()
        ->and($action->handle($branch->id, 'lt')['categories'][0]['items'][0]['name'])->toBe('Atnaujinta Margarita');

    expect($qrCode->public_token)->not->toBeEmpty()
        ->and($unavailableItem->name)->toBe('Truffle pizza');
});

test('guest menu starts with branch default language', function () {
    [, $branch] = createGuestMenuDisplayContext('lt');
    [, $category, $availableItem] = createGuestMenuRows($branch);

    MenuCategoryTranslation::factory()
        ->for($category, 'category')
        ->create([
            'language_code' => 'lt',
            'name' => 'Picos LT',
        ]);
    MenuItemTranslation::factory()
        ->for($availableItem, 'item')
        ->create([
            'language_code' => 'lt',
            'name' => 'Margarita LT',
        ]);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSet('language', 'lt')
        ->assertSeeText('Picos LT')
        ->assertSeeText('Margarita LT')
        ->assertDontSeeText('Margherita');
});

function createGuestMenuDisplayContext(string $defaultLanguage = 'en'): array
{
    $organization = Organization::factory()->create(['name' => 'Guest Menu Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Guest Menu Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Guest Menu Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'currency' => 'EUR',
        ]);
    BranchSetting::factory()->for($branch)->create(['default_language' => $defaultLanguage]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Guest menu table',
            'is_active' => true,
            'status' => ServicePointStatus::Occupied,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'guestmenudisplay'.fake()->unique()->numerify('######'),
            'short_code' => 'QR-GM'.fake()->unique()->numerify('####'),
            'status' => QrCodeStatus::Active,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();
    $activeGuest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    return [$qrCode, $branch, $servicePoint, $tableSession, $activeGuest];
}

function createGuestMenuRows(Branch $branch): array
{
    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Evening menu',
            'status' => MenuStatus::Active,
            'sort_order' => 10,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Pizza',
            'description' => 'Hot classics',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    $availableItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Margherita',
            'description' => 'Tomato, mozzarella, basil',
            'price' => '14.50',
            'image' => 'media/test/margherita.jpg',
            'weight' => '450',
            'calories' => 720,
            'is_available' => true,
            'sort_order' => 10,
        ]);
    $unavailableItem = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Truffle pizza',
            'price' => '21.00',
            'is_available' => false,
            'sort_order' => 20,
        ]);

    $draftMenu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Draft menu',
            'status' => MenuStatus::Draft,
        ]);
    $draftCategory = MenuCategory::factory()->for($draftMenu)->create(['name' => 'Draft category']);
    MenuItem::factory()
        ->for($draftMenu)
        ->for($draftCategory, 'category')
        ->create(['name' => 'Draft only dish']);

    $otherBranch = Branch::factory()
        ->for($branch->organization)
        ->for($branch->brand)
        ->create(['currency' => 'EUR']);
    $otherMenu = Menu::factory()
        ->for($otherBranch)
        ->create([
            'name' => 'Other branch menu',
            'status' => MenuStatus::Active,
        ]);
    $otherCategory = MenuCategory::factory()->for($otherMenu)->create(['name' => 'Other category']);
    MenuItem::factory()
        ->for($otherMenu)
        ->for($otherCategory, 'category')
        ->create(['name' => 'Other branch dish']);

    return [$menu, $category, $availableItem, $unavailableItem];
}

function guestMenuDisplayCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
