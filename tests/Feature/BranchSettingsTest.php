<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\UpdateBranchPublicProfileAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Availability\Index as AvailabilityIndex;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branch settings table has safe operational fields', function () {
    expect(Schema::hasTable('branch_settings'))->toBeTrue();
    expect(Schema::hasColumns('branch_settings', [
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
        'service_charge_basis_points',
        'tips_enabled',
        'order_flow_mode',
        'service_modes',
    ]))->toBeTrue();
});

test('creating branch creates settings with safe defaults', function () {
    [$organization, $brand] = createOrganizationBrandForSettings();

    $branch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Bella Pizza Vilnius Old Town',
        'address' => 'Pilies 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ], actor: $organization->owner);

    $settings = $branch->settings()->firstOrFail();

    expect($settings->require_waiter_confirmation_for_orders)->toBeTrue();
    expect($settings->allow_guest_created_sessions)->toBeTrue();
    expect($settings->allow_waiter_opened_sessions)->toBeTrue();
    expect($settings->allow_guest_invite_links)->toBeTrue();
    expect($settings->guest_join_requires_approval)->toBeTrue();
    expect($settings->polling_interval_seconds)->toBe(1);
    expect($settings->default_language)->toBe('en');
    expect($settings->default_currency)->toBe('EUR');
    expect($settings->service_charge_enabled)->toBeFalse();
    expect($settings->service_charge_basis_points)->toBe(0);
    expect($settings->tips_enabled)->toBeFalse();
    expect($settings->order_flow_mode)->toBe(BranchOrderFlowMode::WaiterConfirmation);
    expect($settings->service_modes)->toBe(['dine_in']);
});

test('branch settings page requires authentication', function () {
    [$organization, $brand, $branch] = createOrganizationBrandBranchForSettings();

    $this->get(route('organizations.brands.branches.settings.index', [$organization, $brand, $branch]))
        ->assertRedirect(route('login'));
});

test('owner can update branch settings', function () {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('form.requireWaiterConfirmationForOrders', true)
        ->assertSet('form.allowGuestCreatedSessions', true)
        ->assertSet('form.allowWaiterOpenedSessions', true)
        ->assertSet('form.allowGuestInviteLinks', true)
        ->assertSet('form.guestJoinRequiresApproval', true)
        ->assertSet('form.pollingIntervalSeconds', 1)
        ->assertSet('form.serviceChargePercent', '0.00')
        ->assertSet('form.serviceModes', ['dine_in'])
        ->assertSeeText('Service modes')
        ->set('form.allowGuestCreatedSessions', true)
        ->set('form.allowWaiterOpenedSessions', true)
        ->set('form.allowGuestInviteLinks', true)
        ->set('form.guestJoinRequiresApproval', false)
        ->set('form.pollingIntervalSeconds', 5)
        ->set('form.defaultLanguage', 'lt')
        ->set('form.defaultCurrency', 'usd')
        ->set('form.serviceChargeEnabled', true)
        ->set('form.serviceChargePercent', '12.50')
        ->set('form.tipsEnabled', true)
        ->set('form.orderFlowMode', BranchOrderFlowMode::StaffManaged->value)
        ->set('form.serviceModes', ['pickup', 'delivery', 'hotel_room_service', 'bar_only', 'custom'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Settings saved.');

    $settings = $branch->settings()->firstOrFail();

    expect($settings->allow_guest_created_sessions)->toBeTrue();
    expect($settings->allow_guest_invite_links)->toBeTrue();
    expect($settings->guest_join_requires_approval)->toBeFalse();
    expect($settings->polling_interval_seconds)->toBe(5);
    expect($settings->default_language)->toBe('lt');
    expect($settings->default_currency)->toBe('USD');
    expect($branch->fresh()->currency)->toBe('USD');
    expect($settings->service_charge_enabled)->toBeTrue();
    expect($settings->service_charge_basis_points)->toBe(1250);
    expect($settings->tips_enabled)->toBeTrue();
    expect($settings->order_flow_mode)->toBe(BranchOrderFlowMode::StaffManaged);
    expect($settings->service_modes)->toBe([
        'pickup',
        'delivery',
        'hotel_room_service',
        'bar_only',
        'custom',
    ]);
});

test('settings page creates missing settings for existing branch', function () {
    [$organization, $brand, , $owner] = createOrganizationBrandBranchForSettings(createSettings: false);

    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->firstOrFail();

    expect($branch->settings()->exists())->toBeFalse();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('form.requireWaiterConfirmationForOrders', true)
        ->assertSet('form.guestJoinRequiresApproval', true)
        ->assertSet('form.pollingIntervalSeconds', 1);

    expect($branch->settings()->exists())->toBeTrue();
});

test('member without branch management cannot access settings', function () {
    [$organization, $brand, $branch] = createOrganizationBrandBranchForSettings();
    $waiter = User::factory()->create();
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $organization->users()->syncWithoutDetachingOrFail([
        $waiter->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
        ],
    ]);

    Livewire::actingAs($waiter)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertForbidden();
});

test('branch must belong to route brand and organization', function () {
    [$organization, $brand, , $owner] = createOrganizationBrandBranchForSettings();
    [, , $otherBranch] = createOrganizationBrandBranchForSettings('Other Group', 'Other Brand');

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $otherBranch])
        ->assertForbidden();
});

test('settings validation keeps polling and order flow safe', function () {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('form.pollingIntervalSeconds', 0)
        ->set('form.defaultCurrency', 'EURO')
        ->set('form.orderFlowMode', 'guest_direct')
        ->set('form.serviceChargeEnabled', true)
        ->set('form.serviceChargePercent', '100.01')
        ->set('form.serviceModes', ['maps_and_couriers'])
        ->call('save')
        ->assertHasErrors([
            'form.pollingIntervalSeconds' => ['min'],
            'form.defaultCurrency' => ['size', 'in'],
            'form.orderFlowMode' => ['in'],
            'form.serviceChargePercent' => ['max'],
            'form.serviceModes.0' => ['in'],
        ]);
});

test('a failed branch configuration save preserves settings profile schedule and original images', function (bool $withImages): void {
    Storage::fake('public');
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    $directory = "media/organizations/{$organization->id}/brands/{$brand->id}/branches/{$branch->id}";
    $originalLogo = $directory.'/logos/original.png';
    $originalCover = $directory.'/covers/original.jpg';
    Storage::disk('public')->put($originalLogo, 'original logo');
    Storage::disk('public')->put($originalCover, 'original cover');
    $branch->update(['public_name' => 'Original restaurant', 'logo_path' => $originalLogo, 'cover_image_path' => $originalCover]);
    $settings = $branch->settings()->firstOrFail();
    $originalHours = BranchOpeningHour::factory()->for($branch)->create();
    $originalBranch = $branch->refresh()->getRawOriginal();
    $originalSettings = $settings->getRawOriginal();
    $component = Livewire::actingAs($owner)
        ->test(Settings::class, compact('organization', 'brand', 'branch'))
        ->set('form.pollingIntervalSeconds', 5)
        ->set('form.publicName', 'Changed restaurant')
        ->set('form.defaultCurrency', 'USD');

    if ($withImages) {
        $component->set('form.publicLogo', UploadedFile::fake()->image('new-logo.png'))
            ->set('form.coverImage', UploadedFile::fake()->image('new-cover.jpg'));
    }

    $this->mock(UpdateBranchPublicProfileAction::class)
        ->shouldReceive('handle')->once()->andThrow(new RuntimeException('Profile persistence failed.'));

    expect(fn () => $component->call('save'))->toThrow(RuntimeException::class, 'Profile persistence failed.');

    expect($settings->refresh()->getRawOriginal())->toBe($originalSettings)
        ->and($branch->refresh()->getRawOriginal())->toBe($originalBranch)
        ->and($branch->openingHours()->sole()->id)->toBe($originalHours->id);
    expect(Storage::disk('public')->allFiles($directory))->toEqualCanonicalizing([$originalLogo, $originalCover]);
    Storage::disk('public')->assertExists([$originalLogo, $originalCover]);

    app()->forgetInstance(UpdateBranchPublicProfileAction::class);
    $component->call('save')->assertHasNoErrors();

    expect($settings->refresh()->polling_interval_seconds)->toBe(5)
        ->and($branch->refresh()->public_name)->toBe('Changed restaurant')
        ->and($branch->currency)->toBe('USD')
        ->and($branch->is_temporarily_closed)->toBeFalse();

    if ($withImages) {
        Storage::disk('public')->assertMissing([$originalLogo, $originalCover]);
        Storage::disk('public')->assertExists([$branch->logo_path, $branch->cover_image_path]);
        expect(Storage::disk('public')->allFiles($directory))->toHaveCount(2);
    }
})->with(['database changes' => false, 'database and image changes' => true]);

test('branch settings reject malformed transport values without changing persistence', function (string $field, mixed $value): void {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    $original = $branch->settings()->firstOrFail()->getRawOriginal();

    Livewire::actingAs($owner)
        ->test(Settings::class, compact('organization', 'brand', 'branch'))
        ->set('form.'.$field, $value)
        ->call('save')
        ->assertHasErrors('form.'.$field);

    expect($branch->settings()->firstOrFail()->getRawOriginal())->toBe($original);
})->with([
    'float money' => ['serviceChargePercent', 12.5],
    'array money' => ['serviceChargePercent', ['12.50']],
    'array currency' => ['defaultCurrency', ['EUR']],
    'null polling' => ['pollingIntervalSeconds', null],
    'scalar modes' => ['serviceModes', 'dine_in'],
]);

test('availability form rejects malformed schedule and pause transport without persistence', function (string $field, mixed $value, string $editor, string $preview): void {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    Livewire::actingAs($owner)->test(AvailabilityIndex::class, compact('organization', 'brand', 'branch'))
        ->call($editor)->set($field, $value)->call($preview)->assertHasErrors($field);
    expect($branch->fresh()->pause_version)->toBe(0)->and($branch->openingHours()->exists())->toBeFalse();
})->with([
    'scalar schedule' => ['weekly.openingHours', 'invalid', 'openHours', 'previewSchedule'],
    'null schedule' => ['weekly.openingHours', null, 'openHours', 'previewSchedule'],
    'invalid closed state' => ['pause.mode', [], 'openPause', 'previewPause'],
]);

test('settings metadata saves ignore forged schedule and pause fields', function (): void {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    $hour = BranchOpeningHour::factory()->for($branch)->create();
    Livewire::actingAs($owner)->test(Settings::class, compact('organization', 'brand', 'branch'))
        ->set('form.publicName', 'Metadata only')->set('form.temporarilyClosed', true)
        ->set('form.temporaryClosedReason', 'Forged hidden operation')->set('form.openingHoursConfigured', false)
        ->set('form.openingHours', [])->call('save')->assertHasNoErrors();
    expect($branch->fresh()->public_name)->toBe('Metadata only')->and($branch->fresh()->is_temporarily_closed)->toBeFalse()
        ->and($branch->fresh()->pause_version)->toBe(0)->and($branch->openingHours()->sole()->id)->toBe($hour->id);
});

test('availability schedule rejects duplicate weekdays and retains the previous schedule', function (): void {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    $component = Livewire::actingAs($owner)->test(AvailabilityIndex::class, compact('organization', 'brand', 'branch'))->set('section', 'schedules')->call('openHours');
    $hours = $component->get('weekly.openingHours');
    $hours[1]['day_of_week'] = 1;
    $component->set('weekly.mode', 'weekly')->set('weekly.openingHours', $hours)
        ->call('previewSchedule')->assertHasErrors(['weekly.openingHours.1.day_of_week' => 'distinct']);
    expect($branch->openingHours()->exists())->toBeFalse();
});

test('availability schedule reports interval errors on the form and allows a corrected retry', function (): void {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    $component = Livewire::actingAs($owner)->test(AvailabilityIndex::class, compact('organization', 'brand', 'branch'))->set('section', 'schedules')->call('openHours')
        ->set('weekly.mode', 'weekly')->set('weekly.openingHours.0.is_closed', false)
        ->set('weekly.openingHours.0.intervals', [['opens_at' => '10:00', 'closes_at' => '10:00']])
        ->call('previewSchedule')->assertHasErrors('weekly.openingHours.0.intervals.0.closes_at');
    expect($branch->openingHours()->exists())->toBeFalse();
    $component->set('weekly.openingHours.0.intervals.0.closes_at', '18:00')
        ->call('previewSchedule')->call('applySchedule')->assertHasNoErrors();
    expect($branch->openingHours()->where('day_of_week', 1)->sole()->closes_at)->toStartWith('18:00');
});

function createOrganizationBrandForSettings(
    string $organizationName = 'Food Group',
    string $brandName = 'Bella Pizza',
): array {
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => $organizationName]);
    $brand = Brand::factory()->for($organization)->create(['name' => $brandName]);

    return [$organization, $brand, $owner];
}

function createOrganizationBrandBranchForSettings(
    string $organizationName = 'Food Group',
    string $brandName = 'Bella Pizza',
    bool $createSettings = true,
): array {
    [$organization, $brand, $owner] = createOrganizationBrandForSettings($organizationName, $brandName);

    if ($createSettings) {
        $branch = app(CreateBranchAction::class)->handle($brand, [
            'name' => $brandName.' Vilnius Old Town',
            'address' => 'Pilies 1',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'timezone' => 'Europe/Vilnius',
            'currency' => 'EUR',
            'is_active' => true,
        ], actor: $organization->owner);
    } else {
        $branch = Branch::factory()
            ->for($organization)
            ->for($brand)
            ->create(['name' => $brandName.' Legacy Branch']);
    }

    return [$organization, $brand, $branch, $owner];
}
