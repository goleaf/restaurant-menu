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
        DB::transaction(function () use ($oldPath, $persist): void {
            $persist();

            DB::afterCommit(fn () => $this->deleteLocalMediaFile->handle($oldPath));
        });
    }
}
