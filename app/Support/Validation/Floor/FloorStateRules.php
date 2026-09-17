<?php

declare(strict_types=1);

namespace App\Support\Validation\Floor;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class FloorStateRules
{
    /** @param array<string,mixed> $state */
    public static function validate(array $state): void
    {
        $id = ['nullable', 'string', 'regex:/\A[1-9][0-9]*\z/'];
        Validator::make($state, [
            'areaType' => ['required', 'string', Rule::in(['all', ...\App\Enums\AreaNodeType::values()])],
            'areaActive' => ['required', 'string', Rule::in(['all', 'active', 'inactive'])],
            'areaSort' => ['required', 'string', Rule::in(['position', 'name_asc', 'name_desc', 'newest', 'oldest'])],
            'point' => $id, 'areaEditor' => $id, 'qrRecord' => $id,
            'panel' => ['nullable', 'string', Rule::in(['properties', 'qr', 'area', 'create-point', 'create-area', 'bulk', 'print', 'generate', 'move'])],
            'areaSearch' => ['nullable', 'string', 'max:100'], 'areaLifecycle' => ['required', 'string', Rule::in(['active', 'archived'])],
        ], attributes: array_fill_keys(array_keys($state), __('floor.fields.selection')))->validate();
    }
}
