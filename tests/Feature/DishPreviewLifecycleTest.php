<?php

declare(strict_types=1);

use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Livewire\Organizations\Brands\Branches\Menu\Variants;
use App\Models\DraftOrder;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Support\MoneyFormatter;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\DishConfigurationFixtures;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    [$this->actor, $this->branch, $original] = DishConfigurationFixtures::context();
    $this->item = MenuItem::factory()->for($original->menu)->for($original->category, 'category')
        ->withDistinctTranslations()->create(['price_cents' => 1000]);
    $this->parameters = ['organization' => $this->branch->organization, 'brand' => $this->branch->brand,
        'branch' => $this->branch, 'item' => $this->item];
});

test('draft preview stays stale after form transport and a section round trip until explicit refresh', function (): void {
    $savedName = $this->item->name;
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('previewForm.source', 'draft')->call('openPreview')
        ->assertSeeHtml('data-dish-preview-price');
    $preview = $card->get('preview');
    $baseline = $card->get('mainBaseline');

    $card->set('editingItemForm.itemTranslations.en.name', 'Unsaved preview revision')
        ->call('selectSection', 'photos')->call('selectSection', 'main')
        ->assertSet('previewStale', true)->assertSet('preview', $preview)
        ->assertSet('mainBaseline', $baseline)->assertSeeHtml('data-dish-preview-stale')
        ->assertDontSeeHtml('data-dish-preview-price')
        ->call('refreshPreview')->assertHasNoErrors()->assertSet('previewStale', false)
        ->assertSet('preview.name', 'Unsaved preview revision')->assertSeeHtml('data-dish-preview-price');
    expect($this->item->fresh()->name)->toBe($savedName)->and(DraftOrder::query()->count())->toBe(0);
});

test('saved preview is invalidated by a real child variant save without replacing the main draft', function (): void {
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->withTranslations()
        ->create(['price_cents' => 1100, 'is_available' => true]);
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)
        ->set('editingItemForm.itemTranslations.en.description', 'Keep this local description')
        ->set('previewForm.variantId', (string) $variant->id)->call('openPreview');
    $preview = $card->get('preview');
    expect($preview['formatted_price'])->toBe(MoneyFormatter::formatCents(1100, $this->branch->currency));
    Livewire::actingAs($this->actor)->test(Variants::class, ['organizationId' => $this->branch->organization_id,
        'brandId' => $this->branch->brand_id, 'branchId' => $this->branch->id, 'itemId' => $this->item->id])
        ->call('startEditingVariant', $variant->id)->set('editingVariant.variantPrice', '17.50')
        ->call('updateVariant')->assertHasNoErrors()->assertDispatched('branch-menu-updated');

    $card->dispatch('branch-menu-updated')->assertOk()->assertSet('previewStale', true)
        ->assertSet('preview', $preview)->assertDontSeeHtml('data-dish-preview-price')
        ->assertSet('editingItemForm.itemTranslations.en.description', 'Keep this local description')
        ->call('refreshPreview')->assertSet('previewStale', false)
        ->assertSet('preview.formatted_price', MoneyFormatter::formatCents(1750, $this->branch->currency));
    expect($variant->fresh()->price_cents)->toBe(1750)->and(DraftOrder::query()->count())->toBe(0);
});

test('preview controls and content language keep the previous result explicitly stale after synchronization', function (): void {
    $card = Livewire::actingAs($this->actor)->test(Dish::class, $this->parameters)->call('openPreview');
    $card->set('contentLanguage', 'lt')->assertSet('previewStale', true)
        ->assertSet('preview.language', 'en')->call('refreshPreview')->assertSet('previewStale', false)
        ->assertSet('preview.language', 'lt')
        ->set('previewForm.source', 'draft')->assertSet('previewStale', true)
        ->assertSet('preview.source', 'saved')->assertDontSeeHtml('data-dish-preview-price');
});
