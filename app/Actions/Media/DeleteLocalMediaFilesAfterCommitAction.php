<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DeleteLocalMediaFilesAfterCommitAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    /**
     * @param  iterable<string|null>  $paths
     * @param  Closure(): void  $persist
     */
    public function handle(iterable $paths, Closure $persist): void
    {
        $spool = null;

        try {
            foreach ($paths as $path) {
                if (! is_string($path) || blank($path)) {
                    continue;
                }

                if ($spool === null) {
                    $spool = tmpfile();

                    if ($spool === false) {
                        throw new RuntimeException('Unable to create the media cleanup spool.');
                    }
                }

                $record = base64_encode($path)."\n";

                if (fwrite($spool, $record) !== strlen($record)) {
                    throw new RuntimeException('Unable to write the media cleanup spool.');
                }
            }

            if ($spool !== null && (! fflush($spool) || ! rewind($spool))) {
                throw new RuntimeException('Unable to prepare the media cleanup spool.');
            }

            $persist();
        } catch (Throwable $exception) {
            if (is_resource($spool)) {
                fclose($spool);
            }

            throw $exception;
        }

        if ($spool === null) {
            return;
        }

        DB::afterRollBack(static fn () => fclose($spool));
        DB::afterCommit(function () use ($spool): void {
            try {
                while (($record = fgets($spool)) !== false) {
                    $path = base64_decode(rtrim($record, "\r\n"), strict: true);

                    if ($path === false) {
                        throw new RuntimeException('Invalid record in the media cleanup spool.');
                    }

                    $this->deleteLocalMediaFile->handle($path);
                }

                if (! feof($spool)) {
                    throw new RuntimeException('Unable to read the media cleanup spool.');
                }
            } finally {
                fclose($spool);
            }
        });
    }
}
