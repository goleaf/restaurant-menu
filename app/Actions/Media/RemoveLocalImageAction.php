<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Closure;
use Illuminate\Support\Facades\DB;

final class RemoveLocalImageAction
{
    public function __construct(
        private readonly DeleteLocalMediaFileAction $deleteLocalMediaFile,
    ) {}

    /**
     * @param  Closure(): void  $persist
     */
    public function handle(?string $oldPath, Closure $persist): void
    {
        $persist();

        $deleteOldFile = fn () => $this->deleteLocalMediaFile->handle($oldPath);
        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit($deleteOldFile);
        } else {
            $deleteOldFile();
        }
    }
}
