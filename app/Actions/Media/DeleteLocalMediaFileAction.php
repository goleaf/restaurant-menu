<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\LocalImageVariants;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class DeleteLocalMediaFileAction
{
    public function handle(?string $path): void
    {
        $failure = null;

        foreach (LocalImageVariants::paths($path) as $variantPath) {
            try {
                if (! Storage::disk('public')->delete($variantPath)) {
                    throw new RuntimeException(__('uploads.errors.not_writable'));
                }
            } catch (Throwable $exception) {
                $failure ??= $exception;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }
}
