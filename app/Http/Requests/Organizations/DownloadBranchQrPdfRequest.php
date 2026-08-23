<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizations;

use App\Enums\QrLabelPreset;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class DownloadBranchQrPdfRequest extends FormRequest
{
    private const MAX_SERVICE_POINTS = 100;

    public function authorize(): bool
    {
        $user = $this->user();
        $organization = $this->route('organization');
        $brand = $this->route('brand');
        $branch = $this->route('branch');

        if (
            ! $user instanceof User
            || ! $organization instanceof Organization
            || ! $brand instanceof Brand
            || ! $branch instanceof Branch
            || $brand->organization_id !== $organization->id
            || $branch->organization_id !== $organization->id
            || $branch->brand_id !== $brand->id
        ) {
            return false;
        }

        return Gate::forUser($user)->allows('viewAny', [QrCode::class, $branch]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $branch = $this->route('branch');
        $branchId = $branch instanceof Branch ? $branch->id : 0;

        return [
            'service_points' => ['required', 'array', 'min:1', 'max:'.self::MAX_SERVICE_POINTS],
            'service_points.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ServicePoint::class, 'id')->where(
                    fn ($query) => $query
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at'),
                ),
            ],
            'preset' => ['required', Rule::enum(QrLabelPreset::class)],
            'print_table_number' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<int>
     */
    public function servicePointIds(): array
    {
        return collect($this->validated('service_points'))
            ->map(fn (mixed $servicePointId): int => (int) $servicePointId)
            ->values()
            ->all();
    }

    public function preset(): QrLabelPreset
    {
        return QrLabelPreset::from((string) $this->validated('preset'));
    }

    public function shouldPrintTableNumber(): bool
    {
        return $this->boolean('print_table_number');
    }
}
