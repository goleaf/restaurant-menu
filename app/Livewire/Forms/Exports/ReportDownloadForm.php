<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Exports;

use App\Data\Reports\ReportPeriodInput;
use App\Support\Reports\CalendarDateRange;
use App\Support\Validation\Reports\ReportPeriodRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Livewire\Form;

final class ReportDownloadForm extends Form
{
    public mixed $date_from = null;

    public mixed $date_to = null;

    public function fillFromQuery(Request $request): void
    {
        $this->fill(ReportPeriodRules::normalize($request->query->all()));
    }

    public function period(string $timezone): CalendarDateRange
    {
        $this->fill(ReportPeriodRules::normalize($this->all()));
        $now = Date::now()->toImmutable();
        $this->withValidator(fn ($validator) => $validator->after(new ReportPeriodRules($timezone, $now)));
        $data = $this->validate(ReportPeriodRules::rules(), [], ReportPeriodRules::attributes());

        return (new ReportPeriodInput($data['date_from'], $data['date_to']))->resolve($timezone, $now);
    }
}
