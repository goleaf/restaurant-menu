<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Dish entry scope']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->menu = Menu::factory()->for($this->branch)->active()->create();
    $this->category = MenuCategory::factory()->for($this->menu)->active()->create();
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['name' => 'Verified entry dish']);
    $this->parameters = ['organization' => $this->organization, 'brand' => $this->brand, 'branch' => $this->branch, 'item' => $this->item];
});

test('direct dish HTTP routes reject mismatched organization brand restaurant and item scope', function (string $mismatch, int $status): void {
    $otherOrganization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Another permitted organization']);
    $otherBrand = Brand::factory()->for($otherOrganization)->create();
    $otherBranch = Branch::factory()->for($otherOrganization)->for($otherBrand)->create();
    $siblingBranch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $foreignMenu = Menu::factory()->for($otherBranch)->active()->create();
    $foreignCategory = MenuCategory::factory()->for($foreignMenu)->create();
    $foreignItem = MenuItem::factory()->for($foreignMenu)->for($foreignCategory, 'category')->create(['name' => 'Must never leak foreign dish']);
    $parameters = $this->parameters;
    if ($mismatch === 'organization') {
        $parameters['organization'] = $otherOrganization;
    } elseif ($mismatch === 'brand') {
        $parameters['brand'] = $otherBrand;
    } elseif ($mismatch === 'branch') {
        $parameters['branch'] = $otherBranch;
    } elseif ($mismatch === 'sibling branch') {
        $parameters['branch'] = $siblingBranch;
    } elseif ($mismatch === 'item') {
        $parameters['item'] = $foreignItem;
    } else {
        $this->item->delete();
    }
    $this->actingAs($this->actor)->get(route('organizations.brands.branches.menu.dish.edit', $parameters))
        ->assertStatus($status)->assertDontSee('Must never leak foreign dish')->assertDontSee('Verified entry dish');
})->with([
    ['organization', 403], ['brand', 403], ['branch', 403], ['sibling branch', 404], ['item', 404], ['deleted item', 404],
]);

test('legacy item entry preserves filters and content language in its verified dish redirect', function (string $section): void {
    $query = ['section' => $section, 'item' => $this->item->id, 'q' => 'Verified', 'menu' => (string) $this->menu->id,
        'quality' => 'photo', 'availability' => 'unavailable', 'page' => 3, 'language' => 'lt'];
    $response = $this->actingAs($this->actor)->get(route('organizations.brands.branches.menu.index', [
        'organization' => $this->organization, 'brand' => $this->brand, 'branch' => $this->branch, ...$query,
    ]));
    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    expect(parse_url($location, PHP_URL_PATH))->toBe(parse_url(route('organizations.brands.branches.menu.dish.edit', $this->parameters), PHP_URL_PATH));
    parse_str((string) parse_url($location, PHP_URL_QUERY), $actual);
    unset($query['item']);
    expect($actual)->toEqual($query);
})->with(['variants', 'modifiers']);

test('main baseline is a bounded field projection without authentication or resource metadata', function (): void {
    $this->actor->forceFill(['two_factor_secret' => encrypt('FICTITIOUS-DISH-ENTRY-SECRET'), 'two_factor_recovery_codes' => encrypt('["FICTITIOUS-RECOVERY-CODE"]')])->save();
    $this->item->update(['image_presentation' => ['revision' => 'FICTITIOUS-IMAGE-REVISION', 'focal_x' => 50, 'focal_y' => 50]]);
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters);
    $expected = ['itemMenuId', 'itemCategoryId', 'itemKitchenDepartmentId', 'itemName', 'itemDescription', 'itemPrice', 'itemWeight',
        'itemVolume', 'itemCalories', 'itemAllergens', 'itemDietaryLabels', 'itemSortOrder', 'itemIsAvailable', 'itemHiddenUntil', 'itemTranslations'];
    $baseline = $card->get('mainBaseline');
    expect(array_keys($baseline))->toEqualCanonicalizing($expected);
    $encoded = json_encode($baseline, JSON_THROW_ON_ERROR);
    foreach (['password', 'two_factor', 'recovery_codes', 'request_id', 'image_presentation', 'FICTITIOUS-DISH-ENTRY-SECRET', 'FICTITIOUS-RECOVERY-CODE', 'FICTITIOUS-IMAGE-REVISION'] as $forbidden) {
        expect($encoded)->not->toContain($forbidden);
    }
    expect(json_encode($card->snapshot, JSON_THROW_ON_ERROR))->not->toContain('FICTITIOUS-DISH-ENTRY-SECRET', 'FICTITIOUS-RECOVERY-CODE');
    $card->set('editingItemForm.itemTranslations.en.description', 'Unsaved description')->assertSet('mainBaseline', $baseline);
});
