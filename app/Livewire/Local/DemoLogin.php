<?php

declare(strict_types=1);

namespace App\Livewire\Local;

use App\Actions\Auth\BuildDemoLoginPageAction;
use App\Actions\Auth\LoginAsDemoRoleAction;
use App\Livewire\Forms\Local\IdentitySelectionForm;
use App\Support\Auth\InteractiveLoginGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Component;

final class DemoLogin extends Component
{
    public IdentitySelectionForm $form;

    public function mount(InteractiveLoginGuard $guard): void
    {
        $guard->authorize(request(), local: false);
    }

    public function login(mixed $role, InteractiveLoginGuard $guard, LoginAsDemoRoleAction $login): void
    {
        $response = $guard->run(request(), local: false,
            operation: fn (Request $request) => $login->handle($request, $this->form->demoRole($role)));
        $this->redirect($response->headers->get('Location'), navigate: false);
    }

    public function render(InteractiveLoginGuard $guard, BuildDemoLoginPageAction $page): View
    {
        $guard->authorize(request(), local: false);

        return view('livewire.local.demo-login', ['accounts' => $page->handle()])
            ->layout('layouts::auth.simple', ['wide' => true])
            ->title(__('demo_login.title'));
    }
}
