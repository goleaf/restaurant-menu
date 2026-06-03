<?php

namespace App\Actions\Media;

use Illuminate\Support\Facades\Storage;

class DeleteLocalMediaFileAction
{
    public function handle(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
