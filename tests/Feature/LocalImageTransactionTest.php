<?php

declare(strict_types=1);

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Models\Branch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('image replacement waits for the enclosing transaction to commit before deleting the original', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;

    $newPath = DB::transaction(function () use ($branch, $oldPath): string {
        $newPath = replaceLocalImageForTransactionBranch($branch);

        Storage::disk('public')->assertExists([$oldPath, $newPath]);
        expect($branch->fresh()->logo_path)->toBe($newPath);

        return $newPath;
    });

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
    expect($branch->fresh()->logo_path)->toBe($newPath);
});

test('image replacement removes the new file and preserves the original when the outer transaction rolls back', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;
    $newPath = null;

    expect(function () use ($branch, &$newPath): void {
        DB::transaction(function () use ($branch, &$newPath): never {
            $newPath = replaceLocalImageForTransactionBranch($branch);

            throw new RuntimeException('Rollback after the image was persisted.');
        });
    })->toThrow(RuntimeException::class, 'Rollback after the image was persisted.');

    Storage::disk('public')->assertExists($oldPath);
    Storage::disk('public')->assertMissing($newPath);
    expect($branch->fresh()->logo_path)->toBe($oldPath);
});

test('a committed nested image replacement still cleans its new file when its parent rolls back', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;
    $newPath = null;

    expect(function () use ($branch, &$newPath): void {
        DB::transaction(function () use ($branch, &$newPath): never {
            $newPath = DB::transaction(fn (): string => replaceLocalImageForTransactionBranch($branch));

            throw new RuntimeException('Rollback the parent transaction.');
        });
    })->toThrow(RuntimeException::class, 'Rollback the parent transaction.');

    Storage::disk('public')->assertExists($oldPath);
    Storage::disk('public')->assertMissing($newPath);
    expect($branch->fresh()->logo_path)->toBe($oldPath);
});

test('rolling back only a nested replacement preserves an earlier replacement that the parent commits', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;
    $discardedPath = null;

    $committedPath = DB::transaction(function () use ($branch, &$discardedPath): string {
        $committedPath = replaceLocalImageForTransactionBranch($branch);

        expect(function () use ($branch, &$discardedPath): void {
            DB::transaction(function () use ($branch, &$discardedPath): never {
                $discardedPath = replaceLocalImageForTransactionBranch($branch);

                throw new RuntimeException('Rollback the nested replacement.');
            });
        })->toThrow(RuntimeException::class, 'Rollback the nested replacement.');

        Storage::disk('public')->assertExists($committedPath);
        Storage::disk('public')->assertMissing($discardedPath);
        expect($branch->fresh()->logo_path)->toBe($committedPath);

        return $committedPath;
    });

    Storage::disk('public')->assertMissing([$oldPath, $discardedPath]);
    Storage::disk('public')->assertExists($committedPath);
    expect($branch->fresh()->logo_path)->toBe($committedPath);
});

test('image removal deletes the original only after the enclosing transaction commits', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;

    DB::transaction(function () use ($branch, $oldPath): void {
        removeLocalImageForTransactionBranch($branch);

        Storage::disk('public')->assertExists($oldPath);
        expect($branch->fresh()->logo_path)->toBeNull();
    });

    Storage::disk('public')->assertMissing($oldPath);
    expect($branch->fresh()->logo_path)->toBeNull();
});

test('image removal preserves the original when the enclosing transaction rolls back', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;

    expect(fn () => DB::transaction(function () use ($branch): never {
        removeLocalImageForTransactionBranch($branch);

        throw new RuntimeException('Rollback the removal.');
    }))->toThrow(RuntimeException::class, 'Rollback the removal.');

    Storage::disk('public')->assertExists($oldPath);
    expect($branch->fresh()->logo_path)->toBe($oldPath);
});

test('nested removal rollback discards its deferred deletion while the outer transaction commits', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;

    DB::transaction(function () use ($branch): void {
        expect(fn () => DB::transaction(function () use ($branch): never {
            removeLocalImageForTransactionBranch($branch);

            throw new RuntimeException('Rollback the nested removal.');
        }))->toThrow(RuntimeException::class, 'Rollback the nested removal.');
    });

    Storage::disk('public')->assertExists($oldPath);
    expect($branch->fresh()->logo_path)->toBe($oldPath);
});

test('failed replacement persistence removes only its new file even if the parent continues', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;

    DB::transaction(function () use ($oldPath): void {
        expect(fn () => app(ReplaceLocalImageAction::class)->handle(
            UploadedFile::fake()->image('replacement.png'),
            'media/transaction-test',
            $oldPath,
            function (): never {
                throw new RuntimeException('Persistence failed.');
            },
        ))->toThrow(RuntimeException::class, 'Persistence failed.');

        expect(Storage::disk('public')->allFiles('media/transaction-test'))->toBe([$oldPath]);
    });

    expect(Storage::disk('public')->allFiles('media/transaction-test'))->toBe([$oldPath]);
    expect($branch->fresh()->logo_path)->toBe($oldPath);
});

test('replacement and removal retain immediate cleanup without an application transaction', function (): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;

    $newPath = replaceLocalImageForTransactionBranch($branch);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);

    removeLocalImageForTransactionBranch($branch);

    Storage::disk('public')->assertMissing($newPath);
    expect($branch->fresh()->logo_path)->toBeNull();
});

test('image helpers also clean files immediately on a connection with no transaction at all', function (): void {
    $defaultConnection = DB::getDefaultConnection();
    $connectionName = 'local-image-without-transaction';
    config(["database.connections.{$connectionName}" => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'foreign_key_constraints' => true,
    ]]);
    DB::setDefaultConnection($connectionName);

    try {
        expect(DB::connection()->transactionLevel())->toBe(0)
            ->and(DB::connection()->getPdo()->inTransaction())->toBeFalse();
        $oldPath = 'media/transaction-test/original.png';
        $persistedPath = $oldPath;
        Storage::disk('public')->put($oldPath, 'original image');

        $newPath = app(ReplaceLocalImageAction::class)->handle(
            UploadedFile::fake()->image('replacement.png'),
            'media/transaction-test',
            $oldPath,
            function (string $path) use (&$persistedPath): void {
                $persistedPath = $path;
            },
        );

        expect($persistedPath)->toBe($newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);

        app(RemoveLocalImageAction::class)->handle(
            $newPath,
            function () use (&$persistedPath): void {
                $persistedPath = null;
            },
        );

        expect($persistedPath)->toBeNull();
        Storage::disk('public')->assertMissing($newPath);
    } finally {
        DB::purge($connectionName);
        DB::setDefaultConnection($defaultConnection);
    }
});

test('old file cleanup failures never delete a successfully persisted replacement', function (bool $withinTransaction): void {
    $branch = createLocalImageTransactionBranch();
    $oldPath = $branch->logo_path;
    $disk = Storage::disk('public');
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('delete')->with($oldPath)->once()
        ->andThrow(new RuntimeException('Old file cleanup failed.'));
    Storage::shouldReceive('disk')->with('public')->andReturn($failingDisk);

    $replace = fn (): string => replaceLocalImageForTransactionBranch($branch);

    expect(fn () => $withinTransaction ? DB::transaction($replace) : $replace())
        ->toThrow(RuntimeException::class, 'Old file cleanup failed.');

    $newPath = $branch->fresh()->logo_path;
    expect($newPath)->not->toBe($oldPath);
    $disk->assertExists([$oldPath, $newPath]);
})->with(['enclosing transaction' => true, 'no application transaction' => false]);

function createLocalImageTransactionBranch(): Branch
{
    $oldPath = 'media/transaction-test/original.png';
    Storage::disk('public')->put($oldPath, 'original image');

    return Branch::factory()->create(['logo_path' => $oldPath]);
}

function replaceLocalImageForTransactionBranch(Branch $branch): string
{
    return app(ReplaceLocalImageAction::class)->handle(
        UploadedFile::fake()->image('replacement.png'),
        'media/transaction-test',
        $branch->logo_path,
        function (string $path) use ($branch): void {
            $branch->forceFill(['logo_path' => $path])->saveOrFail();
        },
    );
}

function removeLocalImageForTransactionBranch(Branch $branch): void
{
    app(RemoveLocalImageAction::class)->handle(
        $branch->logo_path,
        function () use ($branch): void {
            $branch->forceFill(['logo_path' => null])->saveOrFail();
        },
    );
}
