<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrPrintBrandingResolver
{
    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    public function columnsWithOptionalLogo(Model $model, array $columns): array
    {
        foreach (['logo_path', 'logo_url'] as $column) {
            if (Schema::hasColumn($model->getTable(), $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param  iterable<Model>  $models
     */
    public function localLogoUrlFor(iterable $models): ?string
    {
        foreach ($models as $model) {
            $logoPath = $model->getAttribute('logo_path');

            if (is_string($logoPath) && filled($logoPath)) {
                return Storage::disk('public')->url($logoPath);
            }

            $logoUrl = $model->getAttribute('logo_url');

            if (is_string($logoUrl) && filled($logoUrl) && Str::startsWith($logoUrl, '/')) {
                return $logoUrl;
            }
        }

        return null;
    }
}
