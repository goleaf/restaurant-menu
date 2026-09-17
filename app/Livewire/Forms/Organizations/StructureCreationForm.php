<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Organizations;

use App\Support\Validation\Organizations\OrganizationRules;
use Livewire\Form;

final class StructureCreationForm extends Form
{
    public mixed $name = '';

    /** @return array{name:string} */
    public function validated(): array
    {
        return $this->validate(OrganizationRules::organizationName(), [], ['name' => __('center.name')]);
    }
}
