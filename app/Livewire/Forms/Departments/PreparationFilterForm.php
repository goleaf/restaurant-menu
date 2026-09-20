<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Departments;

use App\Enums\DepartmentTicketFilter;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Form;

final class PreparationFilterForm extends Form
{
    /** @return array{department: string, filter: string, ticket: ?int, page: int, itemPage: int} */
    public function validated(mixed $department, mixed $filter, mixed $ticket, mixed $page, mixed $itemPage): array
    {
        $data = Validator::make(['filters' => compact('department', 'filter', 'ticket', 'page', 'itemPage')], [
            'filters.department' => ['present', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === '' || $value === 'all') {
                    return;
                }
                if ((! is_int($value) && ! is_string($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    $fail(__('preparation.errors.department'));
                }
            }],
            'filters.filter' => ['required', 'string', Rule::enum(DepartmentTicketFilter::class)],
            'filters.ticket' => ['nullable', 'numeric', 'integer', 'min:1'],
            'filters.page' => ['required', 'numeric', 'integer', 'min:1', 'max:100000'],
            'filters.itemPage' => ['required', 'numeric', 'integer', 'min:1', 'max:100000'],
        ], attributes: [
            'filters.department' => __('ui.departments.dashboard.department'),
            'filters.filter' => __('ui.departments.dashboard.filter'),
            'filters.ticket' => __('ui.departments.dashboard.tickets'),
            'filters.page' => __('ui.departments.dashboard.pagination'),
            'filters.itemPage' => __('ui.departments.dashboard.pagination'),
        ])->validate()['filters'];

        return ['department' => (string) $data['department'], 'filter' => $data['filter'],
            'ticket' => ($data['ticket'] ?? '') === '' ? null : (int) $data['ticket'],
            'page' => (int) $data['page'], 'itemPage' => (int) $data['itemPage']];
    }
}
