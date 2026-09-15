<?php

declare(strict_types=1);

use App\Actions\Media\ReplaceLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Menus\AddMenuItemImagesAction;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Support\LocalImageVariants;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'media-rollback-cleanup-'.getmypid());
    Storage::fake('public');
});

test('a failed rollback deletion does not interrupt cleanup of the remaining uploaded image pairs', function (bool $loggingFails): void {
    $store = app(StoreLocalImageAction::class);
    $primary = $store->handle(UploadedFile::fake()->image('original-primary.png', 800, 400), 'media/rollback-originals');
    $secondary = $store->handle(UploadedFile::fake()->image('original-secondary.png', 800, 400), 'media/rollback-originals');
    $item = MenuItem::factory()->create(['image' => $primary]);
    $originalImage = MenuItemImage::factory()->for($item, 'item')->create(['path' => $secondary]);
    $branch = $item->menu()->with('branch')->firstOrFail()->branch;
    $disk = Storage::disk('public');
    $blockedPath = null;
    $attempted = [];
    $newPaths = [];
    $expectedFailure = new RuntimeException('Late gallery transaction failed.');
    $failure = null;
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->andReturnUsing(function (string $path) use ($disk, &$blockedPath, &$attempted): bool {
        $attempted[] = $path;

        return $path === $blockedPath ? false : $disk->delete($path);
    });
    Storage::set('public', $proxy);
    $warning = Log::shouldReceive('warning')->once()->with(
        'Unable to clean up a rolled-back image.',
        Mockery::on(function (array $context) use (&$blockedPath): bool {
            return $context === ['path' => $blockedPath, 'exception' => RuntimeException::class];
        }),
    );
    if ($loggingFails) {
        $warning->andThrow(new RuntimeException('The log sink is unavailable.'));
    } else {
        $warning->andReturnNull();
    }

    try {
        DB::transaction(function () use ($branch, $item, $originalImage, $expectedFailure, &$newPaths, &$blockedPath): void {
            $result = app(AddMenuItemImagesAction::class)->handle($branch, $item, [
                UploadedFile::fake()->image('first.png', 800, 400),
                UploadedFile::fake()->image('second.png', 800, 400),
                UploadedFile::fake()->image('third.png', 800, 400),
            ]);
            $newPaths = $result->galleryImages->reject(fn (MenuItemImage $image): bool => $image->id === $originalImage->id)
                ->flatMap(fn (MenuItemImage $image): array => LocalImageVariants::paths($image->path))->values()->all();
            $blockedPath = $newPaths[0];

            throw $expectedFailure;
        });
    } catch (Throwable $exception) {
        $failure = $exception;
    } finally {
        Storage::set('public', $disk);
    }

    expect($newPaths)->toHaveCount(6);
    $remaining = array_values(array_filter($newPaths, fn (string $path): bool => $disk->exists($path)));
    expect([
        'original_failure_preserved' => $failure === $expectedFailure,
        'all_cleanup_attempts_completed' => $attempted === $newPaths,
        'remaining_new_files' => $remaining,
        'primary' => $item->fresh()->image,
        'gallery' => $item->galleryImages()->pluck('path')->all(),
    ])->toBe([
        'original_failure_preserved' => true,
        'all_cleanup_attempts_completed' => true,
        'remaining_new_files' => [$blockedPath],
        'primary' => $primary,
        'gallery' => [$secondary],
    ]);
    $disk->assertExists([...LocalImageVariants::paths($primary), ...LocalImageVariants::paths($secondary)]);
})->with(['working logger' => false, 'failing logger' => true]);

test('a rollback cleanup failure cannot carry old media callbacks into the next transaction', function (): void {
    $defaultConnection = DB::getDefaultConnection();
    $connectionName = 'media-rollback-callback-isolation';
    config()->set('database.connections.'.$connectionName, [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'foreign_key_constraints' => true,
    ]);
    DB::setDefaultConnection($connectionName);
    DB::connection()->setTransactionManager(new DatabaseTransactionsManager);
    $disk = Storage::disk('public');
    $originalPath = app(StoreLocalImageAction::class)->handle(
        UploadedFile::fake()->image('original.png', 800, 400), 'media/rollback-callbacks',
    );
    $newPath = null;
    $expectedFailure = new RuntimeException('Late outer transaction failed.');
    $failure = null;
    $sentinelRuns = 0;
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->andReturnUsing(function (string $path) use ($disk, &$newPath): bool {
        return $path === $newPath ? false : $disk->delete($path);
    });
    Storage::set('public', $proxy);

    try {
        try {
            DB::transaction(function () use ($originalPath, $expectedFailure, &$newPath): void {
                $newPath = app(ReplaceLocalImageAction::class)->handle(
                    UploadedFile::fake()->image('replacement.png', 800, 400),
                    'media/rollback-callbacks', $originalPath, static function (string $path): void {},
                );

                throw $expectedFailure;
            });
        } catch (Throwable $exception) {
            $failure = $exception;
        }
        Storage::set('public', $disk);
        DB::transaction(function () use (&$sentinelRuns): void {
            DB::afterCommit(function () use (&$sentinelRuns): void {
                $sentinelRuns++;
            });
        });

        expect([
            'original_failure_preserved' => $failure === $expectedFailure,
            'original_file_survives_next_commit' => $disk->exists($originalPath),
            'sentinel_runs' => $sentinelRuns,
            'transaction_level' => DB::connection()->transactionLevel(),
        ])->toBe([
            'original_failure_preserved' => true,
            'original_file_survives_next_commit' => true,
            'sentinel_runs' => 1,
            'transaction_level' => 0,
        ]);
        $disk->assertExists(LocalImageVariants::paths($originalPath));
    } finally {
        Storage::set('public', $disk);
        DB::purge($connectionName);
        DB::setDefaultConnection($defaultConnection);
    }
});
