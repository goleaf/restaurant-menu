<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Users\UpdateUserDisplayFormatsAction;
use App\Enums\SupportedLocale;
use App\Livewire\Forms\DisplayFormatsForm;
use App\Models\User;
use App\Support\DisplayPreferences;
use App\Support\LocalizedDateFormatter;
use App\Support\LocalizedNumberFormatter;
use App\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class DisplayFormats extends Component
{
    public DisplayFormatsForm $form;

    public function mount(): void
    {
        $this->form->fill(DisplayPreferences::forUser($this->user())->values());
    }

    public function save(UpdateUserDisplayFormatsAction $updateFormats): void
    {
        $user = $this->user();
        $updateFormats->handle($user, $this->form->preferences());
        Flux::toast(variant: 'success', text: __('ui.settings.formats.saved'));
    }

    public function resetToDefaults(): void
    {
        $this->user();
        $this->form->fill(DisplayPreferences::defaults()->values());
        $this->resetValidation();
    }

    #[On('profile-locale-updated')]
    public function refreshLocale(): void
    {
        App::setLocale(SupportedLocale::normalize($this->user()->locale));
    }

    public function render(): View
    {
        $preferences = $this->form->previewPreferences();
        $example = CarbonImmutable::create(2026, 8, 24, 15, 0, 0, 'UTC');
        $dateOptions = ['locale' => __('ui.settings.formats.language_default')];
        foreach (array_slice(DisplayPreferences::DATE_FORMATS, 1) as $format) {
            $dateOptions[$format] = $example->format($format);
        }
        $numberOptions = ['locale' => __('ui.settings.formats.language_default')];
        foreach (array_slice(DisplayPreferences::NUMBER_FORMATS, 1) as $format) {
            $numberOptions[$format] = LocalizedNumberFormatter::decimal(1234.56, 2, DisplayPreferences::from(['number_format' => $format]));
        }

        return view('livewire.settings.display-formats', [
            'dateOptions' => $dateOptions,
            'timeOptions' => [
                'locale' => __('ui.settings.formats.language_default'),
                '24h' => __('ui.settings.formats.hours_24', ['value' => LocalizedDateFormatter::time($example, DisplayPreferences::from(['time_format' => '24h']))]),
                '12h' => __('ui.settings.formats.hours_12', ['value' => LocalizedDateFormatter::time($example, DisplayPreferences::from(['time_format' => '12h']))]),
            ],
            'numberOptions' => $numberOptions,
            'dateTimeExample' => LocalizedDateFormatter::dateTime($example, $preferences),
            'numberExample' => LocalizedNumberFormatter::decimal(1234.56, 2, $preferences),
            'moneyExample' => MoneyFormatter::formatCents(123456, 'EUR', $preferences),
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
