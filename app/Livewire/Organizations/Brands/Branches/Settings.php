<?php

declare(strict_types=1);

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\Branches\SaveBranchConfigurationAction;
use App\Actions\TableSessions\CleanupInactiveTableSessionsAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\BranchServiceMode;
use App\Enums\SupportedCurrency;
use App\Enums\SupportedLocale;
use App\Livewire\Forms\BranchSettingsForm;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Settings extends Component
{
    use WithFileUploads;

    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public int $settingsId;

    public BranchSettingsForm $form;

    public ?string $currentLogoUrl = null;

    public ?string $currentCoverImageUrl = null;

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

        $this->populateForm($branch, $ensureBranchSettings->handle($branch));
    }

    public function save(SaveBranchConfigurationAction $saveConfiguration): void
    {
        $this->authorizeSettingsManagement();
        $data = $this->form->validatedConfiguration();
        $result = $saveConfiguration->handle($this->currentUser(), $this->branch, $this->settingsId, $data);
        $this->populateForm($result['branch'], $result['settings']);
        $this->form->reset('publicLogo', 'coverImage');
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
        return view('livewire.organizations.brands.branches.settings', [
            'availabilityUrl' => route('organizations.brands.branches.availability.index', [$this->organization, $this->brand, $this->branch]),
            'branchesUrl' => route('organizations.brands.branches.index', [$this->organization, $this->brand]),
            'branchName' => $this->branch->name,
            'contextLabel' => $this->organization->name.' / '.$this->brand->name.' / '.$this->branch->name,
            'publicDisplayName' => $this->branch->publicDisplayName(),
            'serviceModeOptions' => $this->serviceModeOptions(),
            'orderFlowModeOptions' => $this->orderFlowModeOptions(),
        ])->title(__('ui.organizations.brands.branches.settings.branch_settings'));
    }

    private function populateForm(Branch $branch, BranchSetting $settings): void
    {
        $this->branch = $branch;
        $this->settingsId = $settings->id;
        $this->form->populate($branch, $settings);
        $this->currentLogoUrl = $branch->logoUrl();
        $this->currentCoverImageUrl = $branch->coverImageUrl();
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
