<?php

declare(strict_types=1);

use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\ContinueMenuOperationAction;
use App\Actions\Menus\DuplicateMenuItemAction;
use App\Actions\Modifiers\AssignModifierGroupToMenuItemAction;
use App\Actions\Modifiers\UnassignModifierGroupFromMenuItemAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuItemVariant;
use App\Models\MenuOperation;
use App\Models\ModifierGroup;
use App\Models\User;
use App\Support\LocalImageVariants;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-duplicate-'.getmypid());
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => fake()->unique()->company()]);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($brand)->for($organization)->create();
    $menu = Menu::factory()->for($this->branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $this->item = MenuItem::factory()->for($menu)->for($category, 'category')->withTranslations()->create();
});

function finishDuplicateOperation(User $actor, Branch $branch, MenuOperation $operation): MenuOperation
{
    for ($step = 0; $step < 30 && ! $operation->progress()['completed']; $step++) {
        $operation = app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id);
    }
    expect($operation->progress()['completed'])->toBeTrue();

    return $operation;
}

test('duplicate builds independent images variants translations and branch owned modifier links', function (): void {
    $sourcePath = app(StoreLocalImageAction::class)->handle(UploadedFile::fake()->image('primary.jpg', 800, 400), 'media/source');
    $galleryPath = app(StoreLocalImageAction::class)->handle(UploadedFile::fake()->image('secondary.png', 800, 400), 'media/source');
    $this->item->update(['image' => $sourcePath]);
    MenuItemImage::factory()->for($this->item, 'item')->create(['path' => $galleryPath]);
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->withTranslations()->create();
    $ownModifier = ModifierGroup::factory()->for($this->branch)->create();
    $foreignModifier = ModifierGroup::factory()->create();
    $this->item->modifierGroups()->attach([$ownModifier->id, $foreignModifier->id]);
    $requestId = Str::uuid()->toString();
    $operation = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $requestId);
    expect(MenuItem::query()->count())->toBe(1)
        ->and($operation->progress()['result_id'])->toBeNull();
    $operation = finishDuplicateOperation($this->actor, $this->branch, $operation);
    $copy = MenuItem::query()->with(['variants.translations', 'translations', 'galleryImages', 'modifierGroups'])->findOrFail($operation->result_id);
    expect($copy->id)->not->toBe($this->item->id)
        ->and($copy->price_cents)->toBe($this->item->price_cents)
        ->and($copy->image)->not->toBe($sourcePath)
        ->and($copy->variants->sole()->id)->not->toBe($variant->id)
        ->and($copy->variants->sole()->translations)->toHaveCount(3)
        ->and($copy->translations->modelKeys())->not->toContain(...$this->item->translations()->pluck('id')->all())
        ->and($copy->modifierGroups->modelKeys())->toBe([$ownModifier->id]);
    Storage::disk('public')->assertExists([...LocalImageVariants::paths($sourcePath), ...LocalImageVariants::paths($galleryPath), ...LocalImageVariants::paths($copy->image), ...LocalImageVariants::paths($copy->galleryImages->sole()->path)]);
    expect(Storage::disk('public')->get($copy->image))->toBe(Storage::disk('public')->get($sourcePath));
    expect(app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $requestId)->id)->toBe($operation->id)
        ->and(MenuItem::query()->count())->toBe(2)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(8);
});

test('large variant copy remains hidden and advances in bounded slices', function (): void {
    MenuItemVariant::factory()->count(80)->for($this->item, 'item')->withTranslations()->create();
    $operation = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, Str::uuid()->toString());
    for ($step = 0; $step < 4; $step++) {
        $previous = $operation->processed_count;
        $operation = app(ContinueMenuOperationAction::class)->handle($this->actor, $this->branch, $operation->request_id);
        expect($operation->processed_count - $previous)->toBeLessThanOrEqual(50);
    }
    expect($operation->progress()['completed'])->toBeFalse()->and(MenuItem::query()->count())->toBe(1);
    $operation = finishDuplicateOperation($this->actor, $this->branch, $operation);
    expect(MenuItem::query()->findOrFail($operation->result_id)->variants()->count())->toBe(80);
});

test('interrupted and refused media copies retain a durable plan and safely resume', function (): void {
    $path = app(StoreLocalImageAction::class)->handle(UploadedFile::fake()->image('source.jpg', 800, 400), 'media/source');
    $this->item->update(['image' => $path]);
    $operation = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, Str::uuid()->toString());
    $target = $operation->pending_cleanup[0];
    $disk = Storage::disk('public');
    $disk->put($target, 'interrupted partial copy');
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('copy')->andReturn(false);
    Storage::set('public', $proxy);
    expect(fn () => app(ContinueMenuOperationAction::class)->handle($this->actor, $this->branch, $operation->request_id))->toThrow(RuntimeException::class);
    expect($operation->fresh()->pending_cleanup)->toBe([$target]);
    Storage::set('public', $disk);
    $operation = finishDuplicateOperation($this->actor, $this->branch, $operation->fresh());
    $copy = MenuItem::query()->findOrFail($operation->result_id);
    expect($disk->get($copy->image))->toBe($disk->get($path));
    $disk->assertExists(LocalImageVariants::paths($path));
});

test('duplicate resume rechecks revoked access and exact UUID target binding', function (): void {
    $requestId = Str::uuid()->toString();
    $operation = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $requestId);
    $foreign = MenuItem::factory()->create();
    expect(fn () => app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $foreign, $requestId))->toThrow(AuthorizationException::class);
    $this->actor->organizations()->detach($this->branch->organization_id);
    expect(fn () => app(ContinueMenuOperationAction::class)->handle($this->actor, $this->branch, $operation->request_id))->toThrow(AuthorizationException::class);
    expect(MenuItem::query()->count())->toBe(2);
});

test('a source edit during copying fails explicitly and cleans only the unpublished copy', function (string $change): void {
    $path = app(StoreLocalImageAction::class)->handle(UploadedFile::fake()->image('source.jpg', 800, 400), 'media/source');
    $this->item->update(['image' => $path]);
    $variant = MenuItemVariant::factory()->for($this->item, 'item')->withTranslations()->create();
    $group = ModifierGroup::factory()->for($this->branch)->create();
    if ($change === 'detach') {
        $this->item->modifierGroups()->attach($group);
    }
    $operation = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, Str::uuid()->toString());
    $targets = $operation->pending_cleanup;
    $operation = app(ContinueMenuOperationAction::class)->handle($this->actor, $this->branch, $operation->request_id);
    match ($change) {
        'item' => $this->item->update(['description' => 'Changed during copy']),
        'translation' => $this->item->translations()->firstOrFail()->update(['name' => 'Changed translation']),
        'variant' => $variant->update(['price_cents' => 9876]),
        'variant translation' => $variant->translations()->firstOrFail()->update(['name' => 'Changed variant']),
        'attach' => app(AssignModifierGroupToMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $group),
        'detach' => app(UnassignModifierGroupFromMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, $group),
    };
    $operation = finishDuplicateOperation($this->actor, $this->branch, $operation);
    expect($operation->phase->value)->toBe('failed')
        ->and($operation->progress()['result_id'])->toBeNull()
        ->and($operation->active_scope)->toBeNull()
        ->and(MenuItem::query()->count())->toBe(1)
        ->and(MenuItem::withTrashed()->findOrFail($operation->result_id)->trashed())->toBeTrue();
    foreach ($targets as $target) {
        Storage::disk('public')->assertMissing(LocalImageVariants::paths($target));
    }
    Storage::disk('public')->assertExists(LocalImageVariants::paths($path));
    $retry = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, Str::uuid()->toString());
    expect(finishDuplicateOperation($this->actor, $this->branch, $retry)->phase->value)->toBe('completed');
})->with(['item', 'translation', 'variant', 'variant translation', 'attach', 'detach']);

test('duplication preserves intentionally blank translated descriptions and image presentation', function (): void {
    $path = app(StoreLocalImageAction::class)->handle(UploadedFile::fake()->image('source.jpg', 800, 400), 'media/source');
    $presentation = ['focal_x' => 17, 'focal_y' => 83, 'translations' => ['ru' => ['alt' => 'Блюдо', 'caption' => '']]];
    $this->item->update(['description' => 'English description', 'image' => $path, 'image_presentation' => $presentation]);
    $this->item->translations()->where('language_code', 'ru')->firstOrFail()->update(['description' => null]);
    $operation = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, Str::uuid()->toString());
    $operation = finishDuplicateOperation($this->actor, $this->branch, $operation);
    $copy = MenuItem::query()->findOrFail($operation->result_id);
    expect($copy->translations()->where('language_code', 'ru')->firstOrFail()->description)->toBeNull()
        ->and($copy->image_presentation)->toBe($presentation);
});

test('pre upgrade duplication receipts accept null presentation but reject later metadata changes', function (bool $changed): void {
    $path = app(StoreLocalImageAction::class)->handle(UploadedFile::fake()->image('legacy.jpg', 800, 400), 'media/source');
    $image = MenuItemImage::factory()->for($this->item, 'item')->create(['path' => $path, 'presentation' => null]);
    $operation = app(DuplicateMenuItemAction::class)->handle($this->actor, $this->branch, $this->item, Str::uuid()->toString());
    $payload = $operation->payload;
    foreach ($payload['source_gallery'] as &$entry) {
        unset($entry['presentation']);
    }
    unset($entry);
    foreach ($payload['media_plan'] as &$entry) {
        unset($entry['presentation']);
    }
    unset($entry);
    $operation->forceFill(['payload' => $payload])->saveOrFail();
    expect($operation->fresh()->payload['source_gallery'][0])->not->toHaveKey('presentation');
    if ($changed) {
        $image->update(['presentation' => ['focal_x' => 10, 'focal_y' => 90]]);
    }
    $result = app(ContinueMenuOperationAction::class)->handle($this->actor, $this->branch, $operation->request_id);
    if ($changed) {
        expect($result->phase)->toBe(MenuOperationPhase::Failed);
    } else {
        $result = finishDuplicateOperation($this->actor, $this->branch, $result);
        expect($result->phase)->toBe(MenuOperationPhase::Completed);
    }
    Storage::disk('public')->assertExists($path);
})->with([false, true]);
