<?php

declare(strict_types=1);

use App\Enums\MenuStatus;
use App\Enums\TableSessionGuestStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\GuestMenu;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use Livewire\Livewire;

test('query locale restoration persists a preference only for an active guest', function (TableSessionGuestStatus $status): void {
    [$qrCode, , $tableSession, $guest] = guestRecoveryContext();
    $guest->forceFill(['status' => $status, 'locale' => 'lt'])->saveOrFail();
    expect($guest->fresh()->status)->toBe($status);

    Livewire::withQueryParams(['lang' => 'ru'])
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('language', 'ru')
        ->assertSet('currentGuestId', $guest->id)
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('guestCanViewTable', $status === TableSessionGuestStatus::Active);

    expect($guest->fresh()->locale)->toBe($status === TableSessionGuestStatus::Active ? 'ru' : 'lt')
        ->and($guest->fresh()->status)->toBe($status);
})->with([
    'active' => TableSessionGuestStatus::Active,
    'removed' => TableSessionGuestStatus::Removed,
    'left' => TableSessionGuestStatus::Left,
    'rejected' => TableSessionGuestStatus::Rejected,
    'pending approval' => TableSessionGuestStatus::PendingApproval,
]);

test('a restored guest announces its saved language to the page and uses a newer restaurant choice', function (): void {
    [$qrCode, $branch, , $guest] = guestRecoveryContext();
    $guest->forceFill(['locale' => 'lt'])->saveOrFail();
    session()->put('interface_locale', 'en');

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'en'])
        ->assertSet('language', 'lt')
        ->assertDispatched('guest-locale-updated', language: 'lt');

    session()->put('guest_menu_locales.'.$branch->id, 'ru');

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'ru'])
        ->assertSet('language', 'ru');

    expect($guest->fresh()->locale)->toBe('ru')
        ->and(session('interface_locale'))->toBe('en');
});

test('a configured guest dish becoming unavailable reports a conflict without losing unfinished input', function (string $change): void {
    [$qrCode, $branch, $tableSession, $guest] = guestRecoveryContext();
    [$category, $item] = guestRecoveryDish($branch);
    $group = ModifierGroup::factory()->for($branch)->create(['is_required' => false, 'min_select' => 0, 'max_select' => 2]);
    $option = ModifierOption::factory()->for($group)->create(['is_available' => true]);
    $item->modifierGroups()->attach($group->id);
    $component = Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'publicToken' => $qrCode->public_token,
        'guestCanAddItems' => true,
        'language' => 'en',
    ])->call('openItem', $item->id)
        ->call('toggleModifierOption', $group->id, $option->id)
        ->set('itemComment', 'Please keep my unfinished choice')
        ->set('search', 'Recovery')
        ->set('selectedCategoryId', $category->id);
    $attemptId = $component->get('itemAddAttemptId');
    expect($attemptId)->not->toBe('');
    $component->assertSet('selectedModifierOptions.'.$group->id, [$option->id]);
    if ($change === 'menu hidden') {
        $menu = Menu::query()->findOrFail($item->menu_id);
        $menu->forceFill(['status' => MenuStatus::Archived])->saveOrFail();
        expect($menu->fresh()->status)->toBe(MenuStatus::Archived);
    } elseif ($change === 'category removed') {
        $category->delete();
    } elseif ($change === 'item hidden') {
        $item->update(['hidden_until' => now()->addHour()]);
    } else {
        $item->update(['is_available' => false]);
        expect($item->fresh()->is_available)->toBeFalse();
    }

    $component->call('saveConfiguredItem');

    expect(DraftOrder::query()->count())->toBe(0)
        ->and(DraftOrderItem::query()->count())->toBe(0);
    $component->assertHasErrors('menu_item')
        ->assertSee(__('menu.guest.item_no_longer_available'))
        ->assertSeeHtml('wire:model="itemComment"')
        ->assertSeeHtml('role="dialog"')
        ->assertDontSeeHtml('wire:click="saveConfiguredItem"')
        ->assertSeeHtml('wire:click="refreshConfiguredItem"')
        ->assertSet('selectedItemId', $item->id)
        ->assertSet('selectedModifierOptions.'.$group->id, [$option->id])
        ->assertSet('itemComment', 'Please keep my unfinished choice')
        ->assertSet('itemAddAttemptId', $attemptId)
        ->assertSet('search', 'Recovery')
        ->assertSet('selectedCategoryId', $category->id);

    if ($change === 'menu hidden') {
        Menu::query()->findOrFail($item->menu_id)->forceFill(['status' => MenuStatus::Active])->saveOrFail();
    } elseif ($change === 'category removed') {
        $category->restore();
        $deletedItem = MenuItem::withTrashed()->findOrFail($item->id);
        expect($deletedItem->trashed())->toBeTrue();
        $deletedItem->restore();
    } elseif ($change === 'item hidden') {
        $item->update(['hidden_until' => null]);
    } else {
        $item->update(['is_available' => true]);
    }

    $component->call('refreshConfiguredItem')
        ->assertHasNoErrors('menu_item')
        ->assertSeeHtml('wire:click="saveConfiguredItem"')
        ->assertSet('itemAddAttemptId', $attemptId)
        ->assertSet('itemComment', 'Please keep my unfinished choice')
        ->assertSet('selectedModifierOptions.'.$group->id, [$option->id])
        ->call('saveConfiguredItem')
        ->assertHasNoErrors()
        ->assertSet('selectedItemId', null);

    $draftItem = DraftOrderItem::query()->sole();
    expect($draftItem->comment)->toBe('Please keep my unfinished choice')
        ->and($draftItem->idempotency_key)->toBe($attemptId)
        ->and($draftItem->selected_modifiers[0]['option_id'])->toBe($option->id)
        ->and(DraftOrder::query()->count())->toBe(1);
})->with(['stop listed', 'menu hidden', 'category removed', 'item hidden']);

test('reopening the same configured dish preserves its unfinished choices and request identity', function (): void {
    [$qrCode, $branch, $tableSession, $guest] = guestRecoveryContext();
    [, $item] = guestRecoveryDish($branch);
    [, $otherItem] = guestRecoveryDish($branch);
    $group = ModifierGroup::factory()->for($branch)->create(['min_select' => 0, 'max_select' => 1]);
    $option = ModifierOption::factory()->for($group)->create(['is_available' => true]);
    $item->modifierGroups()->attach($group->id);
    $component = Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id, 'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id, 'publicToken' => $qrCode->public_token,
        'guestCanAddItems' => true, 'language' => 'en',
    ])->call('openItem', $item->id)
        ->call('toggleModifierOption', $group->id, $option->id)
        ->set('itemComment', 'Keep this while browsing');
    $attempt = $component->get('itemAddAttemptId');

    $component->call('openItem', $item->id)
        ->assertSet('itemComment', 'Keep this while browsing')
        ->assertSet('selectedModifierOptions.'.$group->id, [$option->id])
        ->assertSet('itemAddAttemptId', $attempt);

    $item->update(['is_available' => false]);
    $component->call('saveConfiguredItem')->assertHasErrors('menu_item');
    $item->update(['is_available' => true]);
    $component->call('openItem', $item->id)
        ->assertHasNoErrors('menu_item')
        ->assertSet('itemComment', 'Keep this while browsing')
        ->assertSet('selectedModifierOptions.'.$group->id, [$option->id])
        ->assertSet('itemAddAttemptId', $attempt);

    $component->call('openItem', $otherItem->id)
        ->assertSet('selectedItemId', $otherItem->id)
        ->assertSet('itemComment', '')
        ->assertSet('selectedModifierOptions', []);
    expect($component->get('itemAddAttemptId'))->not->toBe($attempt);
    expect(DraftOrderItem::query()->count())->toBe(0);
});

test('a dish already unavailable when opened retains its browse only save behavior', function (): void {
    [$qrCode, $branch, $tableSession, $guest] = guestRecoveryContext();
    [, $item] = guestRecoveryDish($branch);
    $item->update(['is_available' => false]);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'publicToken' => $qrCode->public_token,
        'guestCanAddItems' => true,
        'language' => 'en',
    ])->call('openItem', $item->id)
        ->assertSet('selectedItemId', $item->id)
        ->assertSet('itemAddAttemptId', '')
        ->call('saveConfiguredItem')
        ->assertSet('selectedItemId', null);

    expect(DraftOrder::query()->count())->toBe(0)
        ->and(DraftOrderItem::query()->count())->toBe(0);
});

test('switching guest language retains a saved basket and every unfinished dish choice', function (): void {
    [$qrCode, $branch, $tableSession, $guest] = guestRecoveryContext();
    [$category, $item] = guestRecoveryDish($branch);
    $variant = MenuItemVariant::factory()->for($item, 'item')->create(['is_default' => true, 'is_available' => true]);
    $group = ModifierGroup::factory()->for($branch)->create(['min_select' => 0, 'max_select' => 1]);
    $option = ModifierOption::factory()->for($group)->create(['is_available' => true]);
    $item->modifierGroups()->attach($group->id);
    $draft = DraftOrder::factory()->for($tableSession)->draft()->create();
    $savedItem = DraftOrderItem::factory()->for($draft)->create([
        'table_session_guest_id' => $guest->id,
        'menu_item_id' => $item->id,
        'item_name' => 'Saved historical name',
        'unit_price_cents' => 1275,
        'total_price_cents' => 1275,
        'comment' => 'Saved comment',
    ]);
    $savedAttributes = $savedItem->fresh()->getAttributes();
    session()->put('interface_locale', 'lt');
    $component = Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'tableSessionId' => $tableSession->id,
        'currentGuestId' => $guest->id,
        'publicToken' => $qrCode->public_token,
        'guestCanAddItems' => true,
        'language' => 'en',
    ])->call('openItem', $item->id)
        ->call('toggleModifierOption', $group->id, $option->id)
        ->set('itemComment', "Unfinished first paragraph.\n\nSecond paragraph.")
        ->set('search', 'Recovery')
        ->set('selectedCategoryId', $category->id);
    $attempt = $component->get('itemAddAttemptId');

    $component->set('language', 'ru')
        ->assertSet('language', 'ru')
        ->assertSet('selectedItemId', $item->id)
        ->assertSet('selectedItemVariantId', $variant->id)
        ->assertSet('selectedModifierOptions.'.$group->id, [$option->id])
        ->assertSet('itemComment', "Unfinished first paragraph.\n\nSecond paragraph.")
        ->assertSet('itemAddAttemptId', $attempt)
        ->assertSet('search', 'Recovery')
        ->assertSet('selectedCategoryId', $category->id)
        ->assertSet('tableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $guest->id);

    expect($savedItem->fresh()->getAttributes())->toBe($savedAttributes)
        ->and(DraftOrderItem::query()->count())->toBe(1)
        ->and($guest->fresh()->locale)->toBe('ru')
        ->and(session('guest_menu_locales.'.$branch->id))->toBe('ru')
        ->and(session('interface_locale'))->toBe('lt');
});

/** @return array{QrCode, Branch, TableSession, TableSessionGuest} */
function guestRecoveryContext(): array
{
    $branch = Branch::factory()->create();
    BranchSetting::factory()->for($branch)->create(['default_language' => 'en']);
    $servicePoint = ServicePoint::factory()->for($branch)->create(['is_active' => true]);
    $qrCode = QrCode::factory()->forServicePoint($servicePoint)->active()->create();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->active()->create();
    session()->put('guest_entries.'.$qrCode->public_token, [
        'table_session_id' => $tableSession->id,
        'guest_id' => $guest->id,
        'guest_token' => $guest->guest_token,
    ]);

    return [$qrCode, $branch, $tableSession, $guest];
}

/** @return array{MenuCategory, MenuItem} */
function guestRecoveryDish(Branch $branch): array
{
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->active()->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['name' => 'Recovery dish', 'is_available' => true]);

    return [$category, $item];
}
