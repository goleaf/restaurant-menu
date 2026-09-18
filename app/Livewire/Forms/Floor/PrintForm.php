<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Floor;

use App\Enums\QrLabelPreset;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PrintForm extends Form
{
    public mixed $preset = 'minimal';

    public mixed $locale = 'en';

    public mixed $printTableNumber = false;

    /** @return array{preset:string,locale:string,printTableNumber:bool} */
    public function payload(): array
    {
        $data = $this->validate(['preset' => ['required', 'string', Rule::enum(QrLabelPreset::class)],
            'locale' => ['required', 'string', Rule::in(['en', 'lt', 'ru'])], 'printTableNumber' => ['required', 'boolean']],
            attributes: ['preset' => __('floor.print.preset'), 'locale' => __('floor.print.locale'), 'printTableNumber' => __('floor.print.number')]);

        return ['preset' => $data['preset'], 'locale' => $data['locale'], 'printTableNumber' => (bool) $data['printTableNumber']];
    }
}
