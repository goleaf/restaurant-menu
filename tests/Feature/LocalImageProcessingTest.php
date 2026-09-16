<?php

declare(strict_types=1);

use App\Support\Media\LocalImageConstraints;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Menus\DeleteMenuItemAction;
use App\Models\MenuItem;
use App\Support\LocalImageVariants;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'image-processing-'.getmypid());
    Storage::fake('public');
});

test('jpeg is not offered when the runtime cannot normalize its EXIF orientation', function (): void {
    $process = new Process([
        PHP_BINARY, '-d', 'disable_functions=exif_read_data', '-r',
        'require $argv[1]; echo json_encode(App\\Support\\Media\\LocalImageConstraints::allowedExtensions());',
        base_path('vendor/autoload.php'),
    ]);
    $process->mustRun();
    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
        ->not->toContain('jpg', 'jpeg')->toContain('png');
});

test('image processing bounds display and thumbnail dimensions and discards source metadata', function (): void {
    $file = processingImageUpload(2400, 1200, metadata: 'PRIVATE CAMERA LOCATION');
    $path = app(StoreLocalImageAction::class)->handle($file, 'media/processing');
    $stored = Storage::disk('public')->get($path);

    expect(getimagesizefromstring($stored))->toMatchArray([0 => 1600, 1 => 800])
        ->and($stored)->not->toContain('PRIVATE CAMERA LOCATION');

    $variants = LocalImageVariants::forPath($path);
    $thumbnail = LocalImageVariants::paths($path)[1];
    expect(getimagesize(Storage::disk('public')->path($thumbnail)))->toMatchArray([0 => 480, 1 => 240])
        ->and($variants)->toMatchArray(['width' => 1600, 'height' => 800, 'thumbnail_width' => 480, 'thumbnail_height' => 240])
        ->and($variants['srcset'])->toBe(Storage::disk('public')->url($thumbnail).' 480w, '.Storage::disk('public')->url($path).' 1600w')
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2)
        ->and(strlen($stored))->toBeLessThan($file->getSize());
});

test('textured image compression compares the same input with its complete display pair', function (): void {
    $image = imagecreatetruecolor(2400, 1200);
    for ($y = 0; $y < 1200; $y += 4) {
        for ($x = 0; $x < 2400; $x += 4) {
            $color = imagecolorallocate($image, ($x * 7 + $y * 3) % 256, ($x * 3 + $y * 11) % 256, ($x * 13 + $y * 7) % 256);
            imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
        }
    }
    ob_start();
    imagejpeg($image, null, 90);
    $contents = (string) ob_get_clean();
    $file = UploadedFile::fake()->createWithContent('textured.jpg', $contents);
    $path = app(StoreLocalImageAction::class)->handle($file, 'media/textured');
    [$display, $thumbnail] = LocalImageVariants::paths($path);
    $outputBytes = Storage::disk('public')->size($display) + Storage::disk('public')->size($thumbnail);

    expect($outputBytes)->toBeLessThan(strlen($contents))
        ->and(getimagesize(Storage::disk('public')->path($display)))->toMatchArray([0 => 1600, 1 => 800])
        ->and(getimagesize(Storage::disk('public')->path($thumbnail)))->toMatchArray([0 => 480, 1 => 240]);
});

test('small images keep their real dimensions without duplicate thumbnail files', function (): void {
    $path = app(StoreLocalImageAction::class)->handle(processingImageUpload(200, 100), 'media/processing');
    expect(LocalImageVariants::forPath($path))->toMatchArray([
        'width' => 200, 'height' => 100, 'thumbnail_width' => 200, 'thumbnail_height' => 100,
        'thumbnail_url' => Storage::disk('public')->url($path),
    ])->and(Storage::disk('public')->allFiles())->toHaveCount(1);
});

test('legacy and absent image payloads never inspect storage or invent derivatives', function (): void {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('url')->once()->with('media/legacy/missing.jpg')->andReturn('/storage/media/legacy/missing.jpg');
    Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

    expect(LocalImageVariants::forPath('media/legacy/missing.jpg'))->toMatchArray([
        'url' => '/storage/media/legacy/missing.jpg', 'srcset' => null, 'width' => null, 'height' => null,
        'thumbnail_url' => '/storage/media/legacy/missing.jpg',
    ])->and(LocalImageVariants::forPath(null))->toMatchArray(['url' => null, 'srcset' => null])
        ->and(LocalImageVariants::paths('media/legacy/missing.jpg'))->toBe(['media/legacy/missing.jpg']);
});

test('jpeg orientation is applied before metadata is stripped', function (int $orientation, array $expectedCorners): void {
    $file = processingImageUpload(80, 40, orientation: $orientation);
    $path = app(StoreLocalImageAction::class)->handle($file, 'media/orientation');
    $contents = Storage::disk('public')->get($path);
    $image = imagecreatefromstring($contents);
    $width = $orientation >= 5 ? 40 : 80;
    $height = $orientation >= 5 ? 80 : 40;

    expect([imagesx($image), imagesy($image)])->toBe([$width, $height])
        ->and($contents)->not->toContain("Exif\0\0");
    $points = [[5, 5], [$width - 6, 5], [5, $height - 6], [$width - 6, $height - 6]];
    foreach ($points as $index => [$x, $y]) {
        $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
        $actual = match (true) {
            $color['red'] > 180 && $color['green'] > 180 => 'yellow',
            $color['red'] > 180 => 'red',
            $color['green'] > 100 => 'green',
            default => 'blue',
        };
        expect($actual)->toBe($expectedCorners[$index]);
    }
})->with([
    'normal' => [1, ['red', 'green', 'blue', 'yellow']],
    'horizontal' => [2, ['green', 'red', 'yellow', 'blue']],
    'upside down' => [3, ['yellow', 'blue', 'green', 'red']],
    'vertical' => [4, ['blue', 'yellow', 'red', 'green']],
    'transpose' => [5, ['red', 'blue', 'green', 'yellow']],
    'clockwise' => [6, ['blue', 'red', 'yellow', 'green']],
    'transverse' => [7, ['yellow', 'green', 'blue', 'red']],
    'counterclockwise' => [8, ['green', 'yellow', 'red', 'blue']],
]);

test('very narrow portraits do not emit duplicate srcset width descriptors', function (): void {
    $path = app(StoreLocalImageAction::class)->handle(processingImageUpload(1, 2000), 'media/portrait');
    expect(LocalImageVariants::forPath($path)['srcset'])->toBeNull();
    expect(LocalImageVariants::forPath($path))->toMatchArray(['width' => 1, 'height' => 1600, 'thumbnail_width' => 1, 'thumbnail_height' => 480]);
});

test('a recognized image header with corrupt pixel data is rejected without files', function (): void {
    $file = UploadedFile::fake()->image('broken.png', 10, 10);
    $contents = file_get_contents($file->getPathname());
    $file = UploadedFile::fake()->createWithContent('broken.png', substr($contents, 0, 33).substr($contents, -12));
    expect(fn () => app(StoreLocalImageAction::class)->handle($file, 'media/corrupt'))
        ->toThrow(ValidationException::class, __('uploads.errors.invalid_image'));
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('transparent png remains transparent after image processing', function (): void {
    $image = imagecreatetruecolor(800, 400);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 0, 0, 127));
    ob_start();
    imagepng($image);
    $file = UploadedFile::fake()->createWithContent('transparent.png', (string) ob_get_clean());
    $path = app(StoreLocalImageAction::class)->handle($file, 'media/transparent');

    foreach (LocalImageVariants::paths($path) as $variantPath) {
        $output = imagecreatefromstring(Storage::disk('public')->get($variantPath));
        expect(imagecolorsforindex($output, imagecolorat($output, 0, 0))['alpha'])->toBe(127);
    }
});

test('oversized pixel headers are rejected before decoding or storage', function (int $width, int $height): void {
    $file = UploadedFile::fake()->image('huge.png', 10, 10);
    $contents = file_get_contents($file->getPathname());
    $contents = substr_replace($contents, pack('NN', $width, $height), 16, 8);
    $contents = substr_replace($contents, pack('N', crc32(substr($contents, 12, 17))), 29, 4);
    $upload = UploadedFile::fake()->createWithContent('huge.png', $contents);

    expect(fn () => app(StoreLocalImageAction::class)->handle($upload, 'media/guard'))
        ->toThrow(ValidationException::class, __('uploads.errors.dimensions_too_large', ['max_pixels' => 20, 'max_dimension' => 8192]));
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with(['pixel budget' => [8000, 8000], 'edge budget' => [8193, 1]]);

test('low remaining memory rejects processing before a decode allocation', function (): void {
    $file = processingImageUpload(2400, 1200);
    $previous = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(true) + 8 * 1024 * 1024));
    try {
        expect(fn () => app(StoreLocalImageAction::class)->handle($file, 'media/guard'))
            ->toThrow(ValidationException::class, __('uploads.errors.processing_unavailable'));
    } finally {
        ini_set('memory_limit', $previous);
    }
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('refused derivative writes remove every attempted file and retry produces one complete pair', function (bool $throw): void {
    $disk = Storage::disk('public');
    $proxy = Mockery::mock($disk);
    $writes = 0;
    $proxy->shouldReceive('put')->andReturnUsing(function (...$arguments) use ($disk, &$writes, $throw): bool {
        $disk->put(...$arguments);
        if (++$writes === 2) {
            if ($throw) {
                throw new RuntimeException('Storage refused derivative.');
            }

            return false;
        }

        return true;
    });
    Storage::set('public', $proxy);
    $file = processingImageUpload(2400, 1200);

    expect(fn () => app(StoreLocalImageAction::class)->handle($file, 'media/refused'))->toThrow(RuntimeException::class);
    expect($disk->allFiles())->toBe([]);
    Storage::set('public', $disk);
    $path = app(StoreLocalImageAction::class)->handle($file, 'media/refused');
    $disk->assertExists(LocalImageVariants::paths($path));
    expect($disk->allFiles())->toHaveCount(2);
})->with(['false' => false, 'exception' => true]);

test('outer rollback removes both new variants and preserves the previously committed pair', function (): void {
    $old = app(StoreLocalImageAction::class)->handle(processingImageUpload(1200, 600), 'media/rollback');
    $new = null;
    expect(function () use ($old, &$new): void {
        DB::transaction(function () use ($old, &$new): never {
            $new = app(ReplaceLocalImageAction::class)->handle(processingImageUpload(1200, 600), 'media/rollback', $old, fn () => null);
            Storage::disk('public')->assertExists(LocalImageVariants::paths($old));
            throw new RuntimeException('Outer rollback.');
        });
    })->toThrow(RuntimeException::class, 'Outer rollback.');

    Storage::disk('public')->assertExists(LocalImageVariants::paths($old));
    Storage::disk('public')->assertMissing(LocalImageVariants::paths($new));
    expect(Storage::disk('public')->allFiles())->toHaveCount(2);
});

test('parent deletion cleans both variants only after commit and preserves them on rollback', function (): void {
    $item = MenuItem::factory()->create();
    $item->load('menu.branch');
    $item = app(AddMenuItemImagesAction::class)->handle($item->menu->branch, $item, [processingImageUpload(1200, 600)]);
    $paths = LocalImageVariants::paths($item->image);

    expect(fn () => DB::transaction(function () use ($item, $paths): never {
        app(DeleteMenuItemAction::class)->handle($item);
        Storage::disk('public')->assertExists($paths);
        throw new RuntimeException('Keep pair.');
    }))->toThrow(RuntimeException::class, 'Keep pair.');
    Storage::disk('public')->assertExists($paths);
    DB::transaction(function () use ($item, $paths): void {
        app(DeleteMenuItemAction::class)->handle($item->fresh());
        Storage::disk('public')->assertExists($paths);
    });
    Storage::disk('public')->assertMissing($paths);
});

test('post commit deletion failure preserves both variants of the committed replacement', function (bool $throw): void {
    $old = app(StoreLocalImageAction::class)->handle(processingImageUpload(1200, 600), 'media/committed');
    $item = MenuItem::factory()->create(['image' => $old]);
    $disk = Storage::disk('public');
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->withArgs(fn (string $path): bool => $path !== $old)->andReturnUsing(fn (string $path): bool => $disk->delete($path));
    $proxy->shouldReceive('delete')->with($old)->andReturnUsing(function () use ($throw): bool {
        if ($throw) {
            throw new RuntimeException('Old cleanup failed.');
        }

        return false;
    });
    Storage::set('public', $proxy);

    expect(fn () => app(ReplaceLocalImageAction::class)->handle(
        processingImageUpload(1200, 600), 'media/committed', $old,
        fn (string $path): bool => $item->update(['image' => $path]),
    ))->toThrow(RuntimeException::class);

    $current = $item->fresh()->image;
    expect($current)->not->toBe($old);
    $disk->assertExists(LocalImageVariants::paths($current));
    $disk->assertExists($old);
    $disk->assertMissing(LocalImageVariants::paths($old)[1]);
})->with([false, true]);

function processingImageUpload(int $width, int $height, int $orientation = 1, string $metadata = ''): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    $colors = [[255, 0, 0], [0, 180, 0], [0, 0, 255], [255, 255, 0]];
    foreach ($colors as $index => [$red, $green, $blue]) {
        $x = ($index % 2) * intdiv($width, 2);
        $y = intdiv($index, 2) * intdiv($height, 2);
        imagefilledrectangle($image, $x, $y, $x + intdiv($width, 2), $y + intdiv($height, 2), imagecolorallocate($image, $red, $green, $blue));
    }
    ob_start();
    imagejpeg($image, null, 95);
    $contents = (string) ob_get_clean();
    $exif = "Exif\0\0II".pack('vV', 42, 8).pack('v', 1).pack('vvVV', 0x112, 3, 1, $orientation).pack('V', 0);
    $comment = $metadata === '' ? '' : "\xff\xfe".pack('n', strlen($metadata) + 2).$metadata;
    $contents = substr($contents, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.$comment.substr($contents, 2);

    return UploadedFile::fake()->createWithContent('photo.jpg', $contents);
}
