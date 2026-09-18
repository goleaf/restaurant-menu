<?php

declare(strict_types=1);

use App\Actions\Menus\CreateMenuItemVariantAction;
use App\Actions\Menus\DeleteMenuItemVariantAction;
use App\Actions\Menus\UpdateMenuItemVariantAction;
use App\Livewire\Organizations\Brands\Branches\Menu\Variants;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItemVariant;
use App\Models\MenuOperation;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\DishConfigurationFixtures;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('variant actions reject archived authoring parents without changing history or receipts', function (string $parent, string $operation): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $variant = MenuItemVariant::factory()->for($item, 'item')->withTranslations()->create(['is_default' => true]);
    $version = $item->fresh()->variants_version;
    if ($parent === 'dish') {
        $item->delete();
        expect($item->fresh()->trashed())->toBeTrue();
    } else {
        // A historical archived menu can retain a live child; authoring must still fail closed.
        $item->menu->forceFill(['deleted_at' => now()])->saveOrFail();
        expect($item->menu->fresh()->trashed())->toBeTrue();
    }
    $before = [$item->fresh()->getRawOriginal(), $variant->fresh()->getRawOriginal(), $variant->translations()->get()->toArray()];
    $auditCount = AuditLog::query()->count();
    $requestId = (string) Str::uuid();
    $write = match ($operation) {
        'create' => fn () => app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, variantSafetyData('New size'), $version, $requestId),
        'update' => fn () => app(UpdateMenuItemVariantAction::class)->handle($actor, $branch, $variant, variantSafetyData('Changed size'), $version, $requestId),
        'delete' => fn () => app(DeleteMenuItemVariantAction::class)->handle($actor, $branch, $variant, $version, $requestId),
    };

    expect($write)->toThrow(InvalidArgumentException::class);
    expect([$item->fresh()->getRawOriginal(), $variant->fresh()->getRawOriginal(), $variant->translations()->get()->toArray()])->toBe($before)
        ->and($item->variants()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(MenuOperation::query()->where('request_id', $requestId)->exists())->toBeFalse()
        ->and($variant->fresh()->item->id)->toBe($item->id);
})->with(['dish', 'menu'])->with(['create', 'update', 'delete']);

test('an already open variant cannot commit after its authoring parent is archived', function (string $parent): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $variant = MenuItemVariant::factory()->for($item, 'item')->withTranslations()->create();
    $editor = Livewire::actingAs($actor)->test(Variants::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->call('startEditingVariant', $variant->id)->set('editingVariant.variantName', 'Unconfirmed rename');
    if ($parent === 'dish') {
        $item->delete();
        expect($item->fresh()->trashed())->toBeTrue();
    } else {
        $item->menu->forceFill(['deleted_at' => now()])->saveOrFail();
        expect($item->menu->fresh()->trashed())->toBeTrue();
    }
    $before = $variant->fresh()->getRawOriginal();
    $auditCount = AuditLog::query()->count();
    $requestId = $editor->get('editingRequestId');

    $this->actingAs($actor)->postJson(route('default-livewire.update'), ['components' => [[
        'snapshot' => json_encode($editor->snapshot, JSON_THROW_ON_ERROR),
        'updates' => [],
        'calls' => [['method' => 'updateVariant', 'params' => []]],
    ]]], ['X-Livewire' => ''])->assertNotFound();

    expect($variant->fresh()->getRawOriginal())->toBe($before)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(MenuOperation::query()->where('request_id', $requestId)->exists())->toBeFalse();
})->with(['dish', 'menu']);

test('a signed variant save rejects substitution of its original editing target', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    [$first, $second] = MenuItemVariant::factory()->count(2)->for($item, 'item')->withTranslations()->create()->all();
    $editor = Livewire::actingAs($actor)->test(Variants::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->call('startEditingVariant', $first->id);
    $before = [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()];
    $version = $item->fresh()->variants_version;
    $auditCount = AuditLog::query()->count();
    config(['app.debug' => false]);
    Exceptions::fake();

    $this->actingAs($actor)->postJson(route('default-livewire.update'), ['components' => [[
        'snapshot' => json_encode($editor->snapshot, JSON_THROW_ON_ERROR),
        'updates' => ['editingVariantId' => $second->id, 'editingVariant.variantName' => 'Wrong target write'],
        'calls' => [['method' => 'updateVariant', 'params' => []]],
    ]]], ['X-Livewire' => ''])->assertStatus(419);

    Exceptions::assertReported(CannotUpdateLockedPropertyException::class);
    expect([$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()])->toBe($before)
        ->and($item->fresh()->variants_version)->toBe($version)
        ->and(AuditLog::query()->count())->toBe($auditCount)
        ->and(MenuOperation::query()->count())->toBe(0);
});

test('retained variant drafts follow a same dish menu move without replacing input or conflict baselines', function (bool $concurrentVariantChange): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $variant = MenuItemVariant::factory()->for($item, 'item')->withTranslations()->create();
    $editor = Livewire::actingAs($actor)->test(Variants::class, ['organizationId' => $branch->organization_id,
        'brandId' => $branch->brand_id, 'branchId' => $branch->id, 'itemId' => $item->id])
        ->set('variant.variantName', 'Unsaved new portion')
        ->call('startEditingVariant', $variant->id)->set('editingVariant.variantName', 'Unsaved existing portion');
    $baseline = [$editor->get('createVersion'), $editor->get('editingVersion'), $editor->get('createRequestId'), $editor->get('editingRequestId')];
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item->update(['menu_id' => $menu->id, 'category_id' => $category->id]);
    if ($concurrentVariantChange) {
        $variant->update(['name' => 'Other administrator value']);
    }

    $editor->call('refreshData')->assertOk()->assertSet('variantMenuId', (string) $menu->id)
        ->assertSet('variantItemId', (string) $item->id)
        ->assertSet('variant.variantName', 'Unsaved new portion')
        ->assertSet('editingVariant.variantName', 'Unsaved existing portion');
    expect([$editor->get('createVersion'), $editor->get('editingVersion'), $editor->get('createRequestId'), $editor->get('editingRequestId')])->toBe($baseline);

    $editor->call('updateVariant')->assertOk();
    if ($concurrentVariantChange) {
        $editor->assertHasErrors('configuration')->assertSet('editingVariant.variantName', 'Unsaved existing portion');
        expect($variant->fresh()->name)->toBe('Other administrator value');
    } else {
        $editor->assertHasNoErrors()->assertSet('variant.variantName', 'Unsaved new portion');
        expect($variant->fresh()->name)->toBe('Unsaved existing portion');
    }
})->with([false, true]);

/** @return array<string,mixed> */
function variantSafetyData(string $name): array
{
    return ['type' => 'portion', 'name' => $name, 'price' => '12.50', 'weight' => null, 'volume' => null,
        'is_default' => true, 'is_available' => true, 'sort_order' => 0,
        'translations' => ['en' => $name, 'lt' => 'Porcija', 'ru' => 'Порция']];
}
