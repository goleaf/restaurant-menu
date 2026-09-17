<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\LogoutAction;
use Illuminate\View\View;
use Livewire\Component;

final class Logout extends Component
{
    use HandlesAuthResponses;

    public function logout(LogoutAction $action): void
    {
        $this->follow($action->handle(request()));
    }

    public function render(): View
    {
        return view('livewire.auth.logout');
    }
}
