<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Closure;

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
        $this->deleteLocalMediaFile->handle($oldPath);
    }
}
