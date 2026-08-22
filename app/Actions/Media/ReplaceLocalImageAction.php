<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Closure;
use Illuminate\Http\UploadedFile;
use Throwable;

final class ReplaceLocalImageAction
{
    public function __construct(
        private readonly StoreLocalImageAction $storeLocalImage,
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    /**
     * @param  Closure(string): void  $persist
     */
    public function handle(UploadedFile $file, string $directory, ?string $oldPath, Closure $persist): string
    {
        $newPath = $this->storeLocalImage->handle($file, $directory);

        try {
            $persist($newPath);
        } catch (Throwable $exception) {
            $this->deleteLocalMediaFile->handle($newPath);

            throw $exception;
        }

        if ($oldPath !== $newPath) {
            $this->deleteLocalMediaFile->handle($oldPath);
        }

        return $newPath;
    }
}
