<?php

declare(strict_types=1);

use App\Actions\Menus\ReorderMenuItemImagesAction;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function imageReorderContext(int $count = 3): array
{
    $item = MenuItem::factory()->create(['image' => 'media/primary.jpg']);
    $item->load('menu.branch');
    $images = MenuItemImage::factory()->count($count)->for($item, 'item')->sequence(fn ($sequence): array => ['sort_order' => $sequence->index])->create();

    return [$item->menu->branch, $item, $images];
}

test('secondary reorder preserves primary and paths and is idempotent', function (int $count): void {
    [$branch, $item, $images] = imageReorderContext($count);
    $ids = $images->modelKeys();
    $reversed = array_reverse($ids);
    $paths = $images->pluck('path', 'id')->all();
    $result = app(ReorderMenuItemImagesAction::class)->handle($branch, $item, $reversed);

    expect($result->image)->toBe('media/primary.jpg')
        ->and($result->galleryImages->modelKeys())->toBe($reversed)
        ->and($result->galleryImages->pluck('sort_order')->all())->toBe($count === 0 ? [] : range(0, $count - 1))
        ->and($result->galleryImages->pluck('path', 'id')->all())->toEqual($paths);

    MenuItemImage::updating(function (): never {
        throw new RuntimeException('Repeated order must not update rows.');
    });
    expect(app(ReorderMenuItemImagesAction::class)->handle($branch, $item, $reversed)->galleryImages->modelKeys())->toBe($reversed);
})->with([0, 1, 3, 7]);

test('secondary reorder rejects malformed or incomplete permutations without writes', function (string $case): void {
    [$branch, $item, $images] = imageReorderContext();
    $ids = $images->modelKeys();
    $invalid = match ($case) {
        'missing' => array_slice($ids, 1),
        'duplicate' => [$ids[0], $ids[0], $ids[2]],
        'foreign' => [$ids[0], $ids[1], MenuItemImage::factory()->create()->id],
        'string' => [(string) $ids[0], $ids[1], $ids[2]],
        'boolean' => [true, $ids[1], $ids[2]],
        'float' => [(float) $ids[0], $ids[1], $ids[2]],
        'associative' => ['a' => $ids[0], 'b' => $ids[1], 'c' => $ids[2]],
        'too many' => range(1, 8),
        default => [],
    };
    MenuItemImage::updating(function (): never {
        throw new RuntimeException('Invalid reorder attempted a write.');
    });

    expect(fn () => app(ReorderMenuItemImagesAction::class)->handle($branch, $item, $invalid))->toThrow(ValidationException::class);
    expect($item->galleryImages()->pluck('id')->all())->toBe($ids)
        ->and($item->fresh()->image)->toBe('media/primary.jpg');
})->with(['missing', 'duplicate', 'foreign', 'string', 'boolean', 'float', 'associative', 'too many', 'empty']);

test('secondary reorder refuses foreign or archived item scope', function (bool $archived): void {
    [$branch, $item, $images] = imageReorderContext();
    if ($archived) {
        $item->delete();
    } else {
        [$branch] = imageReorderContext();
    }

    $before = $item->galleryImages()->pluck('id')->all();
    expect(fn () => app(ReorderMenuItemImagesAction::class)->handle($branch, $item, array_reverse($images->modelKeys())))->toThrow(InvalidArgumentException::class);
    expect($item->galleryImages()->pluck('id')->all())->toBe($before);
})->with([false, true]);

test('secondary reorder rolls back every position when a write is vetoed', function (int $vetoAt): void {
    [$branch, $item, $images] = imageReorderContext();
    $writes = 0;
    MenuItemImage::updating(function () use (&$writes, $vetoAt): bool {
        return ++$writes !== $vetoAt;
    });

    expect(fn () => app(ReorderMenuItemImagesAction::class)->handle($branch, $item, array_reverse($images->modelKeys())))->toThrow(RuntimeException::class);
    expect($item->galleryImages()->pluck('id')->all())->toBe($images->modelKeys())
        ->and($item->galleryImages()->pluck('sort_order')->all())->toBe([0, 1, 2]);
})->with([2, 4, 6]);

test('secondary reorder respects enclosing rollback and retry', function (): void {
    [$branch, $item, $images] = imageReorderContext();
    $ids = $images->modelKeys();
    expect(fn () => DB::transaction(function () use ($branch, $item, $ids): never {
        app(ReorderMenuItemImagesAction::class)->handle($branch, $item, array_reverse($ids));
        throw new RuntimeException('Later failure.');
    }))->toThrow(RuntimeException::class, 'Later failure.');
    expect($item->galleryImages()->pluck('id')->all())->toBe($ids);
    expect(app(ReorderMenuItemImagesAction::class)->handle($branch, $item, array_reverse($ids))->galleryImages->modelKeys())->toBe(array_reverse($ids));
});
