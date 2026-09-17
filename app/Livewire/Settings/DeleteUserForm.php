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

    public mixed $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout, DeleteUserAction $deleteUser): void
    {
        try {
            $this->validate([
                'password' => ['bail', ...$this->currentPasswordRules()],
            ]);

            $user = Auth::user();

            if (! $user instanceof User) {
                abort(401);
            }

            $logout();
            $deleteUser->handle($user);

            $this->redirect('/', navigate: false);
        } finally {
            $this->reset('password');
        }
    }

    public function dehydrate(): void
    {
        $this->reset('password');
    }
}
