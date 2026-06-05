<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\DraftOrderStatus;
use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\PublicQr\DraftOrder as DraftOrderComponent;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\DraftOrder as DraftOrderModel;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuCategoryTranslation;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
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
        ->assertSee('data-component="guest-draft-order"', false)
        ->assertSee('data-component="guest-menu"', false)
        ->assertSeeText($menu->name)
        ->assertSeeText($category->name)
        ->assertSeeText($availableItem->name)
        ->assertSeeText($unavailableItem->name)
        ->assertSeeText('€14.50')
        ->assertSeeText('Out of stock')
        ->assertSee(Storage::disk('public')->url((string) $availableItem->image), false)
        ->assertDontSeeText('Draft only dish')
        ->assertDontSeeText('Other branch dish')
        ->assertSeeText('Add');
});

test('guest menu shows stop listed item but blocks adding it', function () {
    [$qrCode, $branch, , $tableSession, $activeGuest] = createGuestMenuDisplayContext();
    [, , , $unavailableItem] = createGuestMenuRows($branch);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestMenu::class, [
            'branchId' => $branch->id,
            'currency' => 'EUR',
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'guestCanAddItems' => true,
        ])
        ->assertSeeText($unavailableItem->name)
        ->assertSeeText('Out of stock')
        ->call('openItem', $unavailableItem->id)
        ->assertSet('selectedItemId', null)
        ->set('selectedItemId', $unavailableItem->id)
        ->call('saveConfiguredItem')
        ->assertSet('selectedItemId', null);

    expect(DraftOrderModel::query()->exists())->toBeFalse()
        ->and(DraftOrderItem::query()->exists())->toBeFalse();
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
        ->assertSeeText('€18.75')
        ->assertDontSeeText('€14.50');

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

test('guest menu exposes available modifiers in cached payload', function () {
    [, $branch] = createGuestMenuDisplayContext();
    [, , $availableItem] = createGuestMenuRows($branch);
    [$requiredGroup, $largeOption, $soldOutOption] = createGuestMenuModifierRows($branch, $availableItem);
    $action = app(GetGuestMenuForBranchAction::class);
    $cacheKey = GetGuestMenuForBranchAction::cacheKey($branch->id, 'en');

    Cache::store(GetGuestMenuForBranchAction::cacheStore())->forget($cacheKey);

    $payload = $action->handle($branch->id, 'en');
    $itemPayload = $payload['categories'][0]['items'][0];

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeTrue()
        ->and($itemPayload['modifier_groups'][0]['id'])->toBe($requiredGroup->id)
        ->and($itemPayload['modifier_groups'][0]['is_required'])->toBeTrue()
        ->and($itemPayload['modifier_groups'][0]['options'])->toHaveCount(1)
        ->and($itemPayload['modifier_groups'][0]['options'][0]['id'])->toBe($largeOption->id)
        ->and(collect($itemPayload['modifier_groups'][0]['options'])->pluck('id')->contains($soldOutOption->id))->toBeFalse();

    $largeOption->update(['price_delta' => '4.25']);

    expect(Cache::store(GetGuestMenuForBranchAction::cacheStore())->has($cacheKey))->toBeFalse()
        ->and($action->handle($branch->id, 'en')['categories'][0]['items'][0]['modifier_groups'][0]['options'][0]['price_delta'])->toBe('4.25');
});

test('guest menu lets active guest add configured item to the shared draft', function () {
    [$qrCode, $branch, , $tableSession, $activeGuest] = createGuestMenuDisplayContext();
    [, , $availableItem] = createGuestMenuRows($branch);
    [$requiredGroup, $largeOption, $soldOutOption, $optionalGroup, $cheeseOption] = createGuestMenuModifierRows($branch, $availableItem);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestMenu::class, [
            'branchId' => $branch->id,
            'currency' => 'EUR',
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'guestCanAddItems' => true,
        ])
        ->assertSeeText('Add')
        ->call('openItem', $availableItem->id)
        ->assertSet('selectedItemId', $availableItem->id)
        ->assertSeeText('Pizza size')
        ->assertSeeText('Large')
        ->assertSeeText('Extra cheese')
        ->assertDontSeeText('Sold out')
        ->assertSeeText('€14.50')
        ->set('selectedModifierOptions.'.(string) $requiredGroup->id, [$soldOutOption->id])
        ->call('saveConfiguredItem')
        ->assertHasErrors(['selectedModifierOptions.'.(string) $requiredGroup->id])
        ->call('toggleModifierOption', $requiredGroup->id, $largeOption->id)
        ->call('toggleModifierOption', $optionalGroup->id, $cheeseOption->id)
        ->assertSeeText('€19.25')
        ->set('itemComment', 'No garlic please')
        ->call('saveConfiguredItem')
        ->assertHasNoErrors()
        ->assertSet('selectedItemId', null)
        ->assertSeeText('Added')
        ->assertSeeText('Large')
        ->assertSeeText('Extra cheese')
        ->assertSeeText('No garlic please')
        ->assertSeeText('Item added to the shared order.')
        ->assertSeeText('€19.25');

    $draftOrder = DraftOrderModel::query()
        ->where('table_session_id', $tableSession->id)
        ->firstOrFail();
    $draftOrderItem = DraftOrderItem::query()
        ->where('draft_order_id', $draftOrder->id)
        ->firstOrFail();

    expect($draftOrderItem->table_session_guest_id)->toBe($activeGuest->id)
        ->and($draftOrderItem->menu_item_id)->toBe($availableItem->id)
        ->and($draftOrderItem->item_name)->toBe('Margherita')
        ->and($draftOrderItem->unit_price)->toBe('14.50')
        ->and($draftOrderItem->modifier_total)->toBe('4.75')
        ->and($draftOrderItem->total_price)->toBe('19.25')
        ->and($draftOrderItem->comment)->toBe('No garlic please')
        ->and(collect($draftOrderItem->selected_modifiers)->pluck('option_name')->all())->toBe([
            'Large',
            'Extra cheese',
        ]);
});

test('guest menu blocks rejected guest from adding draft items', function () {
    [$qrCode, $branch, , $tableSession, $activeGuest] = createGuestMenuDisplayContext();
    [, , $availableItem] = createGuestMenuRows($branch);
    [$requiredGroup, $largeOption] = createGuestMenuModifierRows($branch, $availableItem);

    $activeGuest->forceFill(['status' => TableSessionGuestStatus::Rejected])->save();

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestMenu::class, [
            'branchId' => $branch->id,
            'currency' => 'EUR',
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'guestCanAddItems' => false,
        ])
        ->call('openItem', $availableItem->id)
        ->assertSet('selectedItemId', null)
        ->set('selectedItemId', $availableItem->id)
        ->set('selectedModifierOptions.'.(string) $requiredGroup->id, [$largeOption->id])
        ->call('saveConfiguredItem')
        ->assertHasErrors(['guest']);

    expect(DraftOrderModel::query()->exists())->toBeFalse()
        ->and(DraftOrderItem::query()->exists())->toBeFalse();
});

test('draft order component shows shared items and guest totals through polling block', function () {
    [$qrCode, , , $tableSession, $ana] = createGuestMenuDisplayContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $draftOrder = DraftOrderModel::factory()
        ->for($tableSession)
        ->create();

    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($zara, 'guest')
        ->create([
            'item_name' => 'Margherita',
            'unit_price' => '12.50',
            'modifier_total' => '0.00',
            'total_price' => '12.50',
            'selected_modifiers' => [
                [
                    'option_name' => 'Large',
                ],
            ],
        ]);
    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->create([
            'item_name' => 'Water',
            'unit_price' => '10.00',
            'modifier_total' => '0.00',
            'total_price' => '10.00',
        ]);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $ana->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->assertSee('data-component="guest-draft-order"', false)
        ->assertSeeText('Guests')
        ->assertSeeText('Alphabetical')
        ->assertSeeText('Margherita')
        ->assertSeeText('Water')
        ->assertSeeText('Large')
        ->assertSeeText('Not ready')
        ->assertSeeText('Edit')
        ->assertSet('activeGuestCount', 2)
        ->assertSet('readyGuestCount', 0)
        ->assertSet('allGuestsReady', false)
        ->assertSet('guestSections.0.guest_name', 'Ana')
        ->assertSet('guestSections.0.is_ready', false)
        ->assertSet('guestSections.0.items.0.item_name', 'Water')
        ->assertSet('guestSections.0.items.0.can_edit', true)
        ->assertSet('guestSections.1.guest_name', 'Zara')
        ->assertSet('guestSections.1.is_ready', false)
        ->assertSet('guestSections.1.items.0.item_name', 'Margherita')
        ->assertSet('guestSections.1.items.0.can_edit', false)
        ->assertSeeTextInOrder(['Ana', 'Water', '10.00 EUR', 'Zara', 'Margherita', '12.50 EUR'])
        ->assertSeeText('22.50 EUR');

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $zara->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $zara->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->assertSet('guestSections.0.guest_name', 'Ana')
        ->assertSet('guestSections.0.items.0.item_name', 'Water')
        ->assertSet('guestSections.0.items.0.can_edit', false)
        ->assertSet('guestSections.1.guest_name', 'Zara')
        ->assertSet('guestSections.1.items.0.item_name', 'Margherita')
        ->assertSet('guestSections.1.items.0.can_edit', true)
        ->assertSeeTextInOrder(['Ana', 'Water', '10.00 EUR', 'Zara', 'Margherita', '12.50 EUR'])
        ->assertSeeText('22.50 EUR');
});

test('draft order component lets active guests toggle ready status', function () {
    [$qrCode, , , $tableSession, $ana] = createGuestMenuDisplayContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
            'ready_at' => now()->subMinute(),
        ]);

    $component = Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $ana->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->assertSeeText('I am ready')
        ->assertSeeText('Not everyone is ready')
        ->assertSet('activeGuestCount', 2)
        ->assertSet('readyGuestCount', 1)
        ->assertSet('allGuestsReady', false)
        ->assertSet('currentGuestReady', false)
        ->assertSet('guestSections.0.guest_name', 'Ana')
        ->assertSet('guestSections.0.is_ready', false)
        ->assertSet('guestSections.1.guest_name', 'Zara')
        ->assertSet('guestSections.1.is_ready', true)
        ->call('toggleReadyStatus')
        ->assertHasNoErrors()
        ->assertSeeText('You marked yourself ready.')
        ->assertSeeText('Cancel ready')
        ->assertSeeText('Everyone is ready')
        ->assertSet('readyGuestCount', 2)
        ->assertSet('allGuestsReady', true)
        ->assertSet('currentGuestReady', true);

    expect($ana->fresh()->ready_at)->not->toBeNull()
        ->and($zara->fresh()->ready_at)->not->toBeNull();

    $component
        ->call('toggleReadyStatus')
        ->assertHasNoErrors()
        ->assertSeeText('Ready status removed.')
        ->assertSeeText('I am ready')
        ->assertSeeText('Not everyone is ready')
        ->assertSet('readyGuestCount', 1)
        ->assertSet('allGuestsReady', false)
        ->assertSet('currentGuestReady', false);

    expect($ana->fresh()->ready_at)->toBeNull()
        ->and($zara->fresh()->ready_at)->not->toBeNull();
});

test('draft order component asks confirmation when not all guests are ready before sending to waiter', function () {
    [$qrCode, , $servicePoint, $tableSession, $ana] = createGuestMenuDisplayContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
            'ready_at' => null,
        ]);
    $ana->update(['ready_at' => now()]);
    $draftOrder = DraftOrderModel::factory()
        ->for($tableSession)
        ->create();
    $draftOrderItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->create([
            'item_name' => 'Water',
            'total_price' => '10.00',
        ]);

    $component = Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $ana->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->assertSeeText('Send to waiter')
        ->assertSet('canSendDraftToWaiter', true)
        ->assertSet('readyGuestCount', 1)
        ->assertSet('allGuestsReady', false)
        ->call('sendDraftToWaiter')
        ->assertHasNoErrors()
        ->assertSet('sendNeedsReadyConfirmation', true)
        ->assertSeeText('Not all guests are ready');

    expect($draftOrder->fresh()->status)->toBe(DraftOrderStatus::Draft)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied);

    $component
        ->call('sendDraftToWaiter', true)
        ->assertHasNoErrors()
        ->assertSet('canEditDraft', false)
        ->assertSet('canSendDraftToWaiter', false)
        ->assertSet('sendNeedsReadyConfirmation', false)
        ->assertSeeText('Order sent to the waiter.')
        ->assertSeeText('Draft sent to the waiter. Changes are not available right now.')
        ->assertDontSeeText('Edit')
        ->call('editItem', $draftOrderItem->id)
        ->assertHasErrors(['draft_order']);

    $draftOrder = $draftOrder->fresh();

    expect($draftOrder->status)->toBe(DraftOrderStatus::SentToWaiter)
        ->and($draftOrder->sent_by_guest_id)->toBe($ana->id)
        ->and($draftOrder->sent_to_waiter_at)->not->toBeNull()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::HasNewOrder)
        ->and($ana->fresh()->ready_at)->toBeNull()
        ->and($zara->fresh()->ready_at)->toBeNull();
});

test('any active guest can send the shared draft to waiter when everyone is ready', function () {
    [$qrCode, , $servicePoint, $tableSession, $ana] = createGuestMenuDisplayContext();
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
            'ready_at' => now(),
        ]);
    $ana->update(['ready_at' => now()]);
    $draftOrder = DraftOrderModel::factory()
        ->for($tableSession)
        ->create();
    DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->create([
            'item_name' => 'Margherita',
            'total_price' => '12.50',
        ]);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $zara->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $zara->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->assertSet('allGuestsReady', true)
        ->assertSet('readyGuestCount', 2)
        ->assertSeeText('Send to waiter')
        ->call('sendDraftToWaiter')
        ->assertHasNoErrors()
        ->assertSet('canEditDraft', false)
        ->assertSet('sendNeedsReadyConfirmation', false)
        ->assertSeeText('Order sent to the waiter.');

    $draftOrder = $draftOrder->fresh();

    expect($draftOrder->status)->toBe(DraftOrderStatus::SentToWaiter)
        ->and($draftOrder->sent_by_guest_id)->toBe($zara->id)
        ->and($draftOrder->sent_to_waiter_at)->not->toBeNull()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::HasNewOrder)
        ->and($ana->fresh()->ready_at)->toBeNull()
        ->and($zara->fresh()->ready_at)->toBeNull();
});

test('draft order component lets active guest edit own draft item modifiers quantity and comment', function () {
    [$qrCode, $branch, , $tableSession, $ana] = createGuestMenuDisplayContext();
    [, , $availableItem] = createGuestMenuRows($branch);
    [$requiredGroup, $largeOption, , $optionalGroup, $cheeseOption] = createGuestMenuModifierRows($branch, $availableItem);
    $draftOrder = DraftOrderModel::factory()
        ->for($tableSession)
        ->create();
    $draftOrderItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->for($availableItem, 'menuItem')
        ->create([
            'item_name' => 'Margherita',
            'quantity' => 1,
            'unit_price' => '14.50',
            'modifier_total' => '3.50',
            'total_price' => '18.00',
            'selected_modifiers' => [
                [
                    'group_id' => $requiredGroup->id,
                    'group_name' => 'Pizza size',
                    'option_id' => $largeOption->id,
                    'option_name' => 'Large',
                    'price_delta' => '3.50',
                ],
            ],
        ]);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $ana->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->call('editItem', $draftOrderItem->id)
        ->assertSet('editingItemId', $draftOrderItem->id)
        ->assertSet('editingQuantity', 1)
        ->assertSeeText('Pizza size')
        ->assertSeeText('Extra cheese')
        ->set('editingQuantity', 2)
        ->set('editingComment', 'Cut in half')
        ->call('toggleEditingModifierOption', $optionalGroup->id, $cheeseOption->id)
        ->assertSet('editingItemTotal', '38.50')
        ->call('updateItem')
        ->assertHasNoErrors()
        ->assertSet('editingItemId', null)
        ->assertSeeText('Item updated.')
        ->assertSeeText('38.50 EUR');

    $draftOrderItem = $draftOrderItem->fresh();

    expect($draftOrderItem->quantity)->toBe(2)
        ->and($draftOrderItem->modifier_total)->toBe('4.75')
        ->and($draftOrderItem->total_price)->toBe('38.50')
        ->and($draftOrderItem->comment)->toBe('Cut in half')
        ->and(collect($draftOrderItem->selected_modifiers)->pluck('option_name')->all())->toBe([
            'Large',
            'Extra cheese',
        ]);
});

test('draft order component lets active guest delete own draft item', function () {
    [$qrCode, $branch, , $tableSession, $ana] = createGuestMenuDisplayContext();
    [, , $availableItem] = createGuestMenuRows($branch);
    $draftOrder = DraftOrderModel::factory()
        ->for($tableSession)
        ->create();
    $draftOrderItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->for($availableItem, 'menuItem')
        ->create(['item_name' => 'Water']);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $ana->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->assertSeeText('Water')
        ->call('deleteItem', $draftOrderItem->id)
        ->assertHasNoErrors()
        ->assertSeeText('Item removed.')
        ->assertDontSeeText('Water');

    expect(DraftOrderItem::query()->whereKey($draftOrderItem->id)->exists())->toBeFalse();
});

test('draft order component blocks current guest from editing another guest draft item', function () {
    [$qrCode, $branch, , $tableSession, $ana] = createGuestMenuDisplayContext();
    [, , $availableItem] = createGuestMenuRows($branch);
    $zara = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Zara',
            'status' => TableSessionGuestStatus::Active,
        ]);
    $draftOrder = DraftOrderModel::factory()
        ->for($tableSession)
        ->create();
    $zaraItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($zara, 'guest')
        ->for($availableItem, 'menuItem')
        ->create(['item_name' => 'Margherita']);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $ana->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->call('editItem', $zaraItem->id)
        ->assertHasErrors(['draft_item'])
        ->call('deleteItem', $zaraItem->id)
        ->assertHasErrors(['draft_item']);

    expect(DraftOrderItem::query()->whereKey($zaraItem->id)->exists())->toBeTrue();
});

test('draft order component blocks edits after draft is sent to waiter', function () {
    [$qrCode, $branch, , $tableSession, $ana] = createGuestMenuDisplayContext();
    [, , $availableItem] = createGuestMenuRows($branch);
    $draftOrder = DraftOrderModel::factory()
        ->for($tableSession)
        ->create(['status' => DraftOrderStatus::SentToWaiter]);
    $draftOrderItem = DraftOrderItem::factory()
        ->for($draftOrder)
        ->for($ana, 'guest')
        ->for($availableItem, 'menuItem')
        ->create([
            'item_name' => 'Margherita',
            'quantity' => 1,
            'total_price' => '14.50',
        ]);

    Livewire::withCookie(guestMenuDisplayCookieName($qrCode), $ana->guest_token)
        ->test(DraftOrderComponent::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $ana->id,
            'publicToken' => $qrCode->public_token,
            'currency' => 'EUR',
            'language' => 'en',
        ])
        ->assertSet('canEditDraft', false)
        ->assertSeeText('Draft sent to the waiter. Changes are not available right now.')
        ->call('editItem', $draftOrderItem->id)
        ->assertHasErrors(['draft_order'])
        ->call('deleteItem', $draftOrderItem->id)
        ->assertHasErrors(['draft_order']);

    expect($draftOrderItem->fresh()->quantity)->toBe(1)
        ->and(DraftOrderItem::query()->whereKey($draftOrderItem->id)->exists())->toBeTrue();
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

function createGuestMenuModifierRows(Branch $branch, MenuItem $item): array
{
    $requiredGroup = ModifierGroup::factory()
        ->for($branch)
        ->create([
            'name' => 'Pizza size',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 10,
        ]);
    $largeOption = ModifierOption::factory()
        ->for($requiredGroup)
        ->create([
            'name' => 'Large',
            'price_delta' => '3.50',
            'is_available' => true,
            'sort_order' => 10,
        ]);
    $soldOutOption = ModifierOption::factory()
        ->for($requiredGroup)
        ->create([
            'name' => 'Sold out',
            'price_delta' => '9.00',
            'is_available' => false,
            'sort_order' => 20,
        ]);
    $optionalGroup = ModifierGroup::factory()
        ->for($branch)
        ->create([
            'name' => 'Extras',
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 2,
            'sort_order' => 20,
        ]);
    $cheeseOption = ModifierOption::factory()
        ->for($optionalGroup)
        ->create([
            'name' => 'Extra cheese',
            'price_delta' => '1.25',
            'is_available' => true,
            'sort_order' => 10,
        ]);

    $item->modifierGroups()->attach([$requiredGroup->id, $optionalGroup->id]);

    return [$requiredGroup, $largeOption, $soldOutOption, $optionalGroup, $cheeseOption];
}

function guestMenuDisplayCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
