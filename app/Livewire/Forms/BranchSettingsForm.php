<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Actions\Branches\SaveBranchConfigurationAction;
use App\Enums\BranchServiceMode;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Support\MoneyFormatter;
use App\Support\Validation\Branches\BranchProfileRules;
use App\Support\Validation\Branches\BranchSettingsRules;
use App\Support\Validation\Media\ImageUploadRules;
use Illuminate\Http\UploadedFile;
use Livewire\Form;

/** @phpstan-import-type ConfigurationData from SaveBranchConfigurationAction */
final class BranchSettingsForm extends Form
{
    public mixed $requireWaiterConfirmationForOrders = true;

    public mixed $allowGuestCreatedSessions = true;

    public mixed $allowWaiterOpenedSessions = true;

    public mixed $allowGuestInviteLinks = true;

    public mixed $guestJoinRequiresApproval = true;

    public mixed $pollingIntervalSeconds = 1;

    public mixed $inactivityWarningMinutes = 45;

    public mixed $pendingSessionExpireMinutes = 30;

    public mixed $defaultLanguage = 'en';

    public mixed $defaultCurrency = 'EUR';

    public mixed $serviceChargeEnabled = false;

    public mixed $serviceChargePercent = '0.00';

    public mixed $tipsEnabled = false;

    public mixed $orderFlowMode = 'waiter_confirmation';

    public mixed $serviceModes = [];

    public mixed $publicName = '';

    public mixed $publicDescription = '';

    public mixed $phone = '';

    public mixed $email = '';

    public mixed $websiteUrl = '';

    public mixed $instagramUrl = '';

    public mixed $facebookUrl = '';

    public mixed $tiktokUrl = '';

    public mixed $publicLogo = null;

    public mixed $coverImage = null;

    public function populate(Branch $branch, BranchSetting $settings): void
    {
        $this->fillFromSettings($settings);
        $this->fillFromBranchProfile($branch);
    }

    /** @return ConfigurationData */
    public function validatedConfiguration(): array
    {
        if (is_string($this->defaultCurrency)) {
            $this->defaultCurrency = SupportedCurrency::clean($this->defaultCurrency);
        }

        $validated = $this->validate($this->rules(), $this->imageValidationMessages());

        return [
            'settings' => [
                'require_waiter_confirmation_for_orders' => (bool) $validated['requireWaiterConfirmationForOrders'],
                'allow_guest_created_sessions' => (bool) $validated['allowGuestCreatedSessions'],
                'allow_waiter_opened_sessions' => (bool) $validated['allowWaiterOpenedSessions'],
                'allow_guest_invite_links' => (bool) $validated['allowGuestInviteLinks'],
                'guest_join_requires_approval' => (bool) $validated['guestJoinRequiresApproval'],
                'polling_interval_seconds' => (int) $validated['pollingIntervalSeconds'],
                'inactivity_warning_minutes' => (int) $validated['inactivityWarningMinutes'],
                'pending_session_expire_minutes' => (int) $validated['pendingSessionExpireMinutes'],
                'default_language' => SupportedLocale::normalize($validated['defaultLanguage']),
                'default_currency' => SupportedCurrency::normalize($validated['defaultCurrency']),
                'service_charge_enabled' => (bool) $validated['serviceChargeEnabled'],
                'service_charge_percent' => (string) $validated['serviceChargePercent'],
                'tips_enabled' => (bool) $validated['tipsEnabled'],
                'order_flow_mode' => $validated['orderFlowMode'],
                'service_modes' => $validated['serviceModes'],
            ],
            'profile' => [
                'public_name' => $validated['publicName'],
                'public_description' => $validated['publicDescription'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'website_url' => $validated['websiteUrl'],
                'instagram_url' => $validated['instagramUrl'],
                'facebook_url' => $validated['facebookUrl'],
                'tiktok_url' => $validated['tiktokUrl'],
            ],
            'logo' => $validated['publicLogo'] instanceof UploadedFile ? $validated['publicLogo'] : null,
            'cover' => $validated['coverImage'] instanceof UploadedFile ? $validated['coverImage'] : null,
        ];
    }

    protected function rules(): array
    {
        return [
            ...BranchSettingsRules::branchSettings(),
            ...BranchProfileRules::branchProfile(),
            'publicLogo' => $this->optionalImageRules(),
            'coverImage' => $this->optionalImageRules(),
        ];
    }

    private function fillFromSettings(BranchSetting $settings): void
    {
        $this->requireWaiterConfirmationForOrders = $settings->require_waiter_confirmation_for_orders;
        $this->allowGuestCreatedSessions = $settings->allow_guest_created_sessions;
        $this->allowWaiterOpenedSessions = $settings->allow_waiter_opened_sessions;
        $this->allowGuestInviteLinks = $settings->allow_guest_invite_links;
        $this->guestJoinRequiresApproval = $settings->guest_join_requires_approval;
        $this->pollingIntervalSeconds = $settings->polling_interval_seconds;
        $this->inactivityWarningMinutes = $settings->inactivity_warning_minutes;
        $this->pendingSessionExpireMinutes = $settings->pending_session_expire_minutes;
        $this->defaultLanguage = $settings->default_language;
        $this->defaultCurrency = SupportedCurrency::normalize($settings->default_currency);
        $this->serviceChargeEnabled = $settings->service_charge_enabled;
        $this->serviceChargePercent = MoneyFormatter::centsToDecimal($settings->service_charge_basis_points);
        $this->tipsEnabled = $settings->tips_enabled;
        $this->orderFlowMode = $settings->order_flow_mode->value;
        $this->serviceModes = BranchServiceMode::normalizeList($settings->service_modes);
    }

    private function fillFromBranchProfile(Branch $branch): void
    {
        $this->publicName = (string) ($branch->public_name ?? '');
        $this->publicDescription = (string) ($branch->public_description ?? '');
        $this->phone = (string) ($branch->phone ?? '');
        $this->email = (string) ($branch->email ?? '');
        $this->websiteUrl = (string) ($branch->website_url ?? '');
        $this->instagramUrl = (string) ($branch->instagram_url ?? '');
        $this->facebookUrl = (string) ($branch->facebook_url ?? '');
        $this->tiktokUrl = (string) ($branch->tiktok_url ?? '');
    }

    /**
     * @return list<string>
     */
    private function optionalImageRules(): array
    {
        return ImageUploadRules::optionalImageUpload('image')['image'];
    }

    /**
     * @return array<string, string>
     */
    private function imageValidationMessages(): array
    {
        return [
            ...ImageUploadRules::messages('publicLogo'),
            ...ImageUploadRules::messages('coverImage'),
        ];
    }
}
