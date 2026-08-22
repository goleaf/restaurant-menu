<?php

declare(strict_types=1);

namespace App\Livewire\Settings\TwoFactor;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RecoveryCodes extends Component
{
    private User $authenticatedUser;

    #[Locked]
    public array $recoveryCodes = [];

    public function boot(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $this->authenticatedUser = $user;
    }

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes($this->authenticatedUser);

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        if ($this->authenticatedUser->hasEnabledTwoFactorAuthentication() && $this->authenticatedUser->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($this->authenticatedUser->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', __('ui.settings.two_factor.recovery_codes.failed_to_load_recovery_codes'));

                $this->recoveryCodes = [];
            }
        }
    }
}
