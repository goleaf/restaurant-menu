<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Branches\SaveBranchSettingsGroupAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\BranchOrderFlowMode;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Availability\Index as AvailabilityIndex;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use App\Support\Branches\BranchSettingsGroup;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('branch service modes expose stored lists without changing raw attributes', function (mixed $stored, array $expected): void {
    $settings = BranchSetting::factory()->make(['service_modes' => $stored]);
    $attributes = $settings->getAttributes();
    $original = $settings->getRawOriginal();

    expect(countDatabaseQueries(fn (): array => $settings->only(['service_modes'])))->toBe(0);
    expect($settings->service_modes)->toBe($expected)
        ->and($settings->attributesToArray()['service_modes'])->toBe($expected)
        ->and($settings->getAttributes())->toBe($attributes)
        ->and($settings->getRawOriginal())->toBe($original);
})->with([
    'canonical list' => [['pickup', 'delivery'], ['pickup', 'delivery']],
    'double encoded list' => ['["pickup","delivery"]', ['pickup', 'delivery']],
    'empty list' => [[], []],
    'double encoded empty list' => ['[]', []],
    'null' => [null, []],
    'invalid JSON' => ['[invalid', []],
    'scalar' => ['pickup', []],
    'boolean' => [true, []],
    'mixed list' => [['pickup', null, 1, ['delivery']], ['pickup']],
]);

test('stored branch service modes survive opening and saving settings', function (bool $doubleEncoded): void {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    $modes = ['pickup', 'delivery'];
    $settings = $branch->settings()->firstOrFail();
    $settings->update(['service_modes' => $doubleEncoded ? json_encode($modes, JSON_THROW_ON_ERROR) : $modes]);
    $before = $settings->fresh()->getAttributes();
    $auditCount = AuditLog::query()->count();

    $this->actingAs($owner)->get(route('organizations.brands.branches.settings.index', [
        $organization, $brand, $branch,
    ]))->assertOk();

    $form = Livewire::actingAs($owner)->test(Settings::class, [
        'organization' => $organization, 'brand' => $brand, 'branch' => $branch,
    ])->assertSet('advanced.pollingIntervalSeconds', 1);

    expect($settings->fresh()->getAttributes())->toBe($before)
        ->and(AuditLog::query()->count())->toBe($auditCount);

    $form->set('advanced.pollingIntervalSeconds', 5)->call('saveAdvanced')->assertHasNoErrors();

    $saved = $settings->fresh();
    expect($saved->service_modes)->toBe($modes)
        ->and($saved->polling_interval_seconds)->toBe(5)
        ->and($saved->getRawOriginal('service_modes'))->toBe($before['service_modes']);
})->with(['canonical list' => false, 'double encoded list' => true]);

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

test('owner can update independent settings without enabling unsupported service modes', function () {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('guests.allowGuestCreatedSessions', true)
        ->assertSet('guests.allowWaiterOpenedSessions', true)
        ->assertSet('guests.allowGuestInviteLinks', true)
        ->assertSet('advanced.pollingIntervalSeconds', 1)
        ->assertSet('settlement.serviceChargePercent', '0.00')
        ->set('guests.allowGuestInviteLinks', false)->call('saveGuests')->assertHasNoErrors()
        ->set('advanced.pollingIntervalSeconds', 5)->call('saveAdvanced')->assertHasNoErrors()
        ->set('locale.defaultLanguage', 'lt')->call('saveLocale')->assertHasNoErrors()
        ->set('settlement.defaultCurrency', 'USD')
        ->set('settlement.serviceChargeEnabled', true)
        ->set('settlement.serviceChargePercent', '12.50')
        ->set('settlement.tipsEnabled', true)
        ->call('saveSettlement')->assertHasNoErrors()->call('confirmCurrency')->assertHasNoErrors();

    $settings = $branch->settings()->firstOrFail();
    expect($settings->allow_guest_created_sessions)->toBeTrue()
        ->and($settings->allow_guest_invite_links)->toBeFalse()
        ->and($settings->guest_join_requires_approval)->toBeTrue()
        ->and($settings->require_waiter_confirmation_for_orders)->toBeTrue()
        ->and($settings->polling_interval_seconds)->toBe(5)
        ->and($settings->default_language)->toBe('lt')
        ->and($settings->default_currency)->toBe('USD')
        ->and($branch->fresh()->currency)->toBe('USD')
        ->and($settings->service_charge_enabled)->toBeTrue()
        ->and($settings->service_charge_basis_points)->toBe(1250)
        ->and($settings->tips_enabled)->toBeTrue()
        ->and($settings->order_flow_mode)->toBe(BranchOrderFlowMode::WaiterConfirmation)
        ->and($settings->service_modes)->toBe(['dine_in']);
});

test('settings page reads defaults and initializes a missing row only on explicit settings save', function () {
    [$organization, $brand, , $owner] = createOrganizationBrandBranchForSettings(createSettings: false);

    $branch = Branch::query()
        ->where('brand_id', $brand->id)
        ->firstOrFail();

    expect($branch->settings()->exists())->toBeFalse();

    $component = Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->assertSet('guests.allowGuestCreatedSessions', true)
        ->assertSet('advanced.pollingIntervalSeconds', 1);

    expect($branch->settings()->exists())->toBeFalse();
    $component->set('advanced.pollingIntervalSeconds', 5)->call('saveAdvanced')->assertHasNoErrors();
    expect($branch->settings()->sole()->polling_interval_seconds)->toBe(5);
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

test('settings validation keeps polling finance and guest entry safe independently', function () {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('advanced.pollingIntervalSeconds', 0)->call('saveAdvanced')
        ->assertHasErrors(['advanced.pollingIntervalSeconds' => ['min']])
        ->set('settlement.defaultCurrency', 'EURO')
        ->set('settlement.serviceChargeEnabled', true)
        ->set('settlement.serviceChargePercent', '100.01')->call('saveSettlement')
        ->assertHasErrors(['settlement.defaultCurrency' => ['in'], 'settlement.serviceChargePercent' => ['max'], 'advanced.pollingIntervalSeconds'])
        ->set('guests.allowGuestCreatedSessions', 'guest_direct')->call('saveGuests')
        ->assertHasErrors(['guests.allowGuestCreatedSessions' => ['boolean']]);
});

test('a failed profile save preserves already saved settings and independent images', function (bool $withImages): void {
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
    $component = Livewire::actingAs($owner)->test(Settings::class, compact('organization', 'brand', 'branch'))
        ->set('profileForm.publicName', 'Changed restaurant')
        ->set('advanced.pollingIntervalSeconds', 5)->call('saveAdvanced')->assertHasNoErrors();
    if ($withImages) {
        $component->set('logo', UploadedFile::fake()->image('new-logo.png'))->call('saveLogo')->assertHasNoErrors()
            ->set('cover', UploadedFile::fake()->image('new-cover.jpg'))->call('saveCover')->assertHasNoErrors();
    }
    $savedBranch = $branch->fresh()->getRawOriginal();
    $savedFiles = Storage::disk('public')->allFiles($directory);
    $rejectProfile = true;
    Branch::updating(static function (Branch $branch) use (&$rejectProfile): ?bool {
        return $rejectProfile && $branch->isDirty('public_name') ? false : null;
    });
    expect(fn () => $component->call('saveProfile'))->toThrow(RuntimeException::class, 'The restaurant public profile could not be saved.');
    expect($settings->fresh()->polling_interval_seconds)->toBe(5)
        ->and($branch->fresh()->getRawOriginal())->toBe($savedBranch)
        ->and($branch->openingHours()->sole()->id)->toBe($originalHours->id)
        ->and(Storage::disk('public')->allFiles($directory))->toBe($savedFiles);

    $rejectProfile = false;
    $component->call('saveProfile')->assertHasNoErrors();
    expect($settings->fresh()->polling_interval_seconds)->toBe(5)
        ->and($branch->fresh()->public_name)->toBe('Changed restaurant')
        ->and($branch->fresh()->currency)->toBe('EUR')
        ->and($branch->fresh()->is_temporarily_closed)->toBeFalse();
    if ($withImages) {
        Storage::disk('public')->assertMissing([$originalLogo, $originalCover]);
        Storage::disk('public')->assertExists([$branch->fresh()->logo_path, $branch->fresh()->cover_image_path]);
        expect(Storage::disk('public')->allFiles($directory))->toHaveCount(2);
    } else {
        Storage::disk('public')->assertExists([$originalLogo, $originalCover]);
    }
})->with(['database changes' => false, 'database and image changes' => true]);

test('branch settings reject malformed transport values without changing persistence', function (string $group, string $field, mixed $value): void {
    [$organization, $brand, $branch, $owner] = createOrganizationBrandBranchForSettings();
    $settings = $branch->settings()->firstOrFail();
    $original = $settings->getRawOriginal();
    if ($field === 'serviceModes') {
        expect(fn () => app(SaveBranchSettingsGroupAction::class)->handle($owner, $branch, 'guest_process', [
            'allow_guest_created_sessions' => true, 'allow_waiter_opened_sessions' => true, 'allow_guest_invite_links' => true, 'service_modes' => $value,
        ], BranchSettingsGroup::fingerprint($branch, $settings, 'guest_process'), (string) Str::uuid()))->toThrow(ValidationException::class);
    } else {
        Livewire::actingAs($owner)->test(Settings::class, compact('organization', 'brand', 'branch'))
            ->set($group.'.'.$field, $value)->call('save'.ucfirst($group))->assertHasErrors($group.'.'.$field);
    }
    expect($branch->settings()->firstOrFail()->getRawOriginal())->toBe($original);
})->with([
    'float money' => ['settlement', 'serviceChargePercent', 12.5],
    'array money' => ['settlement', 'serviceChargePercent', ['12.50']],
    'array currency' => ['settlement', 'defaultCurrency', ['EUR']],
    'null polling' => ['advanced', 'pollingIntervalSeconds', null],
    'scalar modes' => ['guests', 'serviceModes', 'dine_in'],
    'encoded modes' => ['guests', 'serviceModes', '["pickup","delivery"]'],
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
        ->set('profileForm.publicName', 'Metadata only')->set('profileForm.temporarilyClosed', true)
        ->set('profileForm.temporaryClosedReason', 'Forged hidden operation')->set('profileForm.openingHoursConfigured', false)
        ->set('profileForm.openingHours', [])->call('saveProfile')->assertHasNoErrors();
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
