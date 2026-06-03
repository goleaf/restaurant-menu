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
use App\Models\MenuItem;
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
    [, , $availableItem] = createGuestMenuRows($branch);
    $action = app(GetGuestMenuForBranchAction::class);
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id);

    Cache::forget($cacheKey);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSee('data-component="guest-menu"', false)
        ->assertSeeText($availableItem->name);

    expect(Cache::has($cacheKey))->toBeTrue()
        ->and($action->handle($branch->id)['categories'][0]['items'][0]['name'])->toBe($availableItem->name);

    $availableItem->update(['name' => 'Updated cached pizza']);

    expect(Cache::has($cacheKey))->toBeFalse()
        ->and($action->handle($branch->id)['categories'][0]['items'][0]['name'])->toBe('Updated cached pizza');

    expect($qrCode->public_token)->not->toBeEmpty();
});

function createGuestMenuDisplayContext(): array
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
    BranchSetting::factory()->for($branch)->create();
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
