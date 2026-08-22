<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Branches\UpdateBranchOpeningHoursAction;
use App\Actions\Branches\UpdateBranchPublicProfileAction;
use App\Actions\Branches\UpdateBranchSettingsAction;
use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\TableSessions\CleanupInactiveTableSessionsAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Support\Validation\RestaurantValidationRules;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Branch settings')]
class Settings extends Component
{
    use WithFileUploads;

    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public int $settingsId;

    public bool $requireWaiterConfirmationForOrders = true;

    public bool $allowGuestCreatedSessions = true;

    public bool $allowWaiterOpenedSessions = true;

    public bool $allowGuestInviteLinks = true;

    public bool $guestJoinRequiresApproval = true;

    public int $pollingIntervalSeconds = 1;

    public int $inactivityWarningMinutes = 45;

    public int $pendingSessionExpireMinutes = 30;

    public string $defaultLanguage = 'en';

    public string $defaultCurrency = 'EUR';

    public bool $serviceChargeEnabled = false;

    public string $serviceChargePercent = '0.00';

    public bool $tipsEnabled = false;

    public string $orderFlowMode = 'waiter_confirmation';

    /**
     * @var list<string>
     */
    public array $serviceModes = [];

    public string $publicName = '';

    public string $publicDescription = '';

    public string $phone = '';

    public string $email = '';

    public string $websiteUrl = '';

    public string $instagramUrl = '';

    public string $facebookUrl = '';

    public string $tiktokUrl = '';

    public ?string $currentLogoUrl = null;

    public ?string $currentCoverImageUrl = null;

    public mixed $publicLogo = null;

    public mixed $coverImage = null;

    public bool $temporarilyClosed = false;

    public string $temporaryClosedReason = '';

    public string $temporaryClosedUntil = '';

    public bool $openingHoursConfigured = false;

    /**
     * @var list<array{day_of_week: int, label: string, is_closed: bool, intervals: list<array{opens_at: string, closes_at: string}>}>
     */
    public array $openingHours = [];

    public bool $saved = false;

    public string $cleanupMessage = '';

    /**
     * @var array<string, string>
     */
    public array $languageOptions = [];

    /**
     * @var array<string, string>
     */
    public array $currencyOptions = [];

    public function mount(
        Organization $organization,
        Brand $brand,
        Branch $branch,
        EnsureBranchSettingsAction $ensureBranchSettings,
    ): void {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->branch = $branch;
        $this->languageOptions = SupportedLocale::labels();
        $this->currencyOptions = SupportedCurrency::labels();

        if (
            $brand->organization_id !== $organization->id
            || $branch->organization_id !== $organization->id
            || $branch->brand_id !== $brand->id
        ) {
            abort(403);
        }

        Gate::forUser($this->currentUser())->authorize('manageSettings', $branch);

        $this->fillFromSettings($ensureBranchSettings->handle($branch));
        $this->fillFromBranchProfile($this->branch);
        $this->fillFromTemporaryClosure($this->branch);
        $this->fillFromOpeningHours($this->branch);
    }

    public function save(
        UpdateBranchSettingsAction $updateBranchSettings,
        UpdateBranchPublicProfileAction $updateBranchPublicProfile,
        UpdateBranchOpeningHoursAction $updateBranchOpeningHours,
        UpdateBranchTemporaryClosureAction $updateBranchTemporaryClosure,
        ReplaceLocalImageAction $replaceLocalImage,
    ): void {
        $this->authorizeSettingsManagement();
        $this->defaultCurrency = SupportedCurrency::clean($this->defaultCurrency);

        $validated = $this->validate($this->rules(), $this->imageValidationMessages());
        $openingHoursConfigured = (bool) $validated['openingHoursConfigured'];
        $openingHours = $openingHoursConfigured
            ? $this->validatedOpeningHours($validated['openingHours'] ?? [])
            : [];

        $settings = $updateBranchSettings->handle(
            $this->findSettings(),
            [
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
                'service_charge_percent' => $validated['serviceChargePercent'],
                'tips_enabled' => (bool) $validated['tipsEnabled'],
                'order_flow_mode' => $validated['orderFlowMode'],
                'service_modes' => $validated['serviceModes'],
            ],
        );

        $profilePayload = [
            'public_name' => $validated['publicName'],
            'public_description' => $validated['publicDescription'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'website_url' => $validated['websiteUrl'],
            'instagram_url' => $validated['instagramUrl'],
            'facebook_url' => $validated['facebookUrl'],
            'tiktok_url' => $validated['tiktokUrl'],
        ];

        if ($this->publicLogo instanceof UploadedFile) {
            $profilePayload['logo_path'] = $replaceLocalImage->handle(
                file: $this->publicLogo,
                directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$this->branch->id.'/logos',
                oldPath: $this->branch->logo_path,
                persist: function (string $path): void {
                    $this->branch->forceFill(['logo_path' => $path])->saveOrFail();
                },
            );
        }

        if ($this->coverImage instanceof UploadedFile) {
            $profilePayload['cover_image_path'] = $replaceLocalImage->handle(
                file: $this->coverImage,
                directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$this->branch->id.'/covers',
                oldPath: $this->branch->cover_image_path,
                persist: function (string $path): void {
                    $this->branch->forceFill(['cover_image_path' => $path])->saveOrFail();
                },
            );
        }

        $branch = $updateBranchPublicProfile->handle($this->branch, $profilePayload);
        $branch = $updateBranchTemporaryClosure->handle(
            branch: $branch,
            isTemporarilyClosed: (bool) $validated['temporarilyClosed'],
            reason: $validated['temporaryClosedReason'],
            closedUntil: $validated['temporaryClosedUntil'],
        );
        $updateBranchOpeningHours->handle($branch, $openingHours, $openingHoursConfigured);

        $this->fillFromSettings($settings);
        $this->fillFromBranchProfile($branch);
        $this->fillFromTemporaryClosure($branch);
        $this->fillFromOpeningHours($branch);
        $this->reset('publicLogo', 'coverImage');
        $this->saved = true;

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.settings.settings_saved'));
    }

    public function runSessionInactivityCleanup(CleanupInactiveTableSessionsAction $cleanupInactiveTableSessions): void
    {
        $this->authorizeSettingsManagement();

        $result = $cleanupInactiveTableSessions->handle($this->branch->id);
        $this->cleanupMessage = $this->cleanupSummary($result);

        Flux::toast(variant: 'success', text: __('ui.livewire.organizations.brands.branches.settings.session_cleanup_finished'));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function orderFlowModeOptions(): array
    {
        return BranchOrderFlowMode::options();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    #[Computed]
    public function serviceModeOptions(): array
    {
        return BranchServiceMode::options();
    }

    public function render(): View
    {
        return view('livewire.organizations.brands.branches.settings');
    }

    public function addOpeningInterval(int $dayOfWeek): void
    {
        $this->openingHoursConfigured = true;

        foreach ($this->openingHours as $index => $day) {
            if ((int) $day['day_of_week'] !== $dayOfWeek) {
                continue;
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
        foreach ($this->openingHours as $index => $day) {
            if ((int) $day['day_of_week'] !== $dayOfWeek) {
                continue;
            }

            unset($this->openingHours[$index]['intervals'][$intervalIndex]);
            $this->openingHours[$index]['intervals'] = array_values($this->openingHours[$index]['intervals']);
            $this->openingHours[$index]['is_closed'] = $this->openingHours[$index]['intervals'] === [];

            return;
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            ...RestaurantValidationRules::branchSettings(),
            ...RestaurantValidationRules::branchProfile(),
            'publicLogo' => $this->optionalImageRules(),
            'coverImage' => $this->optionalImageRules(),
            ...RestaurantValidationRules::temporaryClosure($this->temporarilyClosed),
            ...RestaurantValidationRules::openingHours(),
        ];
    }

    private function fillFromSettings(BranchSetting $settings): void
    {
        $this->settingsId = $settings->id;
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
        $this->serviceChargePercent = $settings->service_charge_percent;
        $this->tipsEnabled = $settings->tips_enabled;
        $this->orderFlowMode = $settings->order_flow_mode->value;
        $this->serviceModes = BranchServiceMode::normalizeList($settings->service_modes);
        $this->branch->refresh();
    }

    private function fillFromBranchProfile(Branch $branch): void
    {
        $this->branch = $branch->refresh();
        $this->publicName = (string) ($this->branch->public_name ?? '');
        $this->publicDescription = (string) ($this->branch->public_description ?? '');
        $this->phone = (string) ($this->branch->phone ?? '');
        $this->email = (string) ($this->branch->email ?? '');
        $this->websiteUrl = (string) ($this->branch->website_url ?? '');
        $this->instagramUrl = (string) ($this->branch->instagram_url ?? '');
        $this->facebookUrl = (string) ($this->branch->facebook_url ?? '');
        $this->tiktokUrl = (string) ($this->branch->tiktok_url ?? '');
        $this->currentLogoUrl = $this->branch->logoUrl();
        $this->currentCoverImageUrl = $this->branch->coverImageUrl();
    }

    private function fillFromTemporaryClosure(Branch $branch): void
    {
        $this->branch = $branch->refresh();
        $this->temporarilyClosed = (bool) $this->branch->is_temporarily_closed;
        $this->temporaryClosedReason = (string) ($this->branch->temporary_closed_reason ?? '');
        $temporaryClosedUntil = $this->branch->temporaryClosedUntilForBranch();
        $this->temporaryClosedUntil = $temporaryClosedUntil === null
            ? ''
            : $temporaryClosedUntil->format('Y-m-d\TH:i');
    }

    private function fillFromOpeningHours(Branch $branch): void
    {
        $openingHours = $branch->openingHours()
            ->select([
                'id',
                'branch_id',
                'day_of_week',
                'is_closed',
                'opens_at',
                'closes_at',
                'sort_order',
            ])
            ->get()
            ->groupBy('day_of_week');

        $this->openingHoursConfigured = $openingHours->isNotEmpty();
        $this->openingHours = collect(GetBranchOpeningStatusAction::dayLabels())
            ->map(function (string $label, int $dayOfWeek) use ($openingHours): array {
                $dayRows = $openingHours->get($dayOfWeek, collect());
                $intervals = $dayRows
                    ->filter(fn ($openingHour): bool => ! $openingHour->is_closed)
                    ->map(fn ($openingHour): array => [
                        'opens_at' => substr((string) $openingHour->opens_at, 0, 5),
                        'closes_at' => substr((string) $openingHour->closes_at, 0, 5),
                    ])
                    ->values()
                    ->all();

                return [
                    'day_of_week' => $dayOfWeek,
                    'label' => $label,
                    'is_closed' => $this->openingHoursConfigured && $intervals === [],
                    'intervals' => $intervals !== [] ? $intervals : [
                        [
                            'opens_at' => '10:00',
                            'closes_at' => '22:00',
                        ],
                    ],
                ];
            })
            ->values()
            ->all();
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
            throw ValidationException::withMessages($errors);
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

    private function findSettings(): BranchSetting
    {
        return BranchSetting::query()
            ->select([
                'id',
                'branch_id',
                'require_waiter_confirmation_for_orders',
                'allow_guest_created_sessions',
                'allow_waiter_opened_sessions',
                'allow_guest_invite_links',
                'guest_join_requires_approval',
                'polling_interval_seconds',
                'inactivity_warning_minutes',
                'pending_session_expire_minutes',
                'default_language',
                'default_currency',
                'service_charge_enabled',
                'tips_enabled',
                'order_flow_mode',
                'service_modes',
                'created_at',
                'updated_at',
            ])
            ->whereKey($this->settingsId)
            ->where('branch_id', $this->branch->id)
            ->firstOrFail();
    }

    /**
     * @param  array{
     *     checked: int,
     *     pending_cancelled: int,
     *     active_warnings: int,
     *     skipped_unpaid_orders: int,
     *     skipped_existing_orders: int,
     *     skipped_existing_drafts: int
     * }  $result
     */
    private function cleanupSummary(array $result): string
    {
        return __(
            'ui.livewire.organizations.brands.branches.settings.cleanup_checked_sessions',
            [
                'checked' => $result['checked'],
                'cancelled' => $result['pending_cancelled'],
                'warnings' => $result['active_warnings'],
                'unpaid' => $result['skipped_unpaid_orders'],
            ],
        );
    }

    private function branchTimezone(): string
    {
        $timezone = $this->branch->timezone;

        return is_string($timezone) && $timezone !== '' ? $timezone : config('app.timezone', 'UTC');
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function authorizeSettingsManagement(): void
    {
        Gate::forUser($this->currentUser())->authorize('manageSettings', $this->branch);
    }
}
