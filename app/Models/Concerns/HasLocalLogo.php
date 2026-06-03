<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasLocalLogo
{
    public function logoUrl(): ?string
    {
        $logoPath = $this->getAttribute('logo_path');

        if (! is_string($logoPath) || blank($logoPath)) {
            return null;
        }

        return Storage::disk('public')->url($logoPath);
    }
}
