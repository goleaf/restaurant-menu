<?php

declare(strict_types=1);

use App\Actions\Branches\EnsureBranchSettingsAction;
use App\Actions\Branches\SaveBranchConfigurationAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Forms\BranchSettingsForm;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchSetting;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\Branches\BranchSettingsQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
});

test('branch settings mount keeps a fixed read budget for empty and full schedules', function (int $intervalCount): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();

    if ($intervalCount > 0) {
        BranchOpeningHour::factory()->for($branch)->count($intervalCount)
            ->sequence(fn (Sequence $sequence): array => [
                'day_of_week' => intdiv($sequence->index, 4) + 1,
                'opens_at' => sprintf('%02d:00', 9 + ($sequence->index % 4) * 2),
                'closes_at' => sprintf('%02d:00', 11 + ($sequence->index % 4) * 2),
                'sort_order' => (($sequence->index % 4) + 1) * 10,
            ])->create();
    }

    $organization = $branch->organization()->firstOrFail();
    $brand = $branch->brand()->firstOrFail();
    $this->actingAs($owner);
    $component = new Settings;
    $component->form = new BranchSettingsForm($component, 'form');
    $component->boot(app(BranchSettingsQueryService::class));
    $queryCount = countDatabaseQueries(fn () => $component->mount(
        $organization,
        $brand,
        $branch,
        app(EnsureBranchSettingsAction::class),
    ));

    expect($queryCount)->toBe(13)
        ->and($component->settingsId)->toBe($settings->id)
        ->and($component->form->defaultCurrency)->toBe('EUR')
        ->and($component->form->openingHoursConfigured)->toBe($intervalCount > 0)
        ->and($component->form->openingHours)->toHaveCount(7)
        ->and($component->form->openingHours[0]['intervals'])->toHaveCount($intervalCount > 0 ? 4 : 1);
})->with(['unconfigured schedule' => 0, 'full weekly schedule' => 28]);

test('the aggregate configuration action authorizes its actor and returns the persisted branch and settings', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();

    $result = app(SaveBranchConfigurationAction::class)->handle(
        $owner,
        $branch,
        $settings->id,
        aggregateBranchConfigurationData(),
    );

    expect($result['branch']->id)->toBe($branch->id)
        ->and($result['branch']->public_name)->toBe('Updated restaurant')
        ->and($result['branch']->currency)->toBe('USD')
        ->and($result['settings']->id)->toBe($settings->id)
        ->and($result['settings']->branch_id)->toBe($branch->id)
        ->and($result['settings']->polling_interval_seconds)->toBe(5)
        ->and($settings->refresh()->default_currency)->toBe('USD')
        ->and($branch->refresh()->public_name)->toBe('Updated restaurant');
});

test('the aggregate configuration action rejects an unauthorized actor before changing data or storing files', function (): void {
    [, $branch, $settings] = createAggregateBranchConfigurationContext();
    $outsider = User::factory()->create();
    $originalBranch = $branch->getRawOriginal();
    $originalSettings = $settings->getRawOriginal();
    $data = aggregateBranchConfigurationData();
    $data['logo'] = UploadedFile::fake()->image('unauthorized.png');

    expect(fn () => app(SaveBranchConfigurationAction::class)->handle($outsider, $branch, $settings->id, $data))
        ->toThrow(AuthorizationException::class);

    expect($branch->refresh()->getRawOriginal())->toBe($originalBranch)
        ->and($settings->refresh()->getRawOriginal())->toBe($originalSettings);
    Storage::disk('public')->assertDirectoryEmpty('/');
});

test('the aggregate configuration action refuses settings from a different branch even when the actor owns both', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();
    $otherBranch = Branch::factory()->forBrand($branch->brand()->firstOrFail())
        ->withDefaultSettings()->create();
    $otherSettings = $otherBranch->settings()->firstOrFail();
    $originalSettings = $settings->getRawOriginal();
    $originalOtherSettings = $otherSettings->getRawOriginal();
    $originalBranch = $branch->getRawOriginal();

    expect(fn () => app(SaveBranchConfigurationAction::class)->handle(
        $owner,
        $branch,
        $otherSettings->id,
        aggregateBranchConfigurationData(),
    ))->toThrow(ModelNotFoundException::class);

    expect($settings->refresh()->getRawOriginal())->toBe($originalSettings)
        ->and($otherSettings->refresh()->getRawOriginal())->toBe($originalOtherSettings)
        ->and($branch->refresh()->getRawOriginal())->toBe($originalBranch);
});

test('the aggregate configuration action ignores forged tenant attributes on the supplied branch model', function (): void {
    [$owner, $ownedBranch] = createAggregateBranchConfigurationContext();
    [, $foreignBranch, $foreignSettings] = createAggregateBranchConfigurationContext();
    $originalBranch = $foreignBranch->getRawOriginal();
    $originalSettings = $foreignSettings->getRawOriginal();
    $foreignBranch->organization_id = $ownedBranch->organization_id;
    $foreignBranch->brand_id = $ownedBranch->brand_id;

    expect(fn () => app(SaveBranchConfigurationAction::class)->handle(
        $owner,
        $foreignBranch,
        $foreignSettings->id,
        aggregateBranchConfigurationData(),
    ))->toThrow(AuthorizationException::class);

    expect($foreignBranch->refresh()->getRawOriginal())->toBe($originalBranch)
        ->and($foreignSettings->refresh()->getRawOriginal())->toBe($originalSettings);
});

test('the aggregate configuration action rechecks persisted tenant ownership after a branch was reassigned', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();
    [, $otherBranch] = createAggregateBranchConfigurationContext();
    expect(Gate::forUser($owner)->allows('manageSettings', $branch))->toBeTrue();

    Branch::query()->whereKey($branch->id)->update([
        'organization_id' => $otherBranch->organization_id,
        'brand_id' => $otherBranch->brand_id,
    ]);
    $originalSettings = $settings->getRawOriginal();

    expect(fn () => app(SaveBranchConfigurationAction::class)->handle(
        $owner,
        $branch,
        $settings->id,
        aggregateBranchConfigurationData(),
    ))->toThrow(AuthorizationException::class);

    expect($branch->refresh()->organization_id)->toBe($otherBranch->organization_id)
        ->and($branch->brand_id)->toBe($otherBranch->brand_id)
        ->and($branch->public_name)->toBeNull()
        ->and($settings->refresh()->getRawOriginal())->toBe($originalSettings);
});

test('the aggregate configuration action rechecks membership revocation after an earlier successful authorization', function (): void {
    [$owner, $branch, $settings, $membership] = createAggregateBranchConfigurationContext();
    $owner->load('organizationMemberships');
    expect(Gate::forUser($owner)->allows('manageSettings', $branch))->toBeTrue();
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    expect($membership->refresh()->status)->toBe(OrganizationUserStatus::Suspended);
    $originalSettings = $settings->getRawOriginal();

    expect(fn () => app(SaveBranchConfigurationAction::class)->handle(
        $owner,
        $branch,
        $settings->id,
        aggregateBranchConfigurationData(),
    ))->toThrow(AuthorizationException::class);

    expect($branch->refresh()->public_name)->toBeNull()
        ->and($settings->refresh()->getRawOriginal())->toBe($originalSettings);
});

test('the aggregate configuration action rejects a branch archived since its form was opened', function (): void {
    [$owner, $branch, $settings] = createAggregateBranchConfigurationContext();
    Branch::query()->whereKey($branch->id)->delete();
    $originalSettings = $settings->getRawOriginal();

    expect(fn () => app(SaveBranchConfigurationAction::class)->handle(
        $owner,
        $branch,
        $settings->id,
        aggregateBranchConfigurationData(),
    ))->toThrow(ModelNotFoundException::class);

    expect(Branch::withTrashed()->findOrFail($branch->id)->public_name)->toBeNull()
        ->and($settings->refresh()->getRawOriginal())->toBe($originalSettings);
});

/** @return array{User, Branch, BranchSetting, OrganizationUser} */
function createAggregateBranchConfigurationContext(): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create();
    $membership = OrganizationUser::factory()->forOrganization($organization)->forUser($owner)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $branch = Branch::factory()->for($organization)->withDefaultSettings()->create();

    return [$owner, $branch->refresh(), $branch->settings()->firstOrFail(), $membership];
}

/** @return array<string, mixed> */
function aggregateBranchConfigurationData(): array
{
    return [
        'settings' => [
            'require_waiter_confirmation_for_orders' => true,
            'allow_guest_created_sessions' => true,
            'allow_waiter_opened_sessions' => true,
            'allow_guest_invite_links' => true,
            'guest_join_requires_approval' => true,
            'polling_interval_seconds' => 5,
            'inactivity_warning_minutes' => 45,
            'pending_session_expire_minutes' => 30,
            'default_language' => 'en',
            'default_currency' => 'USD',
            'service_charge_enabled' => false,
            'service_charge_percent' => '0.00',
            'tips_enabled' => false,
            'order_flow_mode' => 'waiter_confirmation',
            'service_modes' => ['dine_in'],
        ],
        'profile' => [
            'public_name' => 'Updated restaurant',
            'public_description' => null,
            'phone' => null,
            'email' => null,
            'website_url' => null,
            'instagram_url' => null,
            'facebook_url' => null,
            'tiktok_url' => null,
        ],
        'closure' => ['closed' => false, 'reason' => null, 'until' => null],
        'opening_hours' => [],
        'opening_hours_configured' => false,
        'logo' => null,
        'cover' => null,
    ];
}
