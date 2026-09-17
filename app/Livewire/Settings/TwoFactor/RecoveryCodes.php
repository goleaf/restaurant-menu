<?php

declare(strict_types=1);

namespace App\Livewire\Settings\TwoFactor;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Livewire\Component;

class RecoveryCodes extends Component
{
    private User $authenticatedUser;

    private array $recoveryCodes = [];

    public function boot(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        abort_unless(
            Features::canManageTwoFactorAuthentication() && $user->hasEnabledTwoFactorAuthentication(),
            403,
        );

        $this->authenticatedUser = $user;
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes($this->authenticatedUser);
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

    public function render(): View
    {
        $this->loadRecoveryCodes();

        return view('livewire.settings.two-factor.recovery-codes', [
            'recoveryCodes' => $this->recoveryCodes,
        ]);
    }
}
