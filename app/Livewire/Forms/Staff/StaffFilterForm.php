<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Staff;

use App\Enums\SystemRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Form;

class StaffFilterForm extends Form
{
    #[Url(as: 'search', history: true, except: '')]
    public mixed $search = '';

    #[Url(as: 'role', history: true, except: '')]
    public mixed $role = '';

    #[Url(as: 'status', history: true, except: '')]
    public mixed $status = '';

    #[Url(as: 'sort', history: true, except: 'newest')]
    public mixed $sort = 'newest';

    /** @return array{search:string,role:string,status:string,sort:string} */
    public function validatedFilters(string $section): array
    {
        $this->resetValidation(['search', 'role', 'status', 'sort']);
        $values = Validator::make(['filters' => $this->all()], [
            'filters.search' => ['nullable', 'string', 'max:120'],
            'filters.role' => ['nullable', 'string', Rule::in(SystemRole::values())],
            'filters.status' => ['nullable', 'string', Rule::in($section === 'invitations' ? ['pending', 'accepted', 'cancelled', 'expired', 'rejected'] : ['active', 'invited', 'suspended', 'removed'])],
            'filters.sort' => ['required', 'string', Rule::in(['newest', 'oldest', 'name'])],
        ], attributes: [
            'filters.search' => __('staff.workspace.search'),
            'filters.role' => __('staff.role'),
            'filters.status' => __('staff.workspace.status'),
            'filters.sort' => __('staff.workspace.sort'),
        ])->validate()['filters'];

        return ['search' => trim($values['search'] ?? ''), 'role' => $values['role'] ?? '', 'status' => $values['status'] ?? '', 'sort' => $values['sort']];
    }
}
