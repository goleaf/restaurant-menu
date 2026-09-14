<?php

declare(strict_types=1);

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('cache.stores.database.lock_lottery', [0, 1]);
});

test('guest list prepares responsive primary image metadata without reading storage or gallery records', function (?string $path, ?array $dimensions): void {
    [$branch, , , $item] = guestImageProductContext($path);
    $secondary = MenuItemImage::factory()->for($item, 'item')->create(['path' => 'media/secondary-private-to-detail.jpg']);
    $galleryHydrated = 0;
    MenuItemImage::retrieved(function () use (&$galleryHydrated): void {
        $galleryHydrated++;
    });
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('url')->andReturnUsing(fn (string $imagePath): string => '/storage/'.$imagePath);
    $disk->shouldNotReceive('exists');
    $disk->shouldNotReceive('get');
    $disk->shouldNotReceive('size');
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    $payload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');
    $row = $payload['categories'][0]['items'][0];
    $url = $path === null ? null : '/storage/'.$path;
    $thumbnail = $dimensions !== null && $dimensions[0] > 480
        ? str_replace('.webp', '-thumb.webp', $url)
        : $url;
    expect($row['image_url'])->toBe($url)
        ->and($row['image_variants'])->toBe([
            'url' => $url,
            'srcset' => $thumbnail !== $url ? $thumbnail.' 480w, '.$url.' '.$dimensions[0].'w' : null,
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'thumbnail_url' => $thumbnail,
            'thumbnail_width' => $dimensions === null ? null : min(480, $dimensions[0]),
            'thumbnail_height' => $dimensions === null ? null : ($dimensions[0] > 480 ? 240 : $dimensions[1]),
        ])
        ->and($galleryHydrated)->toBe(0)
        ->and(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->not->toContain($secondary->path);
    MenuItemImage::query()->findOrFail($secondary->id);
    expect($galleryHydrated)->toBe(1);
})->with([
    'processed display pair' => ['media/12345678-1234-4123-8123-123456789012.v1-1600x800.webp', [1600, 800]],
    'small processed image' => ['media/12345678-1234-4123-8123-123456789012.v1-200x100.webp', [200, 100]],
    'legacy image' => ['media/legacy/dish.jpg', null],
    'absent image' => [null, null],
]);

test('guest image payload bypasses old cache generations and centralized invalidation removes them', function (): void {
    [$branch, , , $item] = guestImageProductContext('media/legacy/dish.jpg');
    $cache = Cache::store(GetGuestMenuForBranchAction::cacheStore());
    $oldKey = 'guest-menu:v5:branch:'.$branch->id.':language:en';
    $olderKey = 'guest-menu:v4:branch:'.$branch->id.':language:en';
    $legacyKey = 'guest-menu:branch:'.$branch->id;
    $stale = ['categories' => [['items' => [['id' => $item->id, 'name' => 'Old cached dish', 'image_url' => '/storage/old.jpg']]]]];
    foreach ([$oldKey, $olderKey, $legacyKey] as $key) {
        $cache->put($key, $stale, 60);
    }

    $payload = app(GetGuestMenuForBranchAction::class)->handle($branch->id, 'en');
    expect($payload['categories'][0]['items'][0]['name'])->toBe($item->name)
        ->and($payload['categories'][0]['items'][0])->toHaveKey('image_variants')
        ->and($cache->has(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')))->toBeTrue();
    app(ForgetBranchCacheAction::class)->handle($branch->id);
    foreach ([$oldKey, $olderKey, $legacyKey, GetGuestMenuForBranchAction::cacheKey($branch->id, 'en')] as $key) {
        expect($cache->has($key))->toBeFalse();
    }
});

test('responsive guest list images keep cold queries constant as the menu grows', function (): void {
    [$branch, $menu, $category] = guestImageProductContext('media/12345678-1234-4123-8123-123456789012.v1-1600x800.webp');
    $action = app(GetGuestMenuForBranchAction::class);
    $initialQueries = countDatabaseQueries(fn () => $action->handle($branch->id, 'en'));
    MenuItem::factory()->count(39)->for($menu)->for($category, 'category')->create(['image' => 'media/legacy/another.jpg']);
    Cache::store(GetGuestMenuForBranchAction::cacheStore())->forget(GetGuestMenuForBranchAction::cacheKey($branch->id, 'en'));
    $payload = [];
    $grownQueries = countDatabaseQueries(function () use ($action, $branch, &$payload): void {
        $payload = $action->handle($branch->id, 'en');
    });
    $warmQueries = countDatabaseQueries(fn () => $action->handle($branch->id, 'en'));

    expect($payload['categories'][0]['items'])->toHaveCount(40)
        ->and($grownQueries)->toBe($initialQueries)->toBeLessThanOrEqual(13)
        ->and($warmQueries)->toBeLessThanOrEqual(2);
});

test('guest detail is an accessible modal with keyboard close and gallery controls', function () {
    $template = file_get_contents(resource_path('views/livewire/public-qr/guest-menu.blade.php'));

    expect($template)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('@keydown.escape.window')
        ->toContain('?.focus())')
        ->toContain("__('menu.guest.gallery_previous')")
        ->toContain("__('menu.guest.gallery_next')");
});

/** @return array{Branch, Menu, MenuCategory, MenuItem} */
function guestImageProductContext(?string $path): array
{
    $branch = Branch::factory()->create();
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->active()->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => $path]);

    return [$branch, $menu, $category, $item];
}
