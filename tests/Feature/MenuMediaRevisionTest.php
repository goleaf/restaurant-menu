<?php

declare(strict_types=1);

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Menus\PromoteMenuItemImageAction;
use App\Actions\Menus\ReorderMenuItemImagesAction;
use App\Actions\Menus\UpdateMenuItemImagePresentationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\User;
use App\Support\BranchReportCacheVersion;
use App\Support\MenuImagePresentation;
use App\Support\MenuItemMediaState;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Independent media revisions']);
    $this->branch = Branch::factory()->for($organization)->create();
    $menu = Menu::factory()->for($this->branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->active()->create();
    $this->item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/revision-primary.png']);
});

test('gallery order rejects a stale new operation after the same image set returns to its original order', function (): void {
    $images = MenuItemImage::factory()->count(3)->for($this->item, 'item')->sequence(fn ($sequence): array => ['sort_order' => $sequence->index])->create();
    $original = $images->modelKeys();
    $snapshot = MenuItemMediaState::fingerprint($this->item->fresh()->load('galleryImages'));
    $action = app(ReorderMenuItemImagesAction::class);
    $action->handle($this->actor, $this->branch, $this->item, array_reverse($original), $snapshot, (string) Str::uuid());
    $changed = MenuItemMediaState::fingerprint($this->item->fresh()->load('galleryImages'));
    $action->handle($this->actor, $this->branch, $this->item, $original, $changed, (string) Str::uuid());
    expect(MenuItemMediaState::fingerprint($this->item->fresh()->load('galleryImages')))->not->toBe($snapshot);
    $receipts = MenuOperation::query()->count();

    expect(fn () => $action->handle($this->actor, $this->branch, $this->item, array_reverse($original), $snapshot, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($this->item->galleryImages()->pluck('id')->all())->toBe($original)
        ->and(MenuOperation::query()->count())->toBe($receipts);
});

test('completed image reorder replays its exact request without reversing a later order and reauthorizes', function (): void {
    $images = MenuItemImage::factory()->count(2)->for($this->item, 'item')->sequence(fn ($sequence): array => ['sort_order' => $sequence->index])->create();
    $ids = $images->modelKeys();
    $snapshot = MenuItemMediaState::fingerprint($this->item->fresh()->load('galleryImages'));
    $requestId = (string) Str::uuid();
    $action = app(ReorderMenuItemImagesAction::class);
    $action->handle($this->actor, $this->branch, $this->item, array_reverse($ids), $snapshot, $requestId);
    $action->handle($this->actor, $this->branch, $this->item, $ids, MenuItemMediaState::fingerprint($this->item->fresh()->load('galleryImages')), (string) Str::uuid());
    $version = $this->item->fresh()->media_version;
    $action->handle($this->actor, $this->branch, $this->item, array_reverse($ids), $snapshot, $requestId);
    expect($this->item->galleryImages()->pluck('id')->all())->toBe($ids)
        ->and($this->item->fresh()->media_version)->toBe($version)
        ->and(MenuOperation::query()->count())->toBe(2);
    expect(fn () => $action->handle($this->actor, $this->branch, $this->item, $ids, $snapshot, $requestId))->toThrow(AuthorizationException::class);
    $this->actor->organizations()->detach($this->branch->organization_id);
    expect(fn () => $action->handle($this->actor, $this->branch, $this->item, array_reverse($ids), $snapshot, $requestId))->toThrow(AuthorizationException::class);
});

test('gallery mutations advance their own version while metadata and content edits leave it unchanged', function (): void {
    $before = $this->item->fresh()->media_version;
    $image = MenuItemImage::factory()->for($this->item, 'item')->create();
    expect($this->item->fresh()->media_version)->toBeGreaterThan($before);
    $createdVersion = $this->item->fresh()->media_version;
    $image->update(['sort_order' => $image->sort_order + 1]);
    expect($this->item->fresh()->media_version)->toBeGreaterThan($createdVersion);
    $orderedVersion = $this->item->fresh()->media_version;
    $image->update(['path' => 'media/changed-secondary.png']);
    expect($this->item->fresh()->media_version)->toBeGreaterThan($orderedVersion);
    $pathVersion = $this->item->fresh()->media_version;
    $image->update(['presentation' => ['focal_x' => 10]]);
    $this->item->update(['name' => 'Unrelated dish name']);
    expect($this->item->fresh()->media_version)->toBe($pathVersion);
    $image->delete();
    expect($this->item->fresh()->media_version)->toBeGreaterThan($pathVersion);
});

test('gallery revision and completed receipt roll back together when receipt persistence is vetoed', function (): void {
    $images = MenuItemImage::factory()->count(2)->for($this->item, 'item')->sequence(fn ($sequence): array => ['sort_order' => $sequence->index])->create();
    $snapshot = MenuItemMediaState::fingerprint($this->item->fresh()->load('galleryImages'));
    MenuOperation::creating(fn (): bool => false);
    expect(fn () => app(ReorderMenuItemImagesAction::class)->handle($this->actor, $this->branch, $this->item, array_reverse($images->modelKeys()), $snapshot, (string) Str::uuid()))
        ->toThrow(RuntimeException::class);
    expect(MenuItemMediaState::fingerprint($this->item->fresh()->load('galleryImages')))->toBe($snapshot)
        ->and(MenuOperation::query()->count())->toBe(0);
});

test('a new metadata request cannot claim success with a stale revision even when its values equal the current state', function (): void {
    $action = app(UpdateMenuItemImagePresentationAction::class);
    $identity = hash('sha256', $this->item->image);
    $initial = MenuImagePresentation::normalize(null);
    $initialVersion = MenuImagePresentation::version(null);
    $changed = [...$initial, 'focal_x' => 25];
    $action->handle($this->actor, $this->branch, $this->item->id, null, $identity, $initialVersion, $changed, (string) Str::uuid());
    $changedVersion = MenuImagePresentation::version($this->item->fresh()->image_presentation);
    $action->handle($this->actor, $this->branch, $this->item->id, null, $identity, $changedVersion, $initial, (string) Str::uuid());
    $returned = $this->item->fresh()->image_presentation;

    expect(fn () => $action->handle($this->actor, $this->branch, $this->item->id, null, $identity, $initialVersion, $initial, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($this->item->fresh()->image_presentation)->toBe($returned);
});

test('gallery creation invalidates its own branch generation and primary promotion refreshes a warm guest payload', function (): void {
    $otherBranch = Branch::factory()->create();
    $cache = Cache::store('database');
    $guest = app(GetGuestMenuForBranchAction::class);
    $before = $guest->handle($this->branch->id, 'en');
    $imageUrl = $before['categories'][0]['items'][0]['image_url'];
    $generation = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$this->branch->id]));
    $otherGeneration = BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$otherBranch->id]));
    $image = MenuItemImage::factory()->for($this->item, 'item')->create(['path' => 'media/promotion-new.webp']);
    expect(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$this->branch->id])))->not->toBe($generation)
        ->and(BranchReportCacheVersion::fingerprint($cache, 'guest-menu', collect([$otherBranch->id])))->toBe($otherGeneration);
    $guest->handle($this->branch->id, 'en');
    $version = $this->item->fresh()->media_version;
    app(PromoteMenuItemImageAction::class)->handle($this->branch, $this->item, $image->id,
        hash('sha256', $image->path), (string) Str::uuid(), $this->actor);
    $after = $guest->handle($this->branch->id, 'en');
    expect($after['categories'][0]['items'][0]['image_url'])->not->toBe($imageUrl)
        ->toEndWith('/media/promotion-new.webp')
        ->and($this->item->fresh()->media_version)->toBeGreaterThan($version);
});

test('the dish card rejects media operations aimed at a different dish in the same permitted restaurant', function (string $method): void {
    $other = MenuItem::factory()->create(['menu_id' => $this->item->menu_id, 'category_id' => $this->item->category_id, 'image' => 'media/other-dish.png']);
    $image = MenuItemImage::factory()->for($other, 'item')->create();
    $requestId = (string) Str::uuid();
    $arguments = match ($method) {
        'removeItemImage' => [$other->id, hash('sha256', $other->image), $requestId],
        'removeItemGalleryImage', 'promoteItemImage' => [$other->id, $image->id, hash('sha256', $image->path), $requestId],
        'reorderItemImages' => [$other->id, [$image->id], MenuItemMediaState::fingerprint($other->fresh()->load('galleryImages')), $requestId],
        'editItemImagePresentation' => [$other->id, null, hash('sha256', $other->image)],
        'saveItemImages' => [$other->id],
    };
    Livewire::actingAs($this->actor)->test(Dish::class, [
        'organization' => $this->branch->organization, 'brand' => $this->branch->brand,
        'branch' => $this->branch, 'item' => $this->item,
    ])->call($method, ...$arguments)->assertForbidden();
    expect($other->fresh()->image)->toBe('media/other-dish.png')
        ->and($other->galleryImages()->pluck('id')->all())->toBe([$image->id])
        ->and(MenuOperation::query()->count())->toBe(0);
})->with(['removeItemImage', 'removeItemGalleryImage', 'promoteItemImage', 'reorderItemImages', 'editItemImagePresentation', 'saveItemImages']);

test('a photo presentation receipt veto rolls back the metadata revision as well as the values', function (): void {
    $before = $this->item->fresh()->image_presentation;
    $input = [...MenuImagePresentation::normalize(null), 'focal_x' => 20];
    MenuOperation::creating(fn (): bool => false);
    expect(fn () => app(UpdateMenuItemImagePresentationAction::class)->handle($this->actor, $this->branch, $this->item->id, null,
        hash('sha256', $this->item->image), MenuImagePresentation::version($before), $input, (string) Str::uuid()))->toThrow(RuntimeException::class);
    expect($this->item->fresh()->image_presentation)->toBe($before)
        ->and(MenuOperation::query()->count())->toBe(0);
});
