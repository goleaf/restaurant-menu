<?php

declare(strict_types=1);

use App\Actions\Media\DeleteLocalMediaFilesAfterCommitAction;
use App\Models\MenuItem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'media-cleanup-spool-'.getmypid());
    Storage::fake('public');
});

test('empty media cleanup persists once without opening a temporary stream', function (): void {
    $before = get_resources('stream');
    $persisted = 0;

    app(DeleteLocalMediaFilesAfterCommitAction::class)->handle([null, '', '   '], function () use ($before, &$persisted): void {
        expect(array_diff_key(get_resources('stream'), $before))->toBe([]);
        $persisted++;
    });

    expect($persisted)->toBe(1);
});

test('media cleanup without an application transaction preserves exact paths and runs after persistence', function (): void {
    $persisted = false;
    $deletedPaths = [];
    $paths = ['media/ordinary.png', "media/line\nbreak.png", "media/byte-\xFF.png", 'media/ordinary.png'];
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('delete')->andReturnUsing(function (string $path) use (&$persisted, &$deletedPaths): bool {
        expect($persisted)->toBeTrue();
        $deletedPaths[] = $path;

        return true;
    });
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    app(DeleteLocalMediaFilesAfterCommitAction::class)->handle($paths, function () use (&$persisted): void {
        $persisted = true;
    });

    expect($deletedPaths)->toBe($paths);
});

test('media cleanup waits for outer commit and discards its temporary file after completion', function (): void {
    $path = 'media/spool/committed.png';
    Storage::disk('public')->put($path, 'original');
    $spoolPath = null;
    $paths = observedMediaCleanupPaths([$path], $spoolPath);

    DB::transaction(function () use ($paths, $path, &$spoolPath): void {
        DB::transaction(function () use ($paths): void {
            app(DeleteLocalMediaFilesAfterCommitAction::class)->handle($paths, static function (): void {});
        });

        Storage::disk('public')->assertExists($path);
        expect($spoolPath)->toBeString()
            ->and(is_file($spoolPath))->toBeTrue();
    });

    Storage::disk('public')->assertMissing($path);
    expect(is_file($spoolPath))->toBeFalse();
});

test('media cleanup discards its spool and preserves files on transaction rollback', function (bool $nested): void {
    $path = 'media/spool/rollback.png';
    Storage::disk('public')->put($path, 'original');
    $spoolPath = null;
    $paths = observedMediaCleanupPaths([$path], $spoolPath);
    $operation = function () use ($paths): never {
        app(DeleteLocalMediaFilesAfterCommitAction::class)->handle($paths, static function (): void {});
        throw new RuntimeException('Roll back media cleanup.');
    };

    if ($nested) {
        DB::transaction(function () use ($operation): void {
            expect(fn () => DB::transaction($operation))->toThrow(RuntimeException::class, 'Roll back media cleanup.');
        });
    } else {
        expect(fn () => DB::transaction($operation))->toThrow(RuntimeException::class, 'Roll back media cleanup.');
    }

    Storage::disk('public')->assertExists($path);
    expect($spoolPath)->toBeString()
        ->and(is_file($spoolPath))->toBeFalse();
})->with(['outer rollback' => false, 'savepoint rollback' => true]);

test('media cleanup abandons a partially collected spool when its source fails before persistence', function (): void {
    $spoolPath = null;
    $persisted = false;
    $paths = (function () use (&$spoolPath): Generator {
        yield from observedMediaCleanupPaths(['media/spool/retained.png'], $spoolPath);
        throw new RuntimeException('Path read failed.');
    })();

    expect(fn () => app(DeleteLocalMediaFilesAfterCommitAction::class)->handle($paths, function () use (&$persisted): void {
        $persisted = true;
    }))->toThrow(RuntimeException::class, 'Path read failed.');

    expect($persisted)->toBeFalse()
        ->and($spoolPath)->toBeString()
        ->and(is_file($spoolPath))->toBeFalse();
});

test('media cleanup preserves original files and closes its spool when persistence fails', function (): void {
    $path = 'media/spool/persist-failure.png';
    Storage::disk('public')->put($path, 'original');
    $spoolPath = null;
    $paths = observedMediaCleanupPaths([$path], $spoolPath);

    expect(fn () => app(DeleteLocalMediaFilesAfterCommitAction::class)->handle($paths, static function (): never {
        throw new RuntimeException('Persistence failed.');
    }))->toThrow(RuntimeException::class, 'Persistence failed.');

    Storage::disk('public')->assertExists($path);
    expect(is_file($spoolPath))->toBeFalse();
});

test('a cleanup exception closes the spool without compensating committed persistence', function (): void {
    $item = MenuItem::factory()->create();
    $spoolPath = null;
    $paths = observedMediaCleanupPaths(['media/spool/cleanup-failure.png'], $spoolPath);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('delete')->once()->andThrow(new RuntimeException('File removal failed.'));
    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    expect(fn () => DB::transaction(function () use ($paths, $item): void {
        app(DeleteLocalMediaFilesAfterCommitAction::class)->handle($paths, function () use ($item): void {
            $item->deleteOrFail();
        });
    }))->toThrow(RuntimeException::class, 'File removal failed.');

    expect($item->fresh()->trashed())->toBeTrue()
        ->and(is_file($spoolPath))->toBeFalse();
});

test('a failed spool write prevents persistence and closes the temporary file', function (string $filterClass): void {
    $filterName = 'restaurant-menu-cleanup-write-'.$filterClass;
    stream_filter_register($filterName, $filterClass);
    $spoolPath = null;
    $persisted = false;
    $before = get_resources('stream');
    $paths = (function () use ($before, $filterName, &$spoolPath): Generator {
        yield 'media/spool/first.png';
        $streams = array_diff_key(get_resources('stream'), $before);
        expect($streams)->toHaveCount(1);
        $spool = reset($streams);
        $spoolPath = stream_get_meta_data($spool)['uri'];
        stream_filter_append($spool, $filterName, STREAM_FILTER_WRITE);
        yield 'media/spool/second.png';
    })();

    expect(fn () => app(DeleteLocalMediaFilesAfterCommitAction::class)->handle($paths, function () use (&$persisted): void {
        $persisted = true;
    }))->toThrow(RuntimeException::class, 'Unable to write the media cleanup spool.');

    expect($persisted)->toBeFalse()
        ->and(is_file($spoolPath))->toBeFalse();
})->with([
    'failed write' => RejectMediaCleanupWritesFilter::class,
    'short write' => ShortMediaCleanupWritesFilter::class,
]);

/**
 * @param  list<string>  $paths
 * @return Generator<int, string>
 */
function observedMediaCleanupPaths(array $paths, ?string &$spoolPath): Generator
{
    $before = get_resources('stream');

    foreach ($paths as $path) {
        yield $path;
        $streams = array_diff_key(get_resources('stream'), $before);
        expect($streams)->toHaveCount(1);
        $spool = reset($streams);
        $spoolPath = stream_get_meta_data($spool)['uri'];
    }
}

final class RejectMediaCleanupWritesFilter extends php_user_filter
{
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;
        }

        return PSFS_ERR_FATAL;
    }
}

final class ShortMediaCleanupWritesFilter extends php_user_filter
{
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = substr($bucket->data, 0, 1);
            $bucket->datalen = 1;
            $consumed++;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
