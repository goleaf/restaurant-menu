<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Illuminate\Support\Facades\Log;
use Throwable;

final class DeleteRolledBackLocalImageAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    public function handle(string $path): void
    {
        try {
            $this->deleteLocalMediaFile->handle($path);
        } catch (Throwable $exception) {
            try {
                Log::warning('Unable to clean up a rolled-back image.', [
                    'path' => $path,
                    'exception' => $exception::class,
                ]);
            } catch (Throwable) {
                // Rollback callbacks must return so Laravel can discard transaction state.
            }
        }
    }
}
