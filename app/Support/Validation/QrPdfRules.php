<?php

declare(strict_types=1);

namespace App\Support\Validation;

use App\Enums\QrLabelPreset;
use App\Models\ServicePoint;
use Illuminate\Validation\Rule;

final class QrPdfRules
{
    public const int MAX_SERVICE_POINTS = 100;

    /** @return array<string, list<mixed>> */
    public static function rules(int $branchId): array
    {
        return [
            'service_points' => ['required', 'array', 'min:1', 'max:'.self::MAX_SERVICE_POINTS],
            'service_points.*' => ['bail', 'required', 'numeric', 'integer', 'distinct', Rule::exists(ServicePoint::class, 'id')->where(fn ($query) => $query->where('branch_id', $branchId)->whereNull('deleted_at'))],
            'preset' => ['required', Rule::enum(QrLabelPreset::class)],
            'print_table_number' => ['required', 'boolean'],
        ];
    }
}
