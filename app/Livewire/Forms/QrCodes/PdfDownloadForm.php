<?php

declare(strict_types=1);

namespace App\Livewire\Forms\QrCodes;

use App\Support\Validation\QrPdfRules;
use Livewire\Form;

final class PdfDownloadForm extends Form
{
    public mixed $service_points = [];

    public mixed $preset = 'minimal';

    public mixed $print_table_number = false;

    /** @return array{service_points: list<int>, preset: string, print_table_number: bool} */
    public function selection(int $branchId, array $servicePointIds, string $preset, bool $printTableNumber): array
    {
        $this->service_points = $servicePointIds;
        $this->preset = $preset;
        $this->print_table_number = $printTableNumber;
        $data = $this->validate(QrPdfRules::rules($branchId));
        $data['service_points'] = array_map(intval(...), $data['service_points']);

        return $data;
    }
}
