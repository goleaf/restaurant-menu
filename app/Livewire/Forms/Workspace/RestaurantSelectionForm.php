<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Workspace;

use Illuminate\Support\Facades\Validator;
use Livewire\Form;

final class RestaurantSelectionForm extends Form
{
    public mixed $search = '';

    public mixed $branchId = '';

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['branchId' => ['required', 'numeric', 'integer', 'min:1'], 'search' => ['present', 'string', 'max:100']];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return ['branchId' => __('workspace.restaurant'), 'search' => __('workspace.search')];
    }

    public function searchTerm(): ?string
    {
        $validator = Validator::make(['search' => $this->search], ['search' => ['present', 'string', 'max:100']], [], ['search' => __('workspace.search')]);
        $this->resetValidation('search');
        if ($validator->fails()) {
            $this->addError('search', $validator->errors()->first('search'));

            return null;
        }

        return $validator->validated()['search'];
    }
}
