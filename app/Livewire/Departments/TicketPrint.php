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

    public function mount(KitchenTicket $kitchenTicket): void
    {
        $this->kitchenTicket = $kitchenTicket;
    }

    public function render(): View
    {
        return view('livewire.departments.ticket-print', [
            'print' => app(BuildDepartmentTicketPrintAction::class)->handle($this->currentUser(), $this->kitchenTicket),
        ])
            ->layoutData(['printStyles' => 'resources/scss/preparation-print.scss'])
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
