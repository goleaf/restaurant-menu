<?php

declare(strict_types=1);

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Menus\UpdateMenuItemImagePresentationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\User;
use App\Support\MenuImagePresentation;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    ParallelTesting::resolveTokenUsing(fn (): string => 'image-presentation-'.getmypid());
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => fake()->unique()->company()]);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($organization)->for($brand)->create();
    $menu = Menu::factory()->for($this->branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $this->item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/presentation-primary.webp']);
    $this->image = MenuItemImage::factory()->for($this->item, 'item')->create(['path' => 'media/presentation-secondary.webp']);
    Storage::disk('public')->put($this->item->image, 'Original primary bytes');
    Storage::disk('public')->put($this->image->path, 'Original secondary bytes');
});

function imagePresentationInput(): array
{
    return ['focal_x' => 25, 'focal_y' => 75, 'translations' => [
        'en' => ['alt' => 'A bowl of soup', 'caption' => "Soup\nwith herbs"],
        'lt' => ['alt' => 'Sriubos dubuo', 'caption' => 'Sriuba su žolelėmis'],
        'ru' => ['alt' => 'Тарелка супа', 'caption' => 'Суп с зеленью'],
    ]];
}

function saveImagePresentation(object $test, ?int $imageId, array $input, ?string $version = null): array
{
    return app(UpdateMenuItemImagePresentationAction::class)->handle(
        $test->actor, $test->branch, $test->item->id, $imageId,
        hash('sha256', $imageId === null ? 'media/presentation-primary.webp' : $test->image->path),
        $version ?? MenuImagePresentation::version(null), $input,
    );
}

test('photo presentation saves three languages without modifying image bytes and replays safely', function (bool $primary): void {
    $imageId = $primary ? null : $this->image->id;
    $input = imagePresentationInput();
    saveImagePresentation($this, $imageId, $input);
    $stored = $primary ? $this->item->fresh()->image_presentation : $this->image->fresh()->presentation;
    expect(MenuImagePresentation::normalize($stored))->toBe($input)
        ->and($stored['revision'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and(Storage::disk('public')->get($this->item->image))->toBe('Original primary bytes')
        ->and(Storage::disk('public')->get($this->image->path))->toBe('Original secondary bytes');
    ($primary ? MenuItem::class : MenuItemImage::class)::updating(fn (): never => throw new RuntimeException('A replay must not write.'));
    expect(saveImagePresentation($this, $imageId, $input))->toBe($input);
})->with([true, false]);

test('photo presentation rejects malformed transport without persistence', function (string $field, mixed $value): void {
    $input = imagePresentationInput();
    data_set($input, $field, $value);
    expect(fn () => saveImagePresentation($this, null, $input))->toThrow(ValidationException::class);
    expect($this->item->fresh()->image_presentation)->toBeNull();
})->with([
    ['focal_x', true], ['focal_x', -1], ['focal_y', 101], ['focal_y', '50.5'],
    ['translations.en.alt', ['bad']], ['translations.ru.caption', str_repeat('x', 1001)],
    ['translations.de', ['alt' => 'Foreign locale']], ['translations.en.html', '<script>'],
]);

test('photo presentation rejects stale values and image identity changes', function (): void {
    saveImagePresentation($this, null, imagePresentationInput());
    $changed = imagePresentationInput();
    $changed['focal_x'] = 80;
    expect(fn () => saveImagePresentation($this, null, $changed))->toThrow(ValidationException::class);
    expect(MenuImagePresentation::normalize($this->item->fresh()->image_presentation))->toBe(imagePresentationInput());
    $this->item->update(['image' => 'media/new-image.webp']);
    expect(fn () => saveImagePresentation($this, null, imagePresentationInput(), MenuImagePresentation::version(imagePresentationInput())))
        ->toThrow(ValidationException::class);
});

test('photo presentation reauthorizes before replay and rejects another tenant or image', function (string $case): void {
    saveImagePresentation($this, null, imagePresentationInput());
    if ($case === 'revoked') {
        $this->actor->organizations()->detach($this->branch->organization_id);
    } elseif ($case === 'branch') {
        $this->branch = Branch::factory()->create();
    } else {
        $this->image = MenuItemImage::factory()->create();
    }
    expect(fn () => saveImagePresentation($this, $case === 'image' ? $this->image->id : null, imagePresentationInput()))
        ->toThrow(AuthorizationException::class);
})->with(['revoked', 'branch', 'image']);

test('photo presentation rolls back on rejected model writes and outer rollback', function (bool $veto): void {
    if ($veto) {
        MenuItemImage::updating(fn (): bool => false);
    }
    expect(fn () => DB::transaction(function () use ($veto): void {
        saveImagePresentation($this, $this->image->id, imagePresentationInput());
        if (! $veto) {
            throw new RuntimeException('Later failure');
        }
    }))->toThrow(RuntimeException::class);
    expect($this->image->fresh()->presentation)->toBeNull()
        ->and(Storage::disk('public')->get($this->image->path))->toBe('Original secondary bytes');
})->with([true, false]);

test('photo metadata follows its file when promoted and removed', function (): void {
    $primary = imagePresentationInput();
    $secondary = imagePresentationInput();
    $secondary['focal_x'] = 90;
    saveImagePresentation($this, null, $primary);
    saveImagePresentation($this, $this->image->id, $secondary);
    app(PromoteMenuItemImageAction::class)->handle($this->branch, $this->item, $this->image);
    expect(MenuImagePresentation::normalize($this->item->fresh()->image_presentation))->toBe($secondary)
        ->and(MenuImagePresentation::normalize($this->image->fresh()->presentation))->toBe($primary);
    app(RemoveMenuItemImageAction::class)->handle($this->branch, $this->item);
    expect(MenuImagePresentation::normalize($this->item->fresh()->image_presentation))->toBe($primary);
    app(RemoveMenuItemImageAction::class)->handle($this->branch, $this->item);
    expect($this->item->fresh()->image_presentation)->toBeNull();
});

test('photo localization sends only selected text and preserves intentional empty captions', function (): void {
    $input = imagePresentationInput();
    $input['translations']['ru']['caption'] = '';
    $input['translations']['ru']['alt'] = '';
    expect(MenuImagePresentation::localized($input, 'ru', 'Название блюда'))->toBe([
        'alt' => 'Название блюда', 'caption' => '', 'object_position' => '25% 75%',
    ])->and(MenuImagePresentation::localized(null, 'lt', 'Patiekalas'))->toBe([
        'alt' => 'Patiekalas', 'caption' => '', 'object_position' => '50% 50%',
    ]);
});

test('photo editor loads one scoped form and preserves invalid translated input before saving', function (): void {
    $component = Livewire::actingAs($this->actor)->test(Catalog::class, [
        'organizationId' => $this->branch->organization_id, 'brandId' => $this->branch->brand_id, 'branchId' => $this->branch->id,
    ])->call('startEditingItem', $this->item->id)
        ->call('editItemImagePresentation', $this->item->id, $this->image->id, hash('sha256', $this->image->path))
        ->assertSet('imagePresentationContext.image_id', $this->image->id)
        ->assertSeeHtml('data-image-presentation-editor')
        ->set('imagePresentationForm.focal_x', '80')
        ->set('imagePresentationForm.translations.ru.alt', str_repeat('я', 251))
        ->call('saveItemImagePresentation')
        ->assertHasErrors(['imagePresentationForm.translations.ru.alt'])
        ->assertDispatched('image-presentation-invalid', locale: 'ru')
        ->assertSet('imagePresentationForm.focal_x', '80');
    $component->set('imagePresentationForm.translations', imagePresentationInput()['translations'])
        ->call('saveItemImagePresentation')
        ->assertHasNoErrors()
        ->assertSet('imagePresentationContext', [])
        ->assertDispatched('image-presentation-closed', itemId: $this->item->id);
    expect($this->image->fresh()->presentation['focal_x'])->toBe(80)
        ->and($this->image->fresh()->presentation['translations'])->toBe(imagePresentationInput()['translations']);
});

test('photo editor cannot overwrite another open photo draft and rejects tampered path identity', function (): void {
    $component = Livewire::actingAs($this->actor)->test(Catalog::class, [
        'organizationId' => $this->branch->organization_id, 'brandId' => $this->branch->brand_id, 'branchId' => $this->branch->id,
    ])->call('startEditingItem', $this->item->id)
        ->call('editItemImagePresentation', $this->item->id, null, hash('sha256', 'wrong-path'))
        ->assertHasErrors(['itemImageUploads.'.$this->item->id])
        ->assertSet('imagePresentationContext', [])
        ->call('editItemImagePresentation', $this->item->id, null, hash('sha256', $this->item->image))
        ->set('imagePresentationForm.translations.lt.caption', 'Nebaigtas tekstas')
        ->call('editItemImagePresentation', $this->item->id, $this->image->id, hash('sha256', $this->image->path))
        ->assertSet('imagePresentationContext.image_id', null)
        ->assertSet('imagePresentationForm.translations.lt.caption', 'Nebaigtas tekstas');
    $this->actor->organizations()->detach($this->branch->organization_id);
    $component->call('saveItemImagePresentation')->assertForbidden();
});

test('photo presentation revision rejects an old request after values return to their initial state', function (): void {
    $firstVersion = MenuImagePresentation::version(null);
    saveImagePresentation($this, null, imagePresentationInput(), $firstVersion);
    $currentVersion = MenuImagePresentation::version($this->item->fresh()->image_presentation);
    saveImagePresentation($this, null, MenuImagePresentation::normalize(null), $currentVersion);
    expect(fn () => saveImagePresentation($this, null, imagePresentationInput(), $firstVersion))->toThrow(ValidationException::class);
    expect(MenuImagePresentation::normalize($this->item->fresh()->image_presentation))->toBe(MenuImagePresentation::normalize(null));
});

test('photo presentation retries a post-commit cache failure without rewriting its saved settings', function (): void {
    $cache = Mockery::mock(ForgetBranchCacheAction::class);
    $calls = 0;
    $cache->shouldReceive('handle')->with($this->branch->id)->twice()->andReturnUsing(function () use (&$calls): void {
        if (++$calls === 1) {
            throw new RuntimeException('Cache failure after commit');
        }
    });
    $this->app->instance(ForgetBranchCacheAction::class, $cache);
    expect(fn () => saveImagePresentation($this, $this->image->id, imagePresentationInput()))->toThrow(RuntimeException::class);
    $saved = $this->image->fresh()->presentation;
    MenuItemImage::updating(fn (): never => throw new RuntimeException('Retry must preserve the committed revision'));
    saveImagePresentation($this, $this->image->id, imagePresentationInput());
    expect($this->image->fresh()->presentation)->toBe($saved)->and($calls)->toBe(2);
});

test('the open photo editor reads legacy image dimensions without rewriting its file', function (): void {
    $file = UploadedFile::fake()->image('legacy.png', 640, 360);
    Storage::disk('public')->put($this->item->image, $file->getContent());
    Livewire::actingAs($this->actor)->test(Catalog::class, [
        'organizationId' => $this->branch->organization_id, 'brandId' => $this->branch->brand_id, 'branchId' => $this->branch->id,
    ])->call('startEditingItem', $this->item->id)
        ->call('editItemImagePresentation', $this->item->id, null, hash('sha256', $this->item->image))
        ->assertSet('imagePresentationContext.width', 640)
        ->assertSet('imagePresentationContext.height', 360);
    expect(Storage::disk('public')->get($this->item->image))->toBe($file->getContent());
});

test('photo focal validation associates native range errors and preserves the other draft fields', function (): void {
    $component = Livewire::actingAs($this->actor)->test(Catalog::class, [
        'organizationId' => $this->branch->organization_id, 'brandId' => $this->branch->brand_id, 'branchId' => $this->branch->id,
    ])->call('startEditingItem', $this->item->id)
        ->call('editItemImagePresentation', $this->item->id, null, hash('sha256', $this->item->image))
        ->set('imagePresentationForm.focal_x', 101)
        ->set('imagePresentationForm.focal_y', -1)
        ->set('imagePresentationForm.translations.lt.caption', 'Išsaugoti įvestą tekstą')
        ->call('saveItemImagePresentation')
        ->assertHasErrors(['imagePresentationForm.focal_x', 'imagePresentationForm.focal_y'])
        ->assertSet('imagePresentationForm.translations.lt.caption', 'Išsaugoti įvestą tekstą');

    $document = new DOMDocument;
    @$document->loadHTML(mb_convert_encoding($component->html(), 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($document);
    foreach (['x', 'y'] as $axis) {
        $id = 'image-focal-'.$axis.'-'.$this->item->id;
        $input = $xpath->query('//*[@id="'.$id.'"]')->item(0);
        expect($input?->getAttribute('aria-describedby'))->toBe($id.'-error')
            ->and($input?->getAttribute('aria-invalid'))->toBe('true');
        expect($xpath->query('//*[@id="'.$id.'-error"]')->item(0)?->textContent)->not->toBeEmpty();
    }
    expect($this->item->fresh()->image_presentation)->toBeNull()
        ->and(Storage::disk('public')->get($this->item->image))->toBe('Original primary bytes');
});
