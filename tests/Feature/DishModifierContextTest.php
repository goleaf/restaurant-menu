<?php

declare(strict_types=1);

use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Livewire\Organizations\Brands\Branches\Menu\Modifiers;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\DishConfigurationFixtures;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

function moveModifierContextDish(User $actor, Branch $branch, MenuItem $item): Menu
{
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    Livewire::actingAs($actor)->test(Dish::class, ['organization' => $branch->organization,
        'brand' => $branch->brand, 'branch' => $branch, 'item' => $item])
        ->set('editingItemForm.itemTranslations.en.name', $item->name)
        ->set('editingItemForm.itemTranslations.lt.name', 'Patiekalas')
        ->set('editingItemForm.itemTranslations.ru.name', 'Блюдо')
        ->set('editingItemForm.itemMenuId', (string) $menu->id)
        ->set('editingItemForm.itemCategoryId', (string) $category->id)
        ->call('saveItem')->assertHasNoErrors()->assertSet('editingItemId', $item->id);
    expect($item->fresh()->menu_id)->toBe($menu->id);

    return $menu;
}

test('retained modifiers follow their saved dish menu while preserving independent drafts and versions', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $group = ModifierGroup::factory()->for($branch)->withTranslations()->create();
    $item->modifierGroups()->attach($group);
    $editor = Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->call('startEditingModifierGroup', $group->id)
        ->set('editingGroup.modifierGroupName', 'Unsaved sauces')
        ->set('editingGroup.modifierGroupTranslations.en', 'Unsaved sauces')
        ->set('option.modifierOptionName', 'Unsubmitted option');
    $version = $editor->get('editingGroupVersion');
    $linksVersion = $editor->get('linksVersion');
    $requests = $editor->get('requestIds');
    $menu = moveModifierContextDish($actor, $branch, $item);

    $editor->dispatch('branch-menu-updated')->assertOk()
        ->assertSet('itemId', $item->id)
        ->assertSet('assignment.modifierItemId', (string) $item->id)
        ->assertSet('assignment.modifierItemMenuId', (string) $menu->id)
        ->assertSet('editingGroup.modifierGroupName', 'Unsaved sauces')
        ->assertSet('option.modifierOptionName', 'Unsubmitted option')
        ->assertSet('editingGroupVersion', $version)->assertSet('linksVersion', $linksVersion)
        ->assertSet('requestIds', $requests)
        ->call('updateModifierGroup')->assertHasNoErrors()
        ->assertSet('option.modifierOptionName', 'Unsubmitted option');
    expect($group->fresh()->name)->toBe('Unsaved sauces')
        ->and($item->fresh()->modifierGroups()->sole()->id)->toBe($group->id);
});

test('a dish menu move does not replace a stale modifier revision with a fresh one', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $group = ModifierGroup::factory()->for($branch)->withTranslations()->create();
    $item->modifierGroups()->attach($group);
    $editor = Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->call('startEditingModifierGroup', $group->id)->set('editingGroup.modifierGroupName', 'Local draft');
    $version = $editor->get('editingGroupVersion');
    $group->update(['name' => 'Changed by another administrator']);
    moveModifierContextDish($actor, $branch, $item);

    $editor->call('refreshData')->assertOk()->assertSet('editingGroupVersion', $version)
        ->call('updateModifierGroup')->assertHasErrors('configuration')
        ->assertSet('editingGroup.modifierGroupName', 'Local draft');
    expect($group->fresh()->name)->toBe('Changed by another administrator');
});

test('embedded modifier parent input cannot select the first or another dish after a menu move', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $editor = Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id]);
    $menu = moveModifierContextDish($actor, $branch, $item);
    $other = MenuItem::factory()->for($menu)->for($item->fresh()->category, 'category')->create(['name' => 'AAA earlier dish']);
    $editor->set('assignment.modifierItemMenuId', (string) $menu->id)->assertOk()
        ->assertSet('itemId', $item->id)->assertSet('assignment.modifierItemId', (string) $item->id);
    $editor->set('assignment.modifierItemId', (string) $other->id)->assertForbidden();
    expect($item->modifierGroups()->count())->toBe(0)->and($other->modifierGroups()->count())->toBe(0);
});

test('an archived dish context rejects modifier deletion before shared data or receipts change', function (string $parent, string $operation): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $group = ModifierGroup::factory()->for($branch)->withTranslations()->create();
    $option = ModifierOption::factory()->for($group, 'group')->create(['price_delta_cents' => 0]);
    $item->modifierGroups()->attach($group);
    $editor = Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id]);
    if ($parent === 'dish') {
        $item->delete();
    } else {
        $item->menu->forceFill(['deleted_at' => now()])->saveOrFail();
    }
    $before = [$group->fresh()->getRawOriginal(), $option->fresh()->getRawOriginal(),
        AuditLog::query()->count(), MenuOperation::query()->count()];

    $this->actingAs($actor)->postJson(route('default-livewire.update'), ['components' => [[
        'snapshot' => json_encode($editor->snapshot, JSON_THROW_ON_ERROR),
        'updates' => [],
        'calls' => [['method' => $operation === 'group' ? 'deleteModifierGroup' : 'deleteModifierOption',
            'params' => [$operation === 'group' ? $group->id : $option->id]]],
    ]]], ['X-Livewire' => ''])->assertNotFound();

    expect([$group->fresh()?->getRawOriginal(), $option->fresh()?->getRawOriginal(),
        AuditLog::query()->count(), MenuOperation::query()->count()])->toBe($before);
})->with(['dish', 'menu'])->with(['group', 'option']);

test('a forged embedded dish selection is rejected before modifier create or update can commit', function (string $operation): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $other = MenuItem::factory()->for($item->menu)->for($item->category, 'category')->create();
    $group = ModifierGroup::factory()->for($branch)->withTranslations()->create();
    $option = ModifierOption::factory()->for($group, 'group')->withTranslations()->create(['price_delta_cents' => 0]);
    $item->modifierGroups()->attach($group);
    $editor = Livewire::actingAs($actor)->test(Modifiers::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id]);
    if ($operation === 'createModifierGroup') {
        $editor->set('group.modifierGroupName', 'Unconfirmed group')
            ->set('group.modifierGroupTranslations', ['en' => 'Group', 'lt' => 'Grupė', 'ru' => 'Группа']);
    } elseif ($operation === 'updateModifierGroup') {
        $editor->call('startEditingModifierGroup', $group->id)->set('editingGroup.modifierGroupName', 'Unconfirmed group');
    } elseif ($operation === 'createModifierOption') {
        $editor->set('option.modifierOptionName', 'Unconfirmed option')
            ->set('option.modifierOptionTranslations', ['en' => 'Option', 'lt' => 'Pasirinkimas', 'ru' => 'Добавка']);
    } else {
        $editor->call('startEditingModifierOption', $option->id)->set('editingOption.modifierOptionName', 'Unconfirmed option');
    }
    $before = [$group->fresh()->getRawOriginal(), $option->fresh()->getRawOriginal(), $item->fresh()->modifier_links_version,
        ModifierGroup::query()->count(), ModifierOption::query()->count(), AuditLog::query()->count(), MenuOperation::query()->count()];

    $this->actingAs($actor)->postJson(route('default-livewire.update'), ['components' => [[
        'snapshot' => json_encode($editor->snapshot, JSON_THROW_ON_ERROR),
        'updates' => ['assignment.modifierItemId' => (string) $other->id],
        'calls' => [['method' => $operation, 'params' => []]],
    ]]], ['X-Livewire' => ''])->assertForbidden();

    expect([$group->fresh()->getRawOriginal(), $option->fresh()->getRawOriginal(), $item->fresh()->modifier_links_version,
        ModifierGroup::query()->count(), ModifierOption::query()->count(), AuditLog::query()->count(), MenuOperation::query()->count()])->toBe($before)
        ->and($other->modifierGroups()->count())->toBe(0);
})->with(['createModifierGroup', 'updateModifierGroup', 'createModifierOption', 'updateModifierOption']);
