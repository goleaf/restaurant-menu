<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Illuminate\Support\Facades\Storage;

final class DeleteLocalMediaFileAction
{
    public function handle(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
