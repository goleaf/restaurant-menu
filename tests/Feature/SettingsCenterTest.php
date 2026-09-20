<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingsChange;
use App\Models\Brand;
use App\Models\User;
use App\Support\Validation\IndependentSectionValidation;
use Database\Seeders\SystemPermissionsSeeder;
use DOM\HTMLDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

function settingsCenterBranch(): Branch
{
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Settings fixture']);
    $brand = Brand::factory()->for($organization)->create();

    return Branch::factory()->forBrand($brand)->create();
}

function settingsCenterComponent(Branch $branch): Testable
{
    $branch->loadMissing('organization.owner', 'brand');

    return Livewire::actingAs($branch->organization->owner)->test(Settings::class, [
        'organization' => $branch->organization, 'brand' => $branch->brand, 'branch' => $branch,
    ]);
}

test('settings read paths and preview never initialize a legacy restaurant', function (): void {
    $branch = settingsCenterBranch();
    $audits = AuditLog::query()->count();
    settingsCenterComponent($branch)->call('selectSection', 'locale')->call('openPreview', false)->assertHasNoErrors();
    expect(BranchSetting::query()->where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(BranchSettingsChange::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe($audits);
});

test('malformed navigation query values fall back without writing restaurant data', function (string $key): void {
    $branch = settingsCenterBranch()->loadMissing('organization.owner', 'brand');
    Livewire::actingAs($branch->organization->owner)->withQueryParams([$key => ['unexpected']])
        ->test(Settings::class, ['organization' => $branch->organization, 'brand' => $branch->brand, 'branch' => $branch])
        ->assertSet('section', 'profile')->assertSet('contentLanguage', 'en')->assertSet('target', '');

    expect(BranchSetting::query()->where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(BranchSettingsChange::query()->count())->toBe(0);
})->with(['section', 'language', 'group']);

test('guest preview labels follow its content language without changing the administrator locale', function (string $language): void {
    app()->setLocale('en');
    $branch = settingsCenterBranch();
    $page = settingsCenterComponent($branch)->set('contentLanguage', $language)
        ->call('openPreview', false)
        ->assertSee(__('settings.no_contacts', [], $language));

    $page->set('profileForm.websiteUrl', 'https://restaurant.example.test')
        ->call('openPreview', true)
        ->assertSee(__('settings.profile.website_url', [], $language));

    expect(app()->getLocale())->toBe('en')
        ->and($branch->fresh()->website_url)->toBeNull()
        ->and(BranchSettingsChange::query()->count())->toBe(0);
})->with(['en', 'lt', 'ru']);

test('two stale screens save settlement and profile independently', function (): void {
    $branch = settingsCenterBranch();
    $profile = settingsCenterComponent($branch);
    $settlement = settingsCenterComponent($branch);
    $settlement->set('settlement.tipsEnabled', true)->call('saveSettlement')->assertHasNoErrors();
    $profile->set('profileForm.phone', '+370 00123')->call('saveProfile')->assertHasNoErrors();
    expect($branch->fresh()->phone)->toBe('+370 00123')
        ->and($branch->settings()->firstOrFail()->tips_enabled)->toBeTrue();
});

test('section save preserves another draft and its validation errors', function (): void {
    $branch = settingsCenterBranch();
    $page = settingsCenterComponent($branch)
        ->set('settlement.serviceChargePercent', 'bad')->call('saveSettlement')
        ->assertHasErrors('settlement.serviceChargePercent')
        ->set('profileForm.phone', '+000')->call('saveProfile')
        ->assertSet('settlement.serviceChargePercent', 'bad')
        ->assertHasErrors('settlement.serviceChargePercent')
        ->assertSet('saved.profile', true);
    $page->call('selectSection', 'settlement')->assertSet('settlement.serviceChargePercent', 'bad');
});

test('same profile conflict preserves newer save and unsaved draft', function (): void {
    $branch = settingsCenterBranch();
    $first = settingsCenterComponent($branch);
    $second = settingsCenterComponent($branch);
    $first->set('profileForm.phone', '+111')->call('saveProfile')->assertHasNoErrors();
    $second->set('profileForm.phone', '+222')->call('saveProfile')->assertHasErrors()->assertSet('profileForm.phone', '+222');
    expect($branch->fresh()->phone)->toBe('+111');
});

test('profile can save beside malformed legacy order flow without rewriting it', function (): void {
    $branch = settingsCenterBranch();
    $settings = BranchSetting::factory()->for($branch)->create(['default_currency' => 'BAD']);
    BranchSetting::query()->whereKey($settings->id)->update(['order_flow_mode' => 'legacy_unknown']);
    settingsCenterComponent($branch)->set('profileForm.publicDescription', 'Independent')->call('saveProfile')->assertHasNoErrors();
    expect($branch->fresh()->public_description)->toBe('Independent')
        ->and($settings->fresh()->getRawOriginal('order_flow_mode'))->toBe('legacy_unknown')
        ->and($settings->fresh()->default_currency)->toBe('BAD');
});

test('media validation preserves another section errors and draft', function (): void {
    $branch = settingsCenterBranch();
    settingsCenterComponent($branch)
        ->set('settlement.serviceChargePercent', 'bad')->call('saveSettlement')
        ->set('profileForm.publicDescription', 'Retained description')
        ->call('saveLogo')->assertHasErrors(['logo', 'settlement.serviceChargePercent'])
        ->assertSet('profileForm.publicDescription', 'Retained description');
    expect($branch->fresh()->logo_path)->toBeNull();
});

test('profile conflict retains another section errors and both unsaved drafts', function (): void {
    $branch = settingsCenterBranch();
    $stale = settingsCenterComponent($branch)
        ->set('settlement.serviceChargePercent', 'bad')->call('saveSettlement')
        ->set('profileForm.phone', '+222');
    settingsCenterComponent($branch)->set('profileForm.phone', '+111')->call('saveProfile')->assertHasNoErrors();
    $stale->call('saveProfile')->assertHasErrors(['profileForm.publicName', 'settlement.serviceChargePercent'])
        ->assertSet('profileForm.phone', '+222')->assertSet('settlement.serviceChargePercent', 'bad');
    expect($branch->fresh()->phone)->toBe('+111');
});

test('media conflict preserves unrelated errors and the selected replacement', function (): void {
    Storage::fake('public');
    $branch = settingsCenterBranch();
    $stale = settingsCenterComponent($branch)
        ->set('settlement.serviceChargePercent', 'bad')->call('saveSettlement')
        ->set('logo', UploadedFile::fake()->image('stale.png'));
    settingsCenterComponent($branch)->set('logo', UploadedFile::fake()->image('current.png'))->call('saveLogo')->assertHasNoErrors();
    $currentPath = $branch->fresh()->logo_path;
    $stale->call('saveLogo')->assertHasErrors(['logo', 'settlement.serviceChargePercent']);
    expect($stale->get('logo'))->not->toBeNull()->and($branch->fresh()->logo_path)->toBe($currentPath);
});

test('profile validation opens the hidden failing translation language', function (): void {
    $branch = settingsCenterBranch();
    settingsCenterComponent($branch)
        ->set('profileForm.translations.lt.description', str_repeat('a', 1201))
        ->set('profileForm.translations.en.description', 'Retained English description')
        ->call('selectSection', 'advanced')
        ->call('saveProfile')->assertHasErrors(['profileForm.translations.lt.description'])
        ->assertSet('section', 'profile')->assertSet('contentLanguage', 'lt')
        ->assertSet('profileForm.translations.en.description', 'Retained English description');
    expect($branch->fresh()->public_translations)->toBeNull();
});

test('malformed stored group values are disclosed without normalizing another save', function (): void {
    $branch = settingsCenterBranch();
    $settings = BranchSetting::factory()->for($branch)->create();
    BranchSetting::query()->whereKey($settings->id)->update(['allow_guest_invite_links' => 'legacy-invalid']);
    settingsCenterComponent($branch)->call('selectSection', 'guests')
        ->assertSee(__('settings.invalid_stored'))
        ->set('profileForm.phone', '+370 002')->call('saveProfile')->assertHasNoErrors();
    expect($settings->fresh()->getRawOriginal('allow_guest_invite_links'))->toBe('legacy-invalid');
});

test('invalid stored translation containers open their language and expose a focused localized error', function (string $locale, mixed $translation): void {
    app()->setLocale($locale);
    $branch = settingsCenterBranch();
    $branch->update(['public_translations' => ['lt' => $translation]]);
    $stored = $branch->getRawOriginal('public_translations');

    $component = settingsCenterComponent($branch)
        ->set('contentLanguage', 'en')
        ->set('profileForm.translations.en.description', 'Retained English draft')
        ->set('profileForm.phone', '+00370 600')
        ->call('selectSection', 'advanced')
        ->call('saveProfile')->assertHasErrors(['profileForm.translations.lt'])
        ->assertSet('section', 'profile')->assertSet('contentLanguage', 'lt')
        ->assertSet('profileForm.translations.en.description', 'Retained English draft')
        ->assertSet('profileForm.phone', '+00370 600')
        ->assertDispatched('settings-focus', target: 'profileForm.translations.lt');

    $document = HTMLDocument::createFromString($component->html(), LIBXML_NOERROR);
    $container = $document->querySelector('[id="profileForm.translations.lt"]');
    expect($container)->not->toBeNull()
        ->and($container->getAttribute('tabindex'))->toBe('-1')
        ->and($container->querySelector('[data-flux-error]')->textContent)->toContain(__('ui.languages.lt'))
        ->and($branch->fresh()->getRawOriginal('public_translations'))->toBe($stored)
        ->and($branch->fresh()->phone)->toBeNull()
        ->and(BranchSettingsChange::query()->count())->toBe(0);
})->with(['en', 'lt', 'ru'])->with([
    'scalar locale' => ['broken'],
    'unknown locale field' => [['unknown' => 'x']],
]);

test('settings resolves the scoped branch once per read request without persisting context', function (): void {
    $branch = settingsCenterBranch();
    $reads = 0;
    Branch::retrieved(function (Branch $record) use (&$reads, $branch): void {
        if ($record->id === $branch->id && array_key_exists('public_translations', $record->getAttributes()) && array_key_exists('pause_version', $record->getAttributes())) {
            $reads++;
        }
    });
    $page = settingsCenterComponent($branch);
    expect($reads)->toBe(1);
    $reads = 0;
    $page->call('selectSection', 'locale')->assertHasNoErrors();
    expect($reads)->toBe(1)->and(array_keys($page->snapshot['data']))->not->toContain('requestBranch');
});

test('settings reauthorizes a fresh request after membership is suspended', function (): void {
    $branch = settingsCenterBranch();
    $page = settingsCenterComponent($branch)->call('selectSection', 'locale');
    $branch->organization->users()->updateExistingPivot($branch->organization->owner_user_id, ['status' => 'suspended']);
    $page->call('saveLocale')->assertForbidden();
    expect($branch->settings()->exists())->toBeFalse()->and(BranchSettingsChange::query()->count())->toBe(0);
});

test('the same successful response presents the freshly persisted profile and media', function (): void {
    Storage::fake('public');
    $branch = settingsCenterBranch();
    settingsCenterComponent($branch)
        ->set('profileForm.phone', '+370 555 01001')->call('saveProfile')->assertHasNoErrors()
        ->assertViewHas('publicProfile', fn (array $profile): bool => $profile['phone'] === '+370 555 01001')
        ->set('logo', UploadedFile::fake()->image('new-context.png'))->call('saveLogo')->assertHasNoErrors()
        ->assertViewHas('hasOwnLogo', true)
        ->assertViewHas('publicProfile', fn (array $profile): bool => $profile['logo_url'] !== null && $profile['logo_source'] === 'restaurant');
});

test('cleanup validation opens the advanced error group without hiding another form error', function (): void {
    $branch = settingsCenterBranch();
    $settings = BranchSetting::factory()->for($branch)->create();
    BranchSetting::query()->whereKey($settings->id)->update(['pending_session_expire_minutes' => 'broken']);
    settingsCenterComponent($branch)
        ->set('profileForm.email', 'invalid')->call('saveProfile')->assertHasErrors('profileForm.email')
        ->call('previewCleanup')->assertHasErrors(['cleanup', 'profileForm.email'])
        ->assertSet('section', 'advanced')->assertDispatched('settings-focus', target: 'cleanup');
});

test('successful cleanup retry clears only cleanup errors and resets its previous report', function (): void {
    $branch = settingsCenterBranch();
    $settings = BranchSetting::factory()->for($branch)->create();
    BranchSetting::query()->whereKey($settings->id)->update(['pending_session_expire_minutes' => 'broken']);
    $page = settingsCenterComponent($branch)
        ->set('profileForm.email', 'invalid')->call('saveProfile')->assertHasErrors('profileForm.email')
        ->call('previewCleanup')->assertHasErrors('cleanup')
        ->set('advanced.pendingSessionExpireMinutes', 30)->call('saveAdvanced')
        ->assertHasErrors('profileForm.email')
        ->call('previewCleanup')->assertHasNoErrors('cleanup')->assertHasErrors('profileForm.email')
        ->call('confirmCleanup')->assertHasNoErrors('cleanup')->assertHasErrors('profileForm.email');
    expect($page->get('cleanupResult'))->not->toBeEmpty();
    $page->call('previewCleanup')->assertSet('cleanupResult', [])->assertHasErrors('profileForm.email');
});

test('repeated invalid section saves preserve distinct errors without multiplying messages', function (): void {
    $branch = settingsCenterBranch();
    $page = settingsCenterComponent($branch)
        ->set('profileForm.email', 'invalid')->call('saveProfile')->assertHasErrors('profileForm.email')
        ->set('settlement.serviceChargePercent', 'invalid');
    $original = $page->instance()->getErrorBag()->get('profileForm.email');
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $page->call('saveSettlement')->assertHasErrors(['profileForm.email', 'settlement.serviceChargePercent']);
    }
    expect($page->instance()->getErrorBag()->get('profileForm.email'))->toBe($original);
});

test('independent error preservation retains distinct messages in their original order', function (): void {
    $exception = ValidationException::withMessages(['settlement' => 'Own group error']);
    $existing = ['profileForm.email' => ['First error', 'Second error', 'First error'], 'settlement' => ['Obsolete own error']];
    IndependentSectionValidation::preserve($exception, $existing, 'settlement');
    IndependentSectionValidation::preserve($exception, $existing, 'settlement');
    expect($exception->errors())->toBe(['settlement' => ['Own group error'], 'profileForm.email' => ['First error', 'Second error']]);
});
