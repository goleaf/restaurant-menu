<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Branches;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Support\Branches\BranchSettingsGroup;
use App\Support\MoneyFormatter;
use App\Support\Validation\Branches\BranchSettingsGroupRules;
use App\Support\Validation\IndependentSectionValidation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

abstract class SettingsGroupForm extends Form
{
    abstract protected function group(): string;

    /** @return array<string, string> */
    abstract protected function fields(): array;

    public function populate(Branch $branch, BranchSetting $settings): void
    {
        $values = BranchSettingsGroup::values($settings, $this->group());
        foreach ($this->fields() as $property => $column) {
            $this->{$property} = match ($column) {
                'service_charge_percent' => $this->percentageForInput($settings),
                'default_currency' => $branch->currency,
                default => $values[$column],
            };
        }
    }

    /** @return array<string, mixed> */
    public function validatedData(): array
    {
        $name = $this->getPropertyName();
        $values = [];
        $rules = [];
        $attributes = [];
        $groupRules = BranchSettingsGroupRules::for($this->group());
        foreach ($this->fields() as $property => $column) {
            $values[$property] = $this->{$property};
            $rules[$name.'.'.$property] = $groupRules[$column];
            $attributes[$name.'.'.$property] = __('settings.fields.'.$column);
        }
        try {
            $validated = Validator::make([$name => $values], $rules, attributes: $attributes)->validate()[$name];
        } catch (ValidationException $exception) {
            throw IndependentSectionValidation::preserve($exception, $this->getComponent()->getErrorBag()->getMessages(), $name);
        }
        $this->getComponent()->resetValidation(array_keys($rules));
        $result = [];
        foreach ($this->fields() as $property => $column) {
            $result[$column] = $validated[$property];
        }

        return $result;
    }

    private function percentageForInput(BranchSetting $settings): mixed
    {
        $value = $settings->getAttributes()['service_charge_basis_points'] ?? null;

        return is_int($value) ? MoneyFormatter::centsToDecimal($value) : $value;
    }
}
