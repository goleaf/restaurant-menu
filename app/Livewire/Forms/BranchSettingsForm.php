<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Branches\SaveBranchConfigurationAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\BranchServiceMode;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Support\MoneyFormatter;
use App\Support\Validation\RestaurantValidationRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
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

    public mixed $temporarilyClosed = false;

    public mixed $temporaryClosedReason = '';

    public mixed $temporaryClosedUntil = '';

    public mixed $openingHoursConfigured = false;

    public mixed $openingHours = [];

    /** @param array{configured: bool, days: list<array<string, mixed>>} $schedule */
    public function populate(Branch $branch, BranchSetting $settings, array $schedule): void
    {
        $this->fillFromSettings($settings);
        $this->fillFromBranchProfile($branch);
        $this->fillFromTemporaryClosure($branch);
        $this->openingHoursConfigured = $schedule['configured'];
        $this->openingHours = $schedule['days'];
    }

    /** @return ConfigurationData */
    public function validatedConfiguration(): array
    {
        if (is_string($this->defaultCurrency)) {
            $this->defaultCurrency = SupportedCurrency::clean($this->defaultCurrency);
        }

        $validated = $this->validate($this->rules(), $this->imageValidationMessages());
        $openingHoursConfigured = (bool) $validated['openingHoursConfigured'];
        $openingHours = $openingHoursConfigured
            ? $this->validatedOpeningHours($validated['openingHours'] ?? [])
            : [];

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
            'closure' => [
                'closed' => (bool) $validated['temporarilyClosed'],
                'reason' => $validated['temporaryClosedReason'],
                'until' => $validated['temporaryClosedUntil'],
            ],
            'opening_hours' => $openingHours,
            'opening_hours_configured' => $openingHoursConfigured,
            'logo' => $validated['publicLogo'] instanceof UploadedFile ? $validated['publicLogo'] : null,
            'cover' => $validated['coverImage'] instanceof UploadedFile ? $validated['coverImage'] : null,
        ];
    }

    /** @return list<array{day_of_week: int, label: string, is_closed: bool, can_add_interval: bool, intervals: list<array{opens_at: string, closes_at: string}>}> */
    public function displayOpeningHours(): array
    {
        $days = is_array($this->openingHours) ? $this->openingHours : [];
        $result = [];

        foreach (GetBranchOpeningStatusAction::dayLabels() as $dayOfWeek => $label) {
            $day = $days[$dayOfWeek - 1] ?? [];
            $day = is_array($day) ? $day : [];
            $intervals = is_array($day['intervals'] ?? null) ? $day['intervals'] : [];
            $displayIntervals = [];

            foreach (array_slice(array_values($intervals), 0, 4) as $interval) {
                $displayIntervals[] = [
                    'opens_at' => is_array($interval) && is_string($interval['opens_at'] ?? null) ? $interval['opens_at'] : '',
                    'closes_at' => is_array($interval) && is_string($interval['closes_at'] ?? null) ? $interval['closes_at'] : '',
                ];
            }

            $result[] = [
                'day_of_week' => $dayOfWeek,
                'label' => $label,
                'is_closed' => in_array($day['is_closed'] ?? false, [true, 1, '1'], true),
                'can_add_interval' => count($intervals) < 4,
                'intervals' => $displayIntervals,
            ];
        }

        return $result;
    }

    public function addOpeningInterval(int $dayOfWeek): void
    {
        $this->openingHoursConfigured = true;

        if (! is_array($this->openingHours)) {
            return;
        }

        foreach ($this->openingHours as $index => $day) {
            if (! is_array($day)) {
                continue;
            }
            if ((int) ($day['day_of_week'] ?? 0) !== $dayOfWeek) {
                continue;
            }

            $this->openingHours[$index]['intervals'] = is_array($day['intervals'] ?? null) ? $day['intervals'] : [];

            if (count($this->openingHours[$index]['intervals']) >= 4) {
                return;
            }

            $this->openingHours[$index]['is_closed'] = false;
            $this->openingHours[$index]['intervals'][] = [
                'opens_at' => '10:00',
                'closes_at' => '22:00',
            ];

            return;
        }
    }

    public function removeOpeningInterval(int $dayOfWeek, int $intervalIndex): void
    {
        if (! is_array($this->openingHours)) {
            return;
        }

        foreach ($this->openingHours as $index => $day) {
            if (! is_array($day)) {
                continue;
            }
            if ((int) ($day['day_of_week'] ?? 0) !== $dayOfWeek) {
                continue;
            }

            if (! is_array($day['intervals'] ?? null)) {
                return;
            }

            unset($this->openingHours[$index]['intervals'][$intervalIndex]);
            $this->openingHours[$index]['intervals'] = array_values($this->openingHours[$index]['intervals']);
            $this->openingHours[$index]['is_closed'] = $this->openingHours[$index]['intervals'] === [];

            return;
        }
    }

    protected function rules(): array
    {
        return [
            ...RestaurantValidationRules::branchSettings(),
            ...RestaurantValidationRules::branchProfile(),
            'publicLogo' => $this->optionalImageRules(),
            'coverImage' => $this->optionalImageRules(),
            ...RestaurantValidationRules::temporaryClosure(in_array($this->temporarilyClosed, [true, 1, '1'], true)),
            ...RestaurantValidationRules::openingHours(in_array($this->openingHoursConfigured, [true, 1, '1'], true)),
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

    private function fillFromTemporaryClosure(Branch $branch): void
    {
        $this->temporarilyClosed = (bool) $branch->is_temporarily_closed;
        $this->temporaryClosedReason = (string) ($branch->temporary_closed_reason ?? '');
        $temporaryClosedUntil = $branch->temporaryClosedUntilForBranch();
        $this->temporaryClosedUntil = $temporaryClosedUntil === null
            ? ''
            : $temporaryClosedUntil->format('Y-m-d\TH:i');
    }

    /**
     * @param  array<int, array<string, mixed>>  $openingHours
     * @return list<array{day_of_week: int, is_closed: bool, intervals: list<array{opens_at: string, closes_at: string}>}>
     */
    private function validatedOpeningHours(array $openingHours): array
    {
        $errors = [];
        $normalizedDays = [];

        foreach ($openingHours as $dayIndex => $day) {
            $dayOfWeek = (int) ($day['day_of_week'] ?? 0);
            $isClosed = (bool) ($day['is_closed'] ?? false);
            $intervals = [];

            if ($dayOfWeek < 1 || $dayOfWeek > 7) {
                continue;
            }

            if (! $isClosed) {
                foreach (($day['intervals'] ?? []) as $intervalIndex => $interval) {
                    $opensAt = substr((string) ($interval['opens_at'] ?? ''), 0, 5);
                    $closesAt = substr((string) ($interval['closes_at'] ?? ''), 0, 5);

                    if ($opensAt === '' || $closesAt === '') {
                        $errors["openingHours.$dayIndex.intervals.$intervalIndex.opens_at"] = __('ui.livewire.organizations.brands.branches.settings.ukazite_nacalo_i_konec_i');

                        continue;
                    }

                    if ($opensAt === $closesAt) {
                        $errors["openingHours.$dayIndex.intervals.$intervalIndex.closes_at"] = __('ui.livewire.organizations.brands.branches.settings.vremia_zakrytiia_dolzno');

                        continue;
                    }

                    $intervals[] = [
                        'opens_at' => $opensAt,
                        'closes_at' => $closesAt,
                    ];
                }

                if ($intervals === []) {
                    $errors["openingHours.$dayIndex.intervals"] = __('ui.livewire.organizations.brands.branches.settings.dobavte_interval_ili_otm');
                }
            }

            $normalizedDays[] = [
                'day_of_week' => $dayOfWeek,
                'is_closed' => $isClosed,
                'intervals' => $intervals,
            ];
        }

        if ($errors !== []) {
            foreach ($errors as $key => $message) {
                $this->addError($key, $message);
            }

            throw ValidationException::withMessages($this->getComponent()->getErrorBag()->messages());
        }

        return $normalizedDays;
    }

    /**
     * @return list<string>
     */
    private function optionalImageRules(): array
    {
        return RestaurantValidationRules::optionalImageUpload('image')['image'];
    }

    /**
     * @return array<string, string>
     */
    private function imageValidationMessages(): array
    {
        return [
            ...StoreLocalImageAction::validationMessages('publicLogo'),
            ...StoreLocalImageAction::validationMessages('coverImage'),
        ];
    }
}
