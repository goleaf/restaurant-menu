<?php

namespace App\Http\Requests\Restaurant;

use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class DownloadBranchCsvExportRequest extends FormRequest
{
    private const DEFAULT_RANGE_DAYS = 30;

    private const MAX_RANGE_DAYS = 31;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $branch = $this->route('branch');

        return $user instanceof User
            && $branch instanceof Branch
            && Gate::forUser($user)->allows('export', $branch);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $startedAt = $this->exportStartedAt();
                $endedAt = $this->exportEndedAt();

                if ($startedAt->gt($endedAt)) {
                    $validator->errors()->add('date_to', __('validation.after_or_equal', [
                        'attribute' => 'date to',
                        'date' => 'date from',
                    ]));

                    return;
                }

                if ($startedAt->diffInDays($endedAt) > self::MAX_RANGE_DAYS) {
                    $validator->errors()->add('date_to', __('validation.max.numeric', [
                        'attribute' => 'date range',
                        'max' => self::MAX_RANGE_DAYS,
                    ]));
                }
            },
        ];
    }

    public function exportStartedAt(): CarbonImmutable
    {
        $dateFrom = $this->dateInput('date_from');
        $dateTo = $this->dateInput('date_to');

        if ($dateFrom instanceof CarbonImmutable) {
            return $dateFrom->startOfDay();
        }

        if ($dateTo instanceof CarbonImmutable) {
            return $dateTo->subDays(self::DEFAULT_RANGE_DAYS)->startOfDay();
        }

        return Date::now()->toImmutable()->subDays(self::DEFAULT_RANGE_DAYS)->startOfDay();
    }

    public function exportEndedAt(): CarbonImmutable
    {
        $dateFrom = $this->dateInput('date_from');
        $dateTo = $this->dateInput('date_to');

        if ($dateTo instanceof CarbonImmutable) {
            return $dateTo->endOfDay();
        }

        if ($dateFrom instanceof CarbonImmutable) {
            return $dateFrom->addDays(self::DEFAULT_RANGE_DAYS)->endOfDay();
        }

        return Date::now()->toImmutable()->endOfDay();
    }

    private function dateInput(string $key): ?CarbonImmutable
    {
        $value = $this->query($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', trim($value));

        if (! $date instanceof CarbonImmutable) {
            return null;
        }

        return $date;
    }
}
