<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class ReplaceLocalImageAction
{
    public function __construct(
        private readonly StoreLocalImageAction $storeLocalImage,
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
        private readonly DeleteRolledBackLocalImageAction $deleteRolledBackLocalImage,
    ) {}

    /**
     * @param  Closure(string): void  $persist
     */
    public function handle(UploadedFile $file, string $directory, ?string $oldPath, Closure $persist): string
    {
        return DB::transaction(function () use ($file, $directory, $oldPath, $persist): string {
            $newPath = $this->storeLocalImage->handle($file, $directory);
            $connection = DB::connection();
            $connection->afterRollBack(fn () => $this->deleteRolledBackLocalImage->handle($newPath));

            $persist($newPath);

            $connection->afterCommit(function () use ($oldPath, $newPath): void {
                if ($oldPath !== $newPath) {
                    $this->deleteLocalMediaFile->handle($oldPath);
                }
            });

            return $newPath;
        });
    }
}
