<?php

declare(strict_types=1);

use App\Actions\Menus\ContinueMenuOperationAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\RemoveMenuItemGalleryImageAction;
use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\User;
use App\Support\LocalImageVariants;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    ParallelTesting::resolveTokenUsing(fn (): string => 'image-mutation-replay-'.getmypid());
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => fake()->unique()->company()]);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($organization)->for($brand)->create();
    $this->menu = Menu::factory()->for($this->branch)->active()->create();
    $this->category = MenuCategory::factory()->for($this->menu)->create();
    $this->primaryPath = 'media/'.Str::uuid().'.v1-800x400.webp';
    $this->galleryPath = 'media/'.Str::uuid().'.v1-800x400.webp';
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['image' => $this->primaryPath]);
    $this->galleryImage = MenuItemImage::factory()->for($this->item, 'item')->create(['path' => $this->galleryPath]);
    foreach ([...LocalImageVariants::paths($this->primaryPath), ...LocalImageVariants::paths($this->galleryPath)] as $path) {
        Storage::disk('public')->put($path, 'Fixture image bytes');
    }
});

function invokeImageMutationForReplay(string $kind, Branch $branch, MenuItem $item, MenuItemImage|int $image, string $identity, string $requestId, User $actor): MenuItem
{
    return match ($kind) {
        'primary' => app(RemoveMenuItemImageAction::class)->handle($branch, $item, $identity, $requestId, $actor),
        'gallery' => app(RemoveMenuItemGalleryImageAction::class)->handle($branch, $item, $image, $identity, $requestId, $actor),
        'promote' => app(PromoteMenuItemImageAction::class)->handle($branch, $item, $image, $identity, $requestId, $actor),
    };
}

test('image mutation receipt makes a lost response replay preserve the committed image selection', function (string $kind): void {
    $requestId = (string) Str::uuid();
    $identity = hash('sha256', $kind === 'primary' ? $this->primaryPath : $this->galleryPath);
    expect(fn () => DB::transaction(function () use ($kind, $identity, $requestId): void {
        invokeImageMutationForReplay($kind, $this->branch, $this->item, $this->galleryImage, $identity, $requestId, $this->actor);
        DB::afterCommit(fn () => throw new RuntimeException('Response lost after commit.'));
    }))->toThrow(RuntimeException::class, 'Response lost after commit.');
    $primary = $this->item->fresh()->image;
    $gallery = $this->item->galleryImages()->get()->map->only(['id', 'path', 'sort_order'])->all();
    $files = Storage::disk('public')->allFiles();

    invokeImageMutationForReplay($kind, $this->branch, $this->item, $this->galleryImage, $identity, $requestId, $this->actor);

    expect($this->item->fresh()->image)->toBe($primary)
        ->and($this->item->galleryImages()->get()->map->only(['id', 'path', 'sort_order'])->all())->toBe($gallery)
        ->and(Storage::disk('public')->allFiles())->toBe($files)
        ->and(MenuOperation::query()->count())->toBe(1);
})->with(['primary', 'gallery', 'promote']);

test('image identity rejects a stale confirmation before any image mutation', function (string $kind): void {
    $identity = hash('sha256', $kind === 'primary' ? $this->primaryPath : $this->galleryPath);
    app(PromoteMenuItemImageAction::class)->handle($this->branch, $this->item, $this->galleryImage);
    $files = Storage::disk('public')->allFiles();
    expect(fn () => invokeImageMutationForReplay($kind, $this->branch, $this->item, $this->galleryImage, $identity, (string) Str::uuid(), $this->actor))
        ->toThrow(ValidationException::class);
    expect($this->item->fresh()->image)->toBe($this->galleryPath)
        ->and($this->galleryImage->fresh()->path)->toBe($this->primaryPath)
        ->and(MenuOperation::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe($files);
})->with(['primary', 'gallery', 'promote']);

test('primary removal retries durable failed cleanup without deleting the promoted image', function (): void {
    $requestId = (string) Str::uuid();
    $identity = hash('sha256', $this->primaryPath);
    $disk = Storage::disk('public');
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->with($this->primaryPath)->andReturn(false);
    $proxy->shouldReceive('delete')->withArgs(fn (string $path): bool => $path !== $this->primaryPath)->andReturnUsing(fn (string $path): bool => $disk->delete($path));
    Storage::set('public', $proxy);
    expect(fn () => invokeImageMutationForReplay('primary', $this->branch, $this->item, $this->galleryImage, $identity, $requestId, $this->actor))->toThrow(RuntimeException::class);
    expect($this->item->fresh()->image)->toBe($this->galleryPath);
    $disk->assertExists(LocalImageVariants::paths($this->galleryPath));
    Storage::set('public', $disk);

    invokeImageMutationForReplay('primary', $this->branch, $this->item, $this->galleryImage, $identity, $requestId, $this->actor);

    expect($this->item->fresh()->image)->toBe($this->galleryPath)
        ->and(MenuOperation::query()->sole()->pending_cleanup)->toBe([]);
    $disk->assertExists(LocalImageVariants::paths($this->galleryPath));
    $disk->assertMissing(LocalImageVariants::paths($this->primaryPath));
});

test('promotion receipt prevents an ABA replay from applying the old swap again', function (): void {
    $originalRequest = (string) Str::uuid();
    invokeImageMutationForReplay('promote', $this->branch, $this->item, $this->galleryImage, hash('sha256', $this->galleryPath), $originalRequest, $this->actor);
    invokeImageMutationForReplay('promote', $this->branch, $this->item, $this->galleryImage, hash('sha256', $this->primaryPath), (string) Str::uuid(), $this->actor);
    invokeImageMutationForReplay('promote', $this->branch, $this->item, $this->galleryImage, hash('sha256', $this->galleryPath), $originalRequest, $this->actor);
    expect($this->item->fresh()->image)->toBe($this->primaryPath)
        ->and($this->galleryImage->fresh()->path)->toBe($this->galleryPath)
        ->and(MenuOperation::query()->count())->toBe(2);
});

test('gallery id replay remains valid after the first operation removed its gallery row', function (string $kind): void {
    if ($kind === 'promote') {
        $this->item->update(['image' => null]);
    }
    $requestId = (string) Str::uuid();
    $identity = hash('sha256', $this->galleryPath);
    invokeImageMutationForReplay($kind, $this->branch, $this->item, $this->galleryImage->id, $identity, $requestId, $this->actor);
    expect(MenuItemImage::query()->whereKey($this->galleryImage->id)->exists())->toBeFalse();
    $primary = $this->item->fresh()->image;
    invokeImageMutationForReplay($kind, $this->branch, $this->item, $this->galleryImage->id, $identity, $requestId, $this->actor);
    expect($this->item->fresh()->image)->toBe($primary)->and(MenuOperation::query()->count())->toBe(1);
})->with(['gallery', 'promote']);

test('outer rollback removes the image receipt and preserves all original references and files', function (): void {
    $files = Storage::disk('public')->allFiles();
    expect(fn () => DB::transaction(function (): void {
        invokeImageMutationForReplay('primary', $this->branch, $this->item, $this->galleryImage, hash('sha256', $this->primaryPath), (string) Str::uuid(), $this->actor);
        throw new RuntimeException('Outer rollback.');
    }))->toThrow(RuntimeException::class, 'Outer rollback.');
    expect($this->item->fresh()->image)->toBe($this->primaryPath)
        ->and($this->galleryImage->fresh()->path)->toBe($this->galleryPath)
        ->and(MenuOperation::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe($files);
});

test('image receipt replay reauthorizes and binds the UUID to its item and image identity', function (): void {
    $requestId = (string) Str::uuid();
    $identity = hash('sha256', $this->primaryPath);
    invokeImageMutationForReplay('primary', $this->branch, $this->item, $this->galleryImage, $identity, $requestId, $this->actor);
    $other = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['image' => 'media/other.jpg']);
    expect(fn () => invokeImageMutationForReplay('primary', $this->branch, $other, $this->galleryImage, $identity, $requestId, $this->actor))->toThrow(AuthorizationException::class);
    expect(fn () => invokeImageMutationForReplay('primary', $this->branch, $this->item, $this->galleryImage, hash('sha256', 'another image'), $requestId, $this->actor))->toThrow(AuthorizationException::class);
    $this->actor->organizations()->detach($this->branch->organization_id);
    expect(fn () => invokeImageMutationForReplay('primary', $this->branch, $this->item, $this->galleryImage, $identity, $requestId, $this->actor))->toThrow(AuthorizationException::class);
    expect($this->item->fresh()->image)->toBe($this->galleryPath)->and($other->fresh()->image)->toBe('media/other.jpg');
});

test('fresh operation resume retries image cleanup without entering menu deletion traversal', function (): void {
    $requestId = (string) Str::uuid();
    $disk = Storage::disk('public');
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->andReturn(false);
    Storage::set('public', $proxy);
    expect(fn () => invokeImageMutationForReplay('primary', $this->branch, $this->item, $this->galleryImage, hash('sha256', $this->primaryPath), $requestId, $this->actor))->toThrow(RuntimeException::class);
    $receipt = MenuOperation::query()->where('request_id', $requestId)->firstOrFail();
    expect($receipt->completed_at)->toBeNull()->and($receipt->pending_cleanup)->toBe([$this->primaryPath]);
    expect(fn () => app(ContinueMenuOperationAction::class)->handle($this->actor, $this->branch, $requestId))->toThrow(RuntimeException::class);
    expect($this->menu->fresh()->trashed())->toBeFalse()
        ->and($this->item->fresh()->image)->toBe($this->galleryPath);
    Storage::set('public', $disk);
    $receipt = app(ContinueMenuOperationAction::class)->handle($this->actor, $this->branch, $requestId);
    expect($receipt->progress()['completed'])->toBeTrue()
        ->and($receipt->pending_cleanup)->toBe([])
        ->and($this->menu->fresh()->trashed())->toBeFalse()
        ->and($this->category->fresh()->trashed())->toBeFalse()
        ->and($this->item->fresh()->trashed())->toBeFalse()
        ->and($this->item->fresh()->image)->toBe($this->galleryPath);
    $disk->assertExists(LocalImageVariants::paths($this->galleryPath));
    $disk->assertMissing(LocalImageVariants::paths($this->primaryPath));
});
