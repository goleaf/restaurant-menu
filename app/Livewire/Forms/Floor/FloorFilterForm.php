<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Floor;

use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Form;

class FloorFilterForm extends Form
{
    #[Url(as: 'q', history: true, except: '')]
    public mixed $search = '';

    #[Url(as: 'zone', history: true, except: 'all')]
    public mixed $area = 'all';

    #[Url(as: 'mode', history: true, except: 'cards')]
    public mixed $mode = 'cards';

    #[Url(as: 'type', history: true, except: 'all')]
    public mixed $type = 'all';

    #[Url(as: 'status', history: true, except: 'all')]
    public mixed $status = 'all';

    #[Url(as: 'active', history: true, except: 'all')]
    public mixed $active = 'all';

    #[Url(as: 'qr', history: true, except: 'all')]
    public mixed $qr = 'all';

    #[Url(as: 'lifecycle', history: true, except: 'active')]
    public mixed $lifecycle = 'active';

    #[Url(as: 'sort', history: true, except: 'position')]
    public mixed $sort = 'position';

    /** @return array{search:string,area_node_id:string,type:string,status:string,active:string,qr:string,lifecycle:string,sort:string} */
    public function filters(): array
    {
        $data = Validator::make(['filters' => $this->all()], [
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.area' => ['required', 'string', 'regex:/\A(?:all|none|[1-9][0-9]*)\z/'],
            'filters.mode' => ['required', Rule::in(['cards', 'list'])],
            'filters.type' => ['required', Rule::in(['all', ...ServicePointType::values()])],
            'filters.status' => ['required', Rule::in(['all', ...ServicePointStatus::values()])],
            'filters.active' => ['required', Rule::in(['all', 'active', 'inactive'])],
            'filters.qr' => ['required', Rule::in(['all', 'with', 'without'])],
            'filters.lifecycle' => ['required', Rule::in(['active', 'archived'])],
            'filters.sort' => ['required', Rule::in(['position', 'name_asc', 'name_desc', 'newest', 'oldest'])],
        ], attributes: array_fill_keys(array_map(fn ($key) => 'filters.'.$key, array_keys($this->all())), __('floor.filters')))->validate()['filters'];

        return ['search' => trim($data['search'] ?? ''), 'area_node_id' => $data['area'], 'type' => $data['type'], 'status' => $data['status'],
            'active' => $data['active'], 'qr' => $data['qr'], 'lifecycle' => $data['lifecycle'], 'sort' => $data['sort']];
    }
}
