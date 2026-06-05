<?php

use App\Actions\DraftOrders\AddGuestDraftOrderItemAction;
use App\Enums\MenuStatus;
use App\Enums\ServicePointStatus;
use App\Livewire\PublicQr\DraftOrder as GuestDraftOrder;
use App\Livewire\PublicQr\GuestMenu;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\PlainText;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

test('plain text component escapes html and preserves safe layout classes', function () {
    $html = Blade::render(
        '<x-ui.plain-text :text="$text" class="text-sm" />',
        ['text' => "<script>alert('xss')</script>\nSecond line"],
    );

    expect($html)
        ->toContain('break-words')
        ->toContain('whitespace-pre-line')
        ->toContain('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;')
        ->not->toContain('<script>');
});

test('plain text normalizer strips html tags before storage', function () {
    expect(PlainText::optional(" <script>alert(1)</script>\nNo onions ", 500))
        ->toBe("alert(1)\nNo onions")
        ->not->toContain('<script>')
        ->and(PlainText::required(' <b>Ana</b>   Maria ', 80, squish: true))
        ->toBe('Ana Maria');
});

test('guest menu escapes unsafe existing menu and category text', function () {
    $branch = Branch::factory()->create(['currency' => 'EUR']);
    $menu = Menu::factory()->for($branch)->create([
        'name' => 'XSS Menu',
        'status' => MenuStatus::Active,
    ]);
    $category = MenuCategory::factory()->for($menu)->create([
        'name' => '<script>alert(1)</script>',
        'description' => "<script>alert(2)</script>\nCategory line",
        'is_active' => true,
    ]);

    MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'category_id' => $category->id,
        'name' => '<script>alert(3)</script>',
        'description' => "<script>alert(4)</script>\nDish line",
        'price' => '12.50',
        'is_available' => true,
    ]);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
    ])
        ->assertSee('data-component="guest-menu"', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('&lt;script&gt;alert(3)&lt;/script&gt;', false)
        ->assertSee('Category line')
        ->assertSee('Dish line')
        ->assertSee('whitespace-pre-line', false)
        ->assertSee('break-words', false)
        ->assertDontSee('<script', false);
});

test('guest order comments are stored as plain text and rendered without raw script html', function () {
    $branch = Branch::factory()->create(['currency' => 'EUR']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'status' => ServicePointStatus::Occupied,
            'is_active' => true,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['guest_name' => 'Ana']);
    $menu = Menu::factory()
        ->for($branch)
        ->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create(['is_active' => true]);
    $menuItem = MenuItem::factory()->create([
        'menu_id' => $menu->id,
        'category_id' => $category->id,
        'name' => 'Soup',
        'description' => 'Soup description',
        'price' => '8.00',
        'is_available' => true,
    ]);

    $draftOrderItem = app(AddGuestDraftOrderItemAction::class)->handle(
        tableSession: $tableSession,
        guest: $guest,
        menuItem: $menuItem,
        selectedModifierOptions: [],
        comment: "<script>alert(5)</script>\nNo onions",
    );

    expect($draftOrderItem->comment)
        ->toBe("alert(5)\nNo onions")
        ->not->toContain('<script>');

    Livewire::test(GuestDraftOrder::class, [
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'currency' => 'EUR',
        'publicToken' => 'xss-token',
    ])
        ->assertSee('alert(5)')
        ->assertSee('No onions')
        ->assertSee('whitespace-pre-line', false)
        ->assertDontSee('<script', false);
});
