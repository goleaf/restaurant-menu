<?php

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\Branches\GetBranchOpeningStatusAction;
use App\Actions\Branches\UpdateBranchOpeningHoursAction;
use App\Actions\Branches\UpdateBranchPublicProfileAction;
use App\Actions\Branches\UpdateBranchSettingsAction;
use App\Actions\Branches\UpdateBranchTemporaryClosureAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

    public string $defaultLanguage = 'en';

    public string $defaultCurrency = 'EUR';

    public bool $serviceChargeEnabled = false;

    public bool $tipsEnabled = false;

    public string $orderFlowMode = 'waiter_confirmation';

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

        $user = $this->currentUser();

        if (
            ! $user->canAccessBranch($branch, $organization)
            || ! $user->canManageOrganizationBranches($organization)
        ) {
            abort(403);
        }

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
        StoreLocalImageAction $storeLocalImage,
    ): void {
        $this->defaultCurrency = SupportedCurrency::clean($this->defaultCurrency);

        $validated = $this->validate($this->rules());
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
                'default_language' => SupportedLocale::normalize($validated['defaultLanguage']),
                'default_currency' => SupportedCurrency::normalize($validated['defaultCurrency']),
                'service_charge_enabled' => (bool) $validated['serviceChargeEnabled'],
                'tips_enabled' => (bool) $validated['tipsEnabled'],
                'order_flow_mode' => $validated['orderFlowMode'],
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
            $profilePayload['logo_path'] = $storeLocalImage->handle(
                file: $this->publicLogo,
                directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$this->branch->id.'/logos',
                oldPath: $this->branch->logo_path,
            );
        }

        if ($this->coverImage instanceof UploadedFile) {
            $profilePayload['cover_image_path'] = $storeLocalImage->handle(
                file: $this->coverImage,
                directory: 'media/organizations/'.$this->organization->id.'/brands/'.$this->brand->id.'/branches/'.$this->branch->id.'/covers',
                oldPath: $this->branch->cover_image_path,
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

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function orderFlowModeOptions(): array
    {
        return BranchOrderFlowMode::options();
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
            'requireWaiterConfirmationForOrders' => ['boolean'],
            'allowGuestCreatedSessions' => ['boolean'],
            'allowWaiterOpenedSessions' => ['boolean'],
            'allowGuestInviteLinks' => ['boolean'],
            'guestJoinRequiresApproval' => ['boolean'],
            'pollingIntervalSeconds' => ['required', 'integer', 'min:1', 'max:60'],
            'defaultLanguage' => ['required', 'string', Rule::in(SupportedLocale::values())],
            'defaultCurrency' => ['required', 'string', 'size:3', Rule::in(SupportedCurrency::values())],
            'serviceChargeEnabled' => ['boolean'],
            'tipsEnabled' => ['boolean'],
            'orderFlowMode' => ['required', 'string', Rule::in(BranchOrderFlowMode::values())],
            'publicName' => ['nullable', 'string', 'max:160'],
            'publicDescription' => ['nullable', 'string', 'max:1200'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'websiteUrl' => ['nullable', 'url', 'max:2048'],
            'instagramUrl' => ['nullable', 'url', 'max:2048'],
            'facebookUrl' => ['nullable', 'url', 'max:2048'],
            'tiktokUrl' => ['nullable', 'url', 'max:2048'],
            'publicLogo' => $this->optionalImageRules(),
            'coverImage' => $this->optionalImageRules(),
            'temporarilyClosed' => ['boolean'],
            'temporaryClosedReason' => [
                Rule::requiredIf($this->temporarilyClosed),
                'nullable',
                'string',
                'max:255',
            ],
            'temporaryClosedUntil' => [
                'nullable',
                'date',
            ],
            'openingHoursConfigured' => ['boolean'],
            'openingHours' => ['array', 'size:7'],
            'openingHours.*.day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'openingHours.*.label' => ['required', 'string', 'max:40'],
            'openingHours.*.is_closed' => ['boolean'],
            'openingHours.*.intervals' => ['array', 'max:4'],
            'openingHours.*.intervals.*.opens_at' => ['nullable', 'date_format:H:i'],
            'openingHours.*.intervals.*.closes_at' => ['nullable', 'date_format:H:i'],
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
        $this->defaultLanguage = $settings->default_language;
        $this->defaultCurrency = SupportedCurrency::normalize($settings->default_currency);
        $this->serviceChargeEnabled = $settings->service_charge_enabled;
        $this->tipsEnabled = $settings->tips_enabled;
        $this->orderFlowMode = $settings->order_flow_mode->value;
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
                        $errors["openingHours.$dayIndex.intervals.$intervalIndex.opens_at"] = __('Укажите начало и конец интервала.');

                        continue;
                    }

                    if ($opensAt === $closesAt) {
                        $errors["openingHours.$dayIndex.intervals.$intervalIndex.closes_at"] = __('Время закрытия должно отличаться от времени открытия.');

                        continue;
                    }

                    $intervals[] = [
                        'opens_at' => $opensAt,
                        'closes_at' => $closesAt,
                    ];
                }

                if ($intervals === []) {
                    $errors["openingHours.$dayIndex.intervals"] = __('Добавьте интервал или отметьте день выходным.');
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
        return [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:'.StoreLocalImageAction::MAX_IMAGE_KILOBYTES,
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
                'default_language',
                'default_currency',
                'service_charge_enabled',
                'tips_enabled',
                'order_flow_mode',
                'created_at',
                'updated_at',
            ])
            ->whereKey($this->settingsId)
            ->where('branch_id', $this->branch->id)
            ->firstOrFail();
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
}
