<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Availability;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Form;

final class StopListFilterForm extends Form
{
    #[Url(as: 'q', history: true)]
    public mixed $search = '';

    #[Url(as: 'restriction', history: true)]
    public mixed $state = '';

    #[Url(as: 'page', history: true, except: 1)]
    public mixed $page = 1;

    /** @return array{search:string,state:string,page:int} */
    public function validatedFilters(): array
    {
        $data = Validator::make(['filters' => $this->all()], [
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.state' => ['nullable', 'string', Rule::in(['', 'stopped', 'hidden', 'unrestricted'])],
            'filters.page' => ['required', 'numeric', 'integer', 'min:1', 'max:10000'],
        ], [], ['filters.search' => __('availability.search_items'), 'filters.state' => __('availability.restriction_filter'), 'filters.page' => __('availability.page')])->validate()['filters'];

        return ['search' => trim((string) $data['search']), 'state' => (string) $data['state'], 'page' => (int) $data['page']];
    }
}
