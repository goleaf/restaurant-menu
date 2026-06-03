<?php

namespace App\Livewire\Organizations\Brands\Branches;

use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\Branches\UpdateBranchSettingsAction;
use App\Enums\BranchOrderFlowMode;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Branch settings')]
class Settings extends Component
{
    public Organization $organization;

    public Brand $brand;

    public Branch $branch;

    public int $settingsId;

    public bool $requireWaiterConfirmationForOrders = true;

    public bool $allowGuestCreatedSessions = false;

    public bool $allowWaiterOpenedSessions = true;

    public bool $allowGuestInviteLinks = false;

    public bool $guestJoinRequiresApproval = true;

    public int $pollingIntervalSeconds = 1;

    public string $defaultLanguage = 'en';

    public string $defaultCurrency = 'EUR';

    public bool $serviceChargeEnabled = false;

    public bool $tipsEnabled = false;

    public string $orderFlowMode = 'waiter_confirmation';

    public bool $saved = false;

    public function mount(
        Organization $organization,
        Brand $brand,
        Branch $branch,
        EnsureBranchSettingsAction $ensureBranchSettings,
    ): void {
        $this->organization = $organization;
        $this->brand = $brand;
        $this->branch = $branch;

        if (
            $brand->organization_id !== $organization->id
            || $branch->organization_id !== $organization->id
            || $branch->brand_id !== $brand->id
        ) {
            abort(403);
        }

        $user = $this->currentUser();

        if (
            ! $user->canAccessOrganization($organization)
            || ! $user->canManageOrganizationBranches($organization)
        ) {
            abort(403);
        }

        $this->fillFromSettings($ensureBranchSettings->handle($branch));
    }

    public function save(UpdateBranchSettingsAction $updateBranchSettings): void
    {
        $validated = $this->validate($this->rules());

        $settings = $updateBranchSettings->handle(
            $this->findSettings(),
            [
                'require_waiter_confirmation_for_orders' => (bool) $validated['requireWaiterConfirmationForOrders'],
                'allow_guest_created_sessions' => (bool) $validated['allowGuestCreatedSessions'],
                'allow_waiter_opened_sessions' => (bool) $validated['allowWaiterOpenedSessions'],
                'allow_guest_invite_links' => (bool) $validated['allowGuestInviteLinks'],
                'guest_join_requires_approval' => (bool) $validated['guestJoinRequiresApproval'],
                'polling_interval_seconds' => (int) $validated['pollingIntervalSeconds'],
                'default_language' => strtolower($validated['defaultLanguage']),
                'default_currency' => strtoupper($validated['defaultCurrency']),
                'service_charge_enabled' => (bool) $validated['serviceChargeEnabled'],
                'tips_enabled' => (bool) $validated['tipsEnabled'],
                'order_flow_mode' => $validated['orderFlowMode'],
            ],
        );

        $this->fillFromSettings($settings);
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
            'defaultLanguage' => ['required', 'string', 'max:10'],
            'defaultCurrency' => ['required', 'string', 'size:3'],
            'serviceChargeEnabled' => ['boolean'],
            'tipsEnabled' => ['boolean'],
            'orderFlowMode' => ['required', 'string', Rule::in(BranchOrderFlowMode::values())],
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
        $this->defaultCurrency = $settings->default_currency;
        $this->serviceChargeEnabled = $settings->service_charge_enabled;
        $this->tipsEnabled = $settings->tips_enabled;
        $this->orderFlowMode = $settings->order_flow_mode->value;
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

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
