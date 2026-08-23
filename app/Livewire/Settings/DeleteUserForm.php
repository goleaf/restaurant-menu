<?php

namespace App\Livewire\Settings;

use App\Actions\Users\DeleteUserAction;
use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, DeleteUserAction $deleteUser): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        $logout();
        $deleteUser->handle($user);

        $this->redirect('/', navigate: true);
    }
}
