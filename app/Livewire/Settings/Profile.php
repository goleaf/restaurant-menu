<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\Users\UpdateUserProfileAction;
use App\Concerns\ProfileValidationRules;
use App\Enums\SupportedLocale;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $locale = 'en';

    /**
     * @var array<string, string>
     */
    public array $localeOptions = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->locale = SupportedLocale::normalize(Auth::user()->locale);
        $this->localeOptions = SupportedLocale::labels();
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(UpdateUserProfileAction $updateProfile): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id, includeLocale: true));

        $updateProfile->handle($user, $validated);

        Flux::toast(variant: 'success', text: __('ui.livewire.settings.profile.profile_updated'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('ui.livewire.settings.profile.a_new_verification_link_has_been_sent_to_your'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        $user = Auth::user();

        if (! $user instanceof MustVerifyEmail) {
            return true;
        }

        return $user->hasVerifiedEmail();
    }

    public function render(): View
    {
        return view('livewire.settings.profile')
            ->title(__('ui.settings.profile.profile_settings'));
    }
}
