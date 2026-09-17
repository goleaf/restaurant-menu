<?php

declare(strict_types=1);

use App\Actions\Onboarding\CreateRestaurantSetupAction;
use App\Actions\Onboarding\SaveOnboardingStarterMenuAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Setup business']);
    $brand = Brand::factory()->for($organization)->create();
    $this->setup = app(CreateRestaurantSetupAction::class)->handle($this->actor, [
        'organizationId' => $organization->id, 'brandId' => $brand->id, 'organizationName' => '', 'brandName' => '',
        'branchName' => 'Restaurant', 'branchAddress' => 'Example 12', 'branchCity' => 'Vilnius',
        'branchCountryCode' => 'LT', 'branchTimezone' => 'Europe/Vilnius', 'branchCurrency' => 'EUR',
    ], (string) Str::uuid());
    $this->data = ['menu_name' => 'Real menu', 'category_name' => 'Soup', 'item_name' => 'Tomato soup', 'item_price' => '4.50'];
});

it('allows menu preparation before rooms tables or QR without publishing or completing setup', function (): void {
    $setup = app(SaveOnboardingStarterMenuAction::class)->handle($this->actor, $this->setup->id, $this->data);
    expect($setup->menu->status)->toBe(MenuStatus::Draft)->and($setup->menuItem->is_available)->toBeFalse()
        ->and($setup->menuItem->price_cents)->toBe(450)->and($setup->completed_at)->toBeNull()
        ->and($setup->servicePoints()->count())->toBe(0);
});

it('does not rewrite existing menu content publication routing or historical completion on continuation', function (): void {
    $action = app(SaveOnboardingStarterMenuAction::class);
    $setup = $action->handle($this->actor, $this->setup->id, $this->data);
    $setup->menu->update(['sort_order' => 23]);
    $setup->menuCategory->update(['description' => 'Preserved description', 'sort_order' => 29, 'is_active' => false]);
    $setup->menuItem->update(['description' => 'Preserved item', 'sort_order' => 31, 'allergens' => ['milk']]);
    $setup->update(['completed_at' => now()->subMonth()]);
    $before = [$setup->fresh()->getAttributes(), $setup->menu->fresh()->getAttributes(), $setup->menuCategory->fresh()->getAttributes(), $setup->menuItem->fresh()->getAttributes()];
    $action->handle($this->actor, $setup->id, $this->data);
    expect([$setup->fresh()->getAttributes(), $setup->menu->fresh()->getAttributes(), $setup->menuCategory->fresh()->getAttributes(), $setup->menuItem->fresh()->getAttributes()])->toBe($before);
    expect(fn () => $action->handle($this->actor, $setup->id, [...$this->data, 'item_name' => 'Changed']))->toThrow(ValidationException::class);
});
