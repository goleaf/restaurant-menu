<?php

declare(strict_types=1);

use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers;
use App\Livewire\Organizations\Brands\Branches\Menu\Variants;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\DishConfigurationFixtures;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('embedded variants retain their exact dish and a stale edit retains its form', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $variant = MenuItemVariant::factory()->for($item, 'item')->withTranslations()->create();
    $component = Livewire::actingAs($actor)->test(Variants::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->assertDontSeeHtml('wire:model.live="variantMenuId"')->assertDontSeeHtml('wire:model.live="variantItemId"')
        ->call('startEditingVariant', $variant->id)->set('editingVariant.variantName', 'Unsaved dish variant');
    $version = $component->get('editingVersion');
    $variant->update(['name' => 'New external value']);
    $component->call('refreshData')->assertSet('editingVersion', $version)
        ->call('updateVariant')->assertHasErrors('configuration')
        ->assertSet('editingVariant.variantName', 'Unsaved dish variant');
    expect($variant->fresh()->name)->toBe('New external value');
});

test('embedded variant editor rejects another dish in the same branch', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $other = MenuItem::factory()->for($item->menu)->for($item->category, 'category')->create();
    $variant = MenuItemVariant::factory()->for($other, 'item')->create();
    Livewire::actingAs($actor)->test(Variants::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->call('startEditingVariant', $variant->id)->assertNotFound();
});

test('embedded modifier editor creates and assigns one group without a dish selector', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->assertDontSeeHtml('wire:model.live="assignment.modifierItemMenuId"')
        ->set('group.modifierGroupName', 'Own extras')
        ->set('group.modifierGroupTranslations', ['en' => 'Own extras', 'lt' => 'Priedai', 'ru' => 'Добавки'])
        ->call('createModifierGroup')->assertHasNoErrors();
    expect($item->modifierGroups()->sole()->name)->toBe('Own extras')
        ->and(ModifierGroup::query()->where('branch_id', $branch->id)->count())->toBe(1);
});

test('lost creation responses replay through form uniqueness without duplicating a variant or group', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $params = ['organizationId' => $branch->organization_id, 'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id];
    $variant = Livewire::actingAs($actor)->test(Variants::class, $params)
        ->set('variant.variantName', 'Large')->set('variant.variantPrice', '12.50')
        ->set('variant.variantTranslations', ['en' => 'Large', 'lt' => 'Didelė', 'ru' => 'Большая']);
    $variantRetry = clone $variant;
    $variant->call('createVariant')->assertHasNoErrors();
    $variantRetry->call('createVariant')->assertHasNoErrors();
    expect($item->variants()->count())->toBe(1);

    $group = Livewire::actingAs($actor)->test(Modifiers::class, $params)
        ->set('group.modifierGroupName', 'Extras')
        ->set('group.modifierGroupTranslations', ['en' => 'Extras', 'lt' => 'Priedai', 'ru' => 'Добавки']);
    $groupRetry = clone $group;
    $group->call('createModifierGroup')->assertHasNoErrors();
    $groupRetry->call('createModifierGroup')->assertHasNoErrors();
    expect($item->modifierGroups()->count())->toBe(1);
});

test('zero modifier maximum keeps its unlimited meaning with a positive minimum', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->set('group.modifierGroupName', 'Required choices')->set('group.modifierGroupMinSelect', 2)->set('group.modifierGroupMaxSelect', 0)
        ->set('group.modifierGroupTranslations', ['en' => 'Required choices', 'lt' => 'Pasirinkimai', 'ru' => 'Выбор'])
        ->call('createModifierGroup')->assertHasNoErrors()->assertSee(__('dish.modifiers.not_orderable'));
    expect($item->modifierGroups()->sole()->max_select)->toBe(0);
});

test('changing variant editor requires explicit cancellation of the existing draft', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    [$first, $second] = MenuItemVariant::factory()->count(2)->for($item, 'item')->create()->all();
    Livewire::actingAs($actor)->test(Variants::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->call('startEditingVariant', $first->id)->set('editingVariant.variantName', 'Unfinished')
        ->call('startEditingVariant', $second->id)->assertHasErrors('configuration')
        ->assertSet('editingVariantId', $first->id)->assertSet('editingVariant.variantName', 'Unfinished');
});

test('deleting another shared group preserves an independent modifier draft', function (): void {
    [$actor, $branch] = DishConfigurationFixtures::context();
    [$first, $second] = ModifierGroup::factory()->count(2)->for($branch)->create()->all();
    Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id])
        ->call('startEditingModifierGroup', $first->id)->set('editingGroup.modifierGroupName', 'Unfinished')
        ->call('deleteModifierGroup', $second->id)->assertOk()->assertHasNoErrors()
        ->assertSet('editingModifierGroupId', $first->id)->assertSet('editingGroup.modifierGroupName', 'Unfinished');
    expect($second->fresh())->toBeNull();
});

test('modifier option paging exposes later options without leaving the selected dish', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $group = ModifierGroup::factory()->for($branch)->create();
    $item->modifierGroups()->attach($group);
    ModifierOption::factory()->count(10)->for($group, 'group')->create(['sort_order' => 1]);
    $later = ModifierOption::factory()->for($group, 'group')->create(['name' => 'Later option', 'sort_order' => 99]);
    $unattached = ModifierGroup::factory()->for($branch)->create(['name' => 'Other dish only']);
    Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->assertDontSee('Later option')->call('nextOptionsPage', $group->id)->assertOk()->assertSee('Later option')
        ->call('startEditingModifierOption', $later->id)->assertSet('editingModifierOptionId', $later->id)
        ->call('nextOptionsPage', $unattached->id)->assertForbidden();
});

test('large variant sets are paginated and later variants remain editable', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    MenuItemVariant::factory()->count(12)->for($item, 'item')->create(['sort_order' => 1, 'is_default' => false]);
    $later = MenuItemVariant::factory()->for($item, 'item')->create(['name' => 'Later variant', 'sort_order' => 99, 'is_default' => false]);
    Livewire::actingAs($actor)->test(Variants::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->assertDontSee('Later variant')->call('nextPage', 'dishVariantsPage')->assertOk()->assertSee('Later variant')
        ->call('startEditingVariant', $later->id)->assertSet('editingVariantId', $later->id);
});

test('detaching a group replays a lost response and removes stale embedded delete controls', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $group = ModifierGroup::factory()->for($branch)->create();
    $option = ModifierOption::factory()->for($group, 'group')->create(['price_delta_cents' => 0]);
    $item->modifierGroups()->attach($group);
    $component = Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id]);
    $retry = clone $component;
    $component->call('detachModifierGroupFromItem', $item->id, $group->id)->assertOk()->assertHasNoErrors();
    $retry->call('detachModifierGroupFromItem', $item->id, $group->id)->assertOk()->assertHasNoErrors();
    $component->call('deleteModifierOption', $option->id)->assertForbidden();
    expect($group->fresh())->not->toBeNull()->and($option->fresh())->not->toBeNull()
        ->and($item->modifierGroups()->exists())->toBeFalse();
});
