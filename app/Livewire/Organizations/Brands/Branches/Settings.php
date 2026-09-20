<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\EnsureBranchCurrencyCanChangeAction;
use App\Actions\Branches\SaveBranchMediaAction;
use App\Actions\Branches\SaveBranchSettingsGroupAction;
use App\Actions\Branches\UpdateBranchPublicProfileAction;
use App\Actions\TableSessions\ConfirmBranchSessionCleanupAction;
use App\Actions\TableSessions\PreviewBranchSessionCleanupAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Livewire\Forms\Branches\AdvancedSettingsForm;
use App\Livewire\Forms\Branches\GuestProcessForm;
use App\Livewire\Forms\Branches\LocaleSettingsForm;
use App\Livewire\Forms\Branches\SettlementSettingsForm;
use App\Livewire\Forms\BranchPublicProfileForm;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use App\Services\Branches\BranchPublicProfilePresenter;
use App\Services\Branches\BranchSettingsQueryService;
use App\Services\Branches\SettingsCenterQuery;
use App\Support\Branches\BranchSettingsGroup;
use App\Support\MoneyFormatter;
use App\Support\Validation\IndependentSectionValidation;
use App\Support\Validation\Media\ImageUploadRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Settings extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $organizationId;

    #[Locked]
    public int $brandId;

    #[Locked]
    public int $branchId;

    #[Locked]
    public int $actorId;

    #[Url(history: true, except: 'profile')]
    public string $section = 'profile';

    #[Url(as: 'language', history: true, except: 'en')]
    public string $contentLanguage = 'en';

    #[Url(as: 'group', except: '')]
    public string $target = '';

    public string $search = '';

    public BranchPublicProfileForm $profileForm;

    public GuestProcessForm $guests;

    public SettlementSettingsForm $settlement;

    public LocaleSettingsForm $locale;

    public AdvancedSettingsForm $advanced;

    #[Locked]
    public array $fingerprints = [];

    #[Locked]
    public array $requestIds = [];

    #[Locked]
    public array $baselines = [];

    #[Locked]
    public array $saved = [];

    public mixed $logo = null;

    public mixed $cover = null;

    public bool $previewOpen = false;

    public bool $previewDraft = true;

    public string $previewWidth = 'mobile';

    #[Locked]
    public array $cleanupPreview = [];

    #[Locked]
    public array $cleanupResult = [];

    #[Locked]
    public array $currencyPreview = [];

    private SettingsCenterQuery $center;

    private BranchSettingsQueryService $queries;

    private BranchPublicProfilePresenter $presenter;

    private ?Branch $requestBranch = null;

    public function boot(SettingsCenterQuery $center, BranchSettingsQueryService $queries, BranchPublicProfilePresenter $presenter): void
    {
        $this->center = $center;
        $this->queries = $queries;
        $this->presenter = $presenter;
        $this->requestBranch = null;
    }

    public function mount(Organization $organization, Brand $brand, Branch $branch): void
    {
        abort_unless($brand->organization_id === $organization->id && $branch->organization_id === $organization->id && $branch->brand_id === $brand->id, 403);
        $this->organizationId = $organization->id;
        $this->brandId = $brand->id;
        $this->branchId = $branch->id;
        $this->actorId = (int) Auth::id();
        $branch = $this->context();
        $settings = $this->queries->effective($branch);
        foreach (['profile', 'guests', 'settlement', 'locale', 'advanced'] as $group) {
            $this->populate($group, $branch, $settings);
        }
        foreach (['logo', 'cover'] as $kind) {
            $this->fingerprints[$kind] = hash('sha256', (string) $branch->getAttribute($kind === 'logo' ? 'logo_path' : 'cover_image_path'));
            $this->requestIds[$kind] = (string) Str::uuid();
        }
        $this->normalizeNavigation();
    }

    public function selectSection(string $section, string $target = ''): void
    {
        $this->context();
        abort_unless(in_array($section, ['profile', 'guests', 'settlement', 'locale', 'advanced'], true), 404);
        $this->section = $section;
        $this->target = ($this->center->targets()[$target] ?? null) === $section ? $target : '';
        $this->search = '';
        $this->dispatch('settings-focus', target: $this->target ?: $section.'-heading');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['logo', 'cover'], true)) {
            $this->saved[$property] = false;
        }
        foreach (['profile' => 'profileForm', 'guests' => 'guests', 'settlement' => 'settlement', 'locale' => 'locale', 'advanced' => 'advanced'] as $group => $prefix) {
            if (str_starts_with($property, $prefix.'.')) {
                $this->saved[$group] = false;
                if ($group === 'settlement') {
                    $this->currencyPreview = [];
                }
            }
        }
        if (in_array($property, ['section', 'contentLanguage', 'target'], true)) {
            $this->normalizeNavigation();
        }
    }

    public function saveProfile(UpdateBranchPublicProfileAction $save): void
    {
        $branch = $this->context();
        $this->section = 'profile';
        try {
            $this->requestBranch = null;
            $result = $save->handle($this->actor(), $branch, $this->profileForm->validatedPayload(), $this->fingerprints['profile'], $this->requestIds['profile']);
        } catch (ValidationException $exception) {
            $this->invalid('profile', $exception);
        }
        $this->complete('profile', $result['fingerprint']);
    }

    public function saveGuests(SaveBranchSettingsGroupAction $save): void
    {
        $this->saveGroup('guests', $save);
    }

    public function saveSettlement(SaveBranchSettingsGroupAction $save, EnsureBranchCurrencyCanChangeAction $guard): void
    {
        $branch = $this->context();
        $this->section = 'settlement';
        try {
            $data = $this->settlement->validatedData();
            if ($data['default_currency'] !== $branch->currency) {
                $guard->handle($branch, $data['default_currency']);
                $this->currencyPreview = ['from' => $branch->currency, 'to' => $data['default_currency'], 'payload' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
                Flux::modal('settings-currency')->show();

                return;
            }
        } catch (ValidationException $exception) {
            $this->invalid('settlement', $exception);
        }
        $this->saveGroup('settlement', $save);
    }

    public function confirmCurrency(SaveBranchSettingsGroupAction $save): void
    {
        $this->context();
        try {
            $data = $this->settlement->validatedData();
            if ($this->currencyPreview === [] || ! hash_equals($this->currencyPreview['payload'], hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)))) {
                throw ValidationException::withMessages(['settlement' => __('settings.errors.currency_preview')]);
            }
        } catch (ValidationException $exception) {
            $this->invalid('settlement', $exception);
        }
        $this->saveGroup('settlement', $save);
        $this->currencyPreview = [];
        Flux::modal('settings-currency')->close();
    }

    public function saveLocale(SaveBranchSettingsGroupAction $save): void
    {
        $this->saveGroup('locale', $save);
    }

    public function saveAdvanced(SaveBranchSettingsGroupAction $save): void
    {
        $this->saveGroup('advanced', $save);
    }

    public function cancelGroup(string $group): void
    {
        abort_unless(in_array($group, ['profile', 'guests', 'settlement', 'locale', 'advanced'], true), 404);
        $branch = $this->context();
        $this->populate($group, $branch, $this->queries->effective($branch));
        $prefix = $group === 'profile' ? 'profileForm' : $group;
        foreach (array_keys($this->getErrorBag()->messages()) as $key) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                $this->resetErrorBag($key);
            }
        }
        $this->saved[$group] = false;
        $this->dispatch('settings-group-clean', group: $group);
    }

    public function saveLogo(SaveBranchMediaAction $save): void
    {
        $this->saveMedia('logo', $save);
    }

    public function saveCover(SaveBranchMediaAction $save): void
    {
        $this->saveMedia('cover', $save);
    }

    public function removeImage(string $kind, SaveBranchMediaAction $save): void
    {
        abort_unless(in_array($kind, ['logo', 'cover'], true), 404);
        $this->saveMedia($kind, $save, true);
    }

    public function openPreview(bool $draft = true): void
    {
        $this->context();
        $this->previewDraft = $draft;
        $this->previewOpen = true;
    }

    public function previewCleanup(PreviewBranchSessionCleanupAction $preview): void
    {
        try {
            $this->cleanupPreview = $preview->handle($this->actor(), $this->context());
        } catch (ValidationException $exception) {
            $this->invalid('cleanup', $exception);
        }
        $this->resetErrorBag('cleanup');
        $this->cleanupResult = [];
        $this->requestIds['cleanup'] = (string) Str::uuid();
        Flux::modal('settings-cleanup')->show();
    }

    public function confirmCleanup(ConfirmBranchSessionCleanupAction $confirm): void
    {
        abort_unless($this->cleanupPreview !== [], 422);
        try {
            $branch = $this->context();
            $this->requestBranch = null;
            $this->cleanupResult = $confirm->handle($this->actor(), $branch, $this->cleanupPreview, $this->requestIds['cleanup']);
        } catch (ValidationException $exception) {
            $this->invalid('cleanup', $exception);
        }
        $this->requestBranch = null;
        $this->resetErrorBag('cleanup');
        Flux::modal('settings-cleanup')->close();
    }

    public function render(EnsureBranchCurrencyCanChangeAction $currencyGuard): View
    {
        $branch = $this->context();
        $settings = $this->queries->effective($branch);
        $this->normalizeNavigation();
        $publicProfile = $this->presenter->present($branch, $this->contentLanguage, (string) $settings->default_language);
        $previewBranch = clone $branch;
        if ($this->previewDraft) {
            $previewBranch->forceFill($this->profileForm->previewAttributes());
        }
        $profile = $this->presenter->present($previewBranch, $this->contentLanguage, (string) $settings->default_language);
        $routeParams = [$this->organizationId, $this->brandId, $this->branchId];
        $sections = array_map(fn (array $entry): array => [...$entry, 'href' => route('organizations.brands.branches.settings.index', [...$routeParams, 'section' => $entry['key']])], $this->center->sections());
        $gate = Gate::forUser($this->actor());

        return view('livewire.organizations.brands.branches.settings', [
            'branchName' => $branch->name, 'internalName' => $branch->name, 'sections' => $sections,
            'searchResults' => $this->center->search($this->search), 'languageOptions' => SupportedLocale::labels(), 'currencyOptions' => SupportedCurrency::labels(),
            'invalidStoredGroups' => $this->center->invalidStoredGroups($settings, $branch),
            'missingSettings' => ! $settings->exists, 'publicProfile' => $publicProfile, 'preview' => $profile,
            'hasOwnLogo' => filled($branch->logo_path), 'hasOwnCover' => filled($branch->cover_image_path),
            'timezone' => $branch->timezone, 'localTime' => now()->setTimezone($branch->timezone)->format('Y-m-d H:i'),
            'currencyMismatch' => $settings->default_currency !== $branch->currency,
            'currencyBlocked' => $this->section === 'settlement' && $currencyGuard->hasMonetaryData($branch),
            'legacyMode' => BranchOrderFlowMode::tryFrom((string) ($settings->getAttributes()['order_flow_mode'] ?? ''))?->label() ?? __('settings.unknown_legacy'),
            'legacyServiceModes' => array_map(fn (string $mode): string => BranchServiceMode::tryFrom($mode)?->label() ?? __('settings.unknown_legacy'), $settings->service_modes),
            'availabilityUrl' => $gate->allows('viewAvailabilityCenter', $branch) ? route('organizations.brands.branches.availability.index', $routeParams) : null,
            'identityUrl' => $gate->allows('update', $branch) ? route('restaurants.index', ['kind' => 'branch', 'object' => $branch->id]) : null,
            'demoCharge' => $this->demonstrationCharge(),
            'selectedLogoPreview' => $this->uploadPreview($this->logo),
            'selectedCoverPreview' => $this->uploadPreview($this->cover),
        ])->title(__('ui.organizations.brands.branches.settings.branch_settings'));
    }

    private function saveGroup(string $group, SaveBranchSettingsGroupAction $save): void
    {
        $branch = $this->context();
        $this->section = $group;
        $form = $this->formFor($group);
        try {
            $this->requestBranch = null;
            $result = $save->handle($this->actor(), $branch, $group === 'guests' ? 'guest_process' : $group, $form->validatedData(), $this->fingerprints[$group], $this->requestIds[$group]);
        } catch (ValidationException $exception) {
            $this->invalid($group, $exception);
        }
        $this->complete($group, $result['fingerprint']);
    }

    private function saveMedia(string $kind, SaveBranchMediaAction $save, bool $remove = false): void
    {
        $branch = $this->context();
        try {
            if (! $remove) {
                Validator::make([$kind => $this->{$kind}], ImageUploadRules::imageUpload($kind), ImageUploadRules::messages($kind), [$kind => __('settings.media.'.$kind)])->validate();
            }
            $this->requestBranch = null;
            $result = $save->handle($this->actor(), $branch, $kind, $remove ? null : $this->{$kind}, $this->fingerprints[$kind], $this->requestIds[$kind]);
        } catch (ValidationException $exception) {
            $this->invalid($kind, $exception);
        }
        $this->requestBranch = null;
        $this->fingerprints[$kind] = $result['fingerprint'];
        $this->requestIds[$kind] = (string) Str::uuid();
        $this->{$kind} = null;
        $this->resetErrorBag($kind);
        $this->saved[$kind] = true;
        $this->dispatch('settings-group-clean', group: $kind);
    }

    private function invalid(string $group, ValidationException $exception): never
    {
        $this->section = match ($group) {
            'logo', 'cover' => 'profile',
            'cleanup' => 'advanced',
            default => $group,
        };
        if ($group === 'settlement') {
            Flux::modal('settings-currency')->close();
        } elseif ($group === 'cleanup') {
            Flux::modal('settings-cleanup')->close();
        }
        $prefix = $group === 'profile' ? 'profileForm' : $group;
        if ($group === 'profile') {
            foreach (array_keys($exception->errors()) as $field) {
                if (preg_match('/^profileForm\.translations\.(en|lt|ru)(?:\.|$)/', $field, $matches)) {
                    $this->contentLanguage = $matches[1];
                    break;
                }
            }
        }
        $field = array_key_first($exception->errors());
        $target = match (true) {
            in_array($group, ['logo', 'cover'], true) => 'images',
            $group === 'cleanup' => 'cleanup',
            $field === 'profileForm.translations' => 'public-text',
            $field === 'settlement.defaultCurrency' => 'currency',
            is_string($field) && str_starts_with($field, $prefix.'.') => $field,
            default => $this->section.'-heading',
        };
        $this->dispatch('settings-focus', target: $target);
        throw IndependentSectionValidation::preserve($exception, $this->getErrorBag()->messages(), $prefix);
    }

    private function populate(string $group, Branch $branch, BranchSetting $settings): void
    {
        $form = $this->formFor($group);
        if ($group === 'profile') {
            $this->profileForm->populate($branch);
            $this->fingerprints[$group] = UpdateBranchPublicProfileAction::fingerprint($branch);
        } else {
            $form->populate($branch, $settings);
            $this->fingerprints[$group] = BranchSettingsGroup::fingerprint($branch, $settings, $group === 'guests' ? 'guest_process' : $group);
        }
        $this->baselines[$group] = $form->all();
        $this->requestIds[$group] = (string) Str::uuid();
    }

    private function complete(string $group, string $fingerprint): void
    {
        $this->requestBranch = null;
        $this->fingerprints[$group] = $fingerprint;
        $this->baselines[$group] = $this->formFor($group)->all();
        $this->requestIds[$group] = (string) Str::uuid();
        $prefix = $group === 'profile' ? 'profileForm' : $group;
        foreach (array_keys($this->getErrorBag()->messages()) as $key) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                $this->resetErrorBag($key);
            }
        }
        $this->saved[$group] = true;
        $this->dispatch('settings-group-clean', group: $group);
        Flux::toast(variant: 'success', text: __('settings.saved', ['section' => __('settings.section.'.$group)]));
    }

    private function formFor(string $group): BranchPublicProfileForm|GuestProcessForm|SettlementSettingsForm|LocaleSettingsForm|AdvancedSettingsForm
    {
        return match ($group) {
            'profile' => $this->profileForm, 'guests' => $this->guests, 'settlement' => $this->settlement,
            'locale' => $this->locale, 'advanced' => $this->advanced,
            default => throw new \InvalidArgumentException('Unknown settings form.'),
        };
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $actor->id === $this->actorId, 403);

        return $actor;
    }

    private function context(): Branch
    {
        $actor = $this->actor();

        return $this->requestBranch ??= $this->center->context($actor, $this->branchId, $this->organizationId, $this->brandId);
    }

    private function normalizeNavigation(): void
    {
        if (! in_array($this->section, ['profile', 'guests', 'settlement', 'locale', 'advanced'], true)) {
            $this->section = 'profile';
        }
        if (! in_array($this->contentLanguage, SupportedLocale::values(), true)) {
            $this->contentLanguage = 'en';
        }
        if (($this->center->targets()[$this->target] ?? null) !== $this->section) {
            $this->target = '';
        }
        if (! in_array($this->previewWidth, ['mobile', 'wide'], true)) {
            $this->previewWidth = 'mobile';
        }
    }

    private function uploadPreview(mixed $file): ?string
    {
        return $file instanceof TemporaryUploadedFile && $file->isPreviewable() ? $file->temporaryUrl() : null;
    }

    private function demonstrationCharge(): ?string
    {
        $percent = $this->settlement->serviceChargePercent;
        if (! is_string($percent) || ! preg_match('/^(?:\d{1,2}(?:\.\d{1,2})?|100(?:\.0{1,2})?)$/D', $percent)) {
            return null;
        }

        return MoneyFormatter::centsToDecimal(MoneyFormatter::percentageOf(10000, MoneyFormatter::decimalToBasisPoints($percent)));
    }
}
