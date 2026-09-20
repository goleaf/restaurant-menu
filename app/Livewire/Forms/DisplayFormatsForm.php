<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Support\DisplayPreferences;
use Illuminate\Validation\Rule;
use Livewire\Form;

class DisplayFormatsForm extends Form
{
    public mixed $date_format = 'locale';

    public mixed $time_format = 'locale';

    public mixed $number_format = 'locale';

    public function preferences(): DisplayPreferences
    {
        return DisplayPreferences::from($this->validate([
            'date_format' => ['bail', 'required', 'string', Rule::in(DisplayPreferences::DATE_FORMATS)],
            'time_format' => ['bail', 'required', 'string', Rule::in(DisplayPreferences::TIME_FORMATS)],
            'number_format' => ['bail', 'required', 'string', Rule::in(DisplayPreferences::NUMBER_FORMATS)],
        ], attributes: [
            'date_format' => __('ui.settings.formats.date'),
            'time_format' => __('ui.settings.formats.time'),
            'number_format' => __('ui.settings.formats.number'),
        ]));
    }

    public function previewPreferences(): DisplayPreferences
    {
        return DisplayPreferences::from($this->only(['date_format', 'time_format', 'number_format']));
    }
}
