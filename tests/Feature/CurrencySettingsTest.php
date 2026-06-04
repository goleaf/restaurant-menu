<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\UpdateBranchSettingsAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\MenuStatus;
use App\Enums\SupportedCurrency;
use App\Livewire\PublicQr\GuestMenu;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Support\MoneyFormatter;
use Livewire\Livewire;

test('currency foundation supports fixed local currencies and readable formatting', function () {
    expect(SupportedCurrency::values())->toContain('EUR', 'USD', 'GBP', 'PLN', 'UAH')
        ->and(SupportedCurrency::normalize('usd'))->toBe('USD')
        ->and(SupportedCurrency::isSupported('EURO'))->toBeFalse()
        ->and(MoneyFormatter::format('14.5', 'EUR'))->toBe('€14.50')
        ->and(MoneyFormatter::format('14.5', 'USD'))->toBe('$14.50')
        ->and(MoneyFormatter::format('14.5', 'PLN'))->toBe('14.50 PLN')
        ->and(MoneyFormatter::formatSigned('-1.25', 'EUR'))->toBe('-€1.25')
        ->and(MoneyFormatter::formatSigned('3.50', 'USD'))->toBe('+$3.50');
});

test('branch settings default currency syncs to branch currency without exchange rates', function () {
    $branch = createCurrencySettingsBranch('EUR');
    $settings = $branch->settings()->firstOrFail();

    app(UpdateBranchSettingsAction::class)->handle($settings, [
        'require_waiter_confirmation_for_orders' => true,
        'allow_guest_created_sessions' => true,
        'allow_waiter_opened_sessions' => true,
        'allow_guest_invite_links' => true,
        'guest_join_requires_approval' => true,
        'polling_interval_seconds' => 1,
        'default_language' => 'en',
        'default_currency' => 'usd',
        'service_charge_enabled' => false,
        'tips_enabled' => false,
        'order_flow_mode' => BranchOrderFlowMode::WaiterConfirmation->value,
    ]);

    expect($settings->fresh()->default_currency)->toBe('USD')
        ->and($branch->fresh()->currency)->toBe('USD');
});

test('guest menu displays prices in the branch currency without converting amounts', function () {
    $branch = createCurrencySettingsBranch('USD');
    [$item, $modifierGroup, $largeOption] = createCurrencySettingsMenuRows($branch);

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => $branch->currency,
        'guestCanAddItems' => true,
    ])
        ->assertSeeText('$14.50')
        ->assertDontSeeText('14.50 USD')
        ->call('openItem', $item->id)
        ->assertSeeText('+$3.50')
        ->call('toggleModifierOption', $modifierGroup->id, $largeOption->id)
        ->assertSeeText('$18.00');

    expect($item->fresh()->price)->toBe('14.50')
        ->and($largeOption->fresh()->price_delta)->toBe('3.50');
});

function createCurrencySettingsBranch(string $currency = 'EUR'): Branch
{
    $organization = Organization::factory()->create(['name' => 'Currency Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Currency Brand']);

    return app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Currency Branch '.$currency,
        'address' => 'Currency Street 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => $currency,
        'is_active' => true,
    ]);
}

function createCurrencySettingsMenuRows(Branch $branch): array
{
    BranchSetting::query()
        ->where('branch_id', $branch->id)
        ->update(['default_currency' => $branch->currency]);

    $menu = Menu::factory()
        ->for($branch)
        ->create([
            'name' => 'Currency menu',
            'status' => MenuStatus::Active,
        ]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create([
            'name' => 'Pizza',
            'is_active' => true,
        ]);
    $item = MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create([
            'name' => 'Currency pizza',
            'price' => '14.50',
            'is_available' => true,
        ]);
    $modifierGroup = ModifierGroup::factory()
        ->for($branch)
        ->create([
            'name' => 'Size',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);
    $largeOption = ModifierOption::factory()
        ->for($modifierGroup)
        ->create([
            'name' => 'Large',
            'price_delta' => '3.50',
            'is_available' => true,
        ]);

    $item->modifierGroups()->attach($modifierGroup->id);

    return [$item, $modifierGroup, $largeOption];
}
