<?php

declare(strict_types=1);

namespace App\Livewire\Local;

use App\Actions\Auth\LoginAsLocalUserAction;
use App\Livewire\Forms\Local\IdentitySelectionForm;
use App\Services\Auth\LocalLoginDirectoryQuery;
use App\Support\Auth\InteractiveLoginGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Component;
use Livewire\WithPagination;

final class UserLogin extends Component
{
    use WithPagination;

    public IdentitySelectionForm $form;

    public function login(mixed $userId, InteractiveLoginGuard $guard, LoginAsLocalUserAction $login): void
    {
        $response = $guard->run(request(), local: true,
            operation: fn (Request $request) => $login->handle($request, $this->form->localIdentity($userId)));
        $this->redirect($response->headers->get('Location'), navigate: false);
    }

    public function render(InteractiveLoginGuard $guard, LocalLoginDirectoryQuery $directory): View
    {
        return view('livewire.local.user-login', [
            'users' => $guard->allowsLocal(request()) ? $directory->handle(request()) : null,
        ]);
    }
}
