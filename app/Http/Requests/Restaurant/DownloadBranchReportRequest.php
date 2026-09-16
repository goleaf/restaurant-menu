<?php

declare(strict_types=1);

namespace App\Http\Requests\Restaurant;

use App\Data\Reports\ReportPeriodInput;
use App\Models\Branch;
use App\Models\User;
use App\Support\Reports\CalendarDateRange;
use App\Support\Validation\Reports\ReportPeriodRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

final class DownloadBranchReportRequest extends FormRequest
{
    private ?CalendarDateRange $resolvedPeriod = null;

    private ?CarbonImmutable $referenceTime = null;

    public function authorize(): bool
    {
        $user = $this->user();
        $branch = $this->route('branch');

        return $user instanceof User && $branch instanceof Branch
            && Gate::forUser($user)->allows('export', $branch);
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        $data = array_intersect_key($this->query->all(), ReportPeriodRules::rules());

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        return $data;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ReportPeriodRules::rules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ReportPeriodRules::attributes();
    }

    /** @return list<ReportPeriodRules> */
    public function after(): array
    {
        return [new ReportPeriodRules($this->branchTimezone(), $this->referenceTime())];
    }

    public function period(): CalendarDateRange
    {
        $data = $this->validated();

        return $this->resolvedPeriod ??= (new ReportPeriodInput($data['date_from'] ?? null, $data['date_to'] ?? null))
            ->resolve($this->branchTimezone(), $this->referenceTime());
    }

    private function branchTimezone(): string
    {
        $branch = $this->route('branch');
        abort_unless($branch instanceof Branch, 403);

        return $branch->timezone;
    }

    private function referenceTime(): CarbonImmutable
    {
        return $this->referenceTime ??= Date::now()->toImmutable();
    }
}
