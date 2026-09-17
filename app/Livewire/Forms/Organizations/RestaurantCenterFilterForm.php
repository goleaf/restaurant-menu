<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Organizations;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Form;

final class RestaurantCenterFilterForm extends Form
{
    #[Url(as: 'view', history: true)]
    public mixed $view = 'restaurants';

    #[Url(as: 'q', history: true)]
    public mixed $search = '';

    #[Url(as: 'organization', history: true)]
    public mixed $organizationId = '';

    #[Url(as: 'brand', history: true)]
    public mixed $brandId = '';

    #[Url(as: 'active', history: true)]
    public mixed $active = 'all';

    #[Url(as: 'setup', history: true)]
    public mixed $setup = 'all';

    #[Url(as: 'lifecycle', history: true)]
    public mixed $lifecycle = 'active';

    /** @return array<string, string> */
    public function validated(): array
    {
        $data = Validator::make(['filters' => $this->all()], [
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.organizationId' => ['nullable', 'numeric', 'integer', 'min:1'],
            'filters.brandId' => ['nullable', 'numeric', 'integer', 'min:1'],
            'filters.view' => ['required', 'string', Rule::in(['restaurants', 'structure'])],
            'filters.active' => ['required', 'string', Rule::in(['all', 'active', 'inactive'])],
            'filters.setup' => ['required', 'string', Rule::in(['all', 'unfinished'])],
            'filters.lifecycle' => ['required', 'string', Rule::in(['active', 'archived'])],
        ], attributes: [
            'filters.search' => __('center.search'), 'filters.organizationId' => __('center.organization'),
            'filters.brandId' => __('center.brand'), 'filters.view' => __('center.title'),
            'filters.active' => __('center.administrative_state'), 'filters.setup' => __('center.setup_state'),
            'filters.lifecycle' => __('center.lifecycle'),
        ])->validate()['filters'];

        return ['view' => $data['view'], 'search' => trim($data['search'] ?? ''),
            'organization' => (string) ($data['organizationId'] ?? ''), 'brand' => (string) ($data['brandId'] ?? ''),
            'active' => $data['active'], 'setup' => $data['setup'], 'lifecycle' => $data['lifecycle']];
    }
}
