<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Users\UpdateUserPasswordAction;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\Users\PasskeyQueryService;
use Exception;
use Flux\Flux;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Security extends Component
{
    use PasswordValidationRules;

    private User $authenticatedUser;

    private PasskeyQueryService $passkeyQueries;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    #[Locked]
    public bool $canManageTwoFactor;

    #[Locked]
    public bool $twoFactorEnabled;

    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    public function boot(Request $request, PasskeyQueryService $passkeyQueries): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $this->authenticatedUser = $user;
        $this->passkeyQueries = $passkeyQueries;
    }

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null($this->authenticatedUser->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication($this->authenticatedUser);
            }

            $this->twoFactorEnabled = $this->authenticatedUser->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(UpdateUserPasswordAction $updatePassword): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        $updatePassword->handle($this->authenticatedUser, $validated['password']);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('ui.livewire.settings.security.password_updated'));
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = $this->passkeyQueries->forUser($this->authenticatedUser)
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = $this->passkeyQueries->findForUser($this->authenticatedUser, $passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = $this->passkeyQueries->findForUser($this->authenticatedUser, $this->deletingPasskeyId);

        $deletePasskey($this->authenticatedUser, $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    /**
     * Enable two-factor authentication for the user.
     */
    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        $enableTwoFactorAuthentication($this->authenticatedUser);

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = $this->authenticatedUser->hasEnabledTwoFactorAuthentication();
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        try {
            $this->qrCodeSvg = $this->authenticatedUser->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($this->authenticatedUser->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', __('ui.livewire.settings.security.failed_to_fetch_setup_data'));

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication($this->authenticatedUser, $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication($this->authenticatedUser);

        $this->twoFactorEnabled = false;
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = $this->authenticatedUser->hasEnabledTwoFactorAuthentication();
        }
    }

    #[Computed]
    public function browserPasswordRules(): string
    {
        return Password::defaults()->toPasswordRulesString();
    }

    /**
     * Get the current modal configuration state.
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('ui.livewire.settings.security.two_factor_authentication_enabled'),
                'description' => __('ui.livewire.settings.security.two_factor_authentication_is_now_enabled_scan'),
                'buttonText' => __('guest.table.close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('ui.livewire.settings.security.verify_authentication_code'),
                'description' => __('ui.livewire.settings.security.enter_the_6_digit_code_from_your_authenticato'),
                'buttonText' => __('ui.actions.continue'),
            ];
        }

        return [
            'title' => __('ui.livewire.settings.security.enable_two_factor_authentication'),
            'description' => __('ui.livewire.settings.security.to_finish_enabling_two_factor_authentication'),
            'buttonText' => __('ui.actions.continue'),
        ];
    }

    public function render(): View
    {
        return view('livewire.settings.security')
            ->title(__('ui.settings.security.security_settings'));
    }
}
