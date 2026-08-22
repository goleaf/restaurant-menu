<?php

declare(strict_types=1);

namespace App\Livewire\Departments;

use App\Actions\Departments\BuildDepartmentTicketPrintAction;
use App\Models\KitchenTicket;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.print')]
class TicketPrint extends Component
{
    public KitchenTicket $kitchenTicket;

    /**
     * @var array{
     *     ticket: array<string, mixed>,
     *     branch: array<string, mixed>,
     *     service_point: array<string, mixed>,
     *     department: array<string, mixed>,
     *     items: list<array<string, mixed>>
     * }
     */
    public array $print = [
        'ticket' => [],
        'branch' => [],
        'service_point' => [],
        'department' => [],
        'items' => [],
    ];

    public function mount(KitchenTicket $kitchenTicket, BuildDepartmentTicketPrintAction $buildPrint): void
    {
        $this->kitchenTicket = $kitchenTicket;
        $this->print = $buildPrint->handle($this->currentUser(), $kitchenTicket);
    }

    public function render(): View
    {
        return view('livewire.departments.ticket-print')
            ->title(__('ui.livewire.departments.ticketprint.kitchen_ticket_print'));
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
