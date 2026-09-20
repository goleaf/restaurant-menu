<?php

declare(strict_types=1);

use App\Actions\Branches\UpdateBranchPublicProfileAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Livewire\PublicQr\GuestEntry;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\BranchPublicProfilePresenter;
use App\Support\Validation\Branches\BranchProfileRules;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('public profile links accept only web schemes', function (string $url, bool $valid): void {
    $validator = Validator::make(['websiteUrl' => $url], BranchProfileRules::branchProfile());

    expect($validator->passes())->toBe($valid);
})->with([
    ['https://example.test/restaurant', true],
    ['http://example.test', true],
    ['ftp://example.test', false],
    ['javascript:alert(1)', false],
    ['data:text/html,Hello', false],
]);

test('public profile preview uses explicit translations legacy fallback and inherited media without writes', function (): void {
    $organization = Organization::factory()->make(['logo_path' => 'organization.png']);
    $brand = Brand::factory()->make(['logo_path' => 'brand.png']);
    $branch = Branch::factory()->make([
        'name' => 'Internal name', 'public_name' => 'Legacy name', 'public_description' => 'Legacy description',
        'public_translations' => ['lt' => ['name' => 'Lietuviškas vardas'], 'ru' => ['description' => 'Русское описание']],
        'website_url' => 'javascript:alert(1)', 'phone' => '+00370 600 001',
    ]);
    $branch->setRelation('organization', $organization)->setRelation('brand', $brand);
    $presenter = app(BranchPublicProfilePresenter::class);
    $lt = $presenter->present($branch, 'lt', 'en');
    $ru = $presenter->present($branch, 'ru', 'lt');
    $en = $presenter->present($branch, 'en', 'en');

    expect($lt['venue_name'])->toBe('Lietuviškas vardas')
        ->and($lt['public_description'])->toBe('Legacy description')
        ->and($ru['venue_name'])->toBe('Lietuviškas vardas')
        ->and($ru['public_description'])->toBe('Русское описание')
        ->and($en['venue_name'])->toBe('Legacy name')
        ->and($lt['logo_source'])->toBe('brand')
        ->and($lt['website_url'])->toBeNull()
        ->and($lt['phone'])->toBe('+00370 600 001')
        ->and($branch->exists)->toBeFalse();
});

test('profile save changes only supplied profile fields and replays once', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    $settings = BranchSetting::factory()->for($branch)->create(['default_currency' => 'USD', 'polling_interval_seconds' => 0]);
    $fingerprint = UpdateBranchPublicProfileAction::fingerprint($branch);
    $requestId = (string) Str::uuid();
    $data = ['phone' => '+000370 600', 'currency' => 'GBP', 'order_flow_mode' => 'custom', 'logo_path' => 'forged.png'];
    $action = app(UpdateBranchPublicProfileAction::class);
    $result = $action->handle($actor, $branch, $data, $fingerprint, $requestId);
    $replay = $action->handle($actor, $branch, $data, $fingerprint, $requestId);

    expect($branch->fresh()->phone)->toBe('+000370 600')
        ->and($branch->fresh()->currency)->toBe('EUR')
        ->and($branch->fresh()->logo_path)->toBeNull()
        ->and($settings->fresh()->default_currency)->toBe('USD')
        ->and($settings->fresh()->polling_interval_seconds)->toBe(0)
        ->and($replay)->toBe($result)
        ->and(AuditLog::query()->where('branch_id', $branch->id)->count())->toBe(1);
});

test('stale profile changes conflict while unrelated structural changes do not', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    $fingerprint = UpdateBranchPublicProfileAction::fingerprint($branch);
    $branch->update(['address' => 'Changed street']);
    $action = app(UpdateBranchPublicProfileAction::class);
    $action->handle($actor, $branch, ['phone' => '+370 1'], $fingerprint, (string) Str::uuid());

    expect(fn () => $action->handle($actor, $branch, ['phone' => '+370 2'], $fingerprint, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($branch->fresh()->phone)->toBe('+370 1');
});

test('explicit translation clearing preserves omitted languages and legacy text', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    $branch->update(['public_name' => 'Legacy', 'public_translations' => ['en' => ['name' => 'English'], 'lt' => ['name' => 'Lietuvių']]]);
    app(UpdateBranchPublicProfileAction::class)->handle($actor, $branch, ['public_translations' => ['lt' => ['name' => null]]], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid());

    expect($branch->fresh()->public_translations)->toBe(['en' => ['name' => 'English'], 'lt' => ['name' => null]])
        ->and($branch->fresh()->public_name)->toBe('Legacy');
});

test('corrupt stored translations remain intact and receive validation instead of implicit replacement', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    $branch->update(['public_translations' => 'legacy-invalid']);
    $action = app(UpdateBranchPublicProfileAction::class);
    $action->handle($actor, $branch, ['phone' => '+00370'], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid());
    $branch->refresh();
    expect(fn () => $action->handle($actor, $branch, ['public_translations' => ['en' => ['name' => 'English']]], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($branch->fresh()->public_translations)->toBe('legacy-invalid')->and($branch->fresh()->phone)->toBe('+00370');
});

test('malformed stored translation JSON remains visible to profile validation in every language', function (string $locale): void {
    app()->setLocale($locale);
    [$actor, $branch] = profileSectionsFixture();
    Branch::query()->whereKey($branch->id)->update(['public_translations' => '{broken']);
    $branch->refresh();

    $component = Livewire::actingAs($actor)->test(Settings::class, [
        'organization' => $branch->organization, 'brand' => $branch->brand, 'branch' => $branch,
    ])->assertViewHas('invalidStoredGroups', fn (array $groups): bool => ($groups['profile'] ?? false) === true)
        ->assertSee(__('settings.invalid_stored'))
        ->assertSet('profileForm.translations', '{broken')
        ->set('profileForm.phone', '+00370 600')
        ->call('saveProfile')
        ->assertHasErrors(['profileForm.translations'])
        ->assertSet('profileForm.phone', '+00370 600');

    expect($component->instance()->getErrorBag()->first('profileForm.translations'))->toContain(__('settings.profile.translations'))
        ->and($branch->fresh()->getRawOriginal('public_translations'))->toBe('{broken')
        ->and($branch->fresh()->phone)->toBeNull();
})->with(['en', 'lt', 'ru']);

test('phone-only profile save preserves malformed JSON and translation writes reject it', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    Branch::query()->whereKey($branch->id)->update(['public_translations' => '{broken']);
    $branch->refresh();
    $action = app(UpdateBranchPublicProfileAction::class);
    $action->handle($actor, $branch, ['phone' => '+00370'], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid());
    $branch->refresh();

    $audit = AuditLog::query()->where('branch_id', $branch->id)->sole();
    expect($branch->getRawOriginal('public_translations'))->toBe('{broken')->and($branch->phone)->toBe('+00370')
        ->and($audit->old_values['public_translations'])->toBe('{broken')
        ->and($audit->new_values['public_translations'])->toBe('{broken');
    expect(fn () => $action->handle($actor, $branch, ['public_translations' => ['en' => ['name' => 'English']]], UpdateBranchPublicProfileAction::fingerprint($branch), (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($branch->fresh()->getRawOriginal('public_translations'))->toBe('{broken');
});

test('profile fingerprint detects changes between distinct invalid stored translation values', function (string $first, string $second): void {
    [$actor, $branch] = profileSectionsFixture();
    Branch::query()->whereKey($branch->id)->update(['public_translations' => $first]);
    $branch->refresh();
    $fingerprint = UpdateBranchPublicProfileAction::fingerprint($branch);
    Branch::query()->whereKey($branch->id)->update(['public_translations' => $second]);

    expect(UpdateBranchPublicProfileAction::fingerprint($branch->fresh()))->not->toBe($fingerprint);
    expect(fn () => app(UpdateBranchPublicProfileAction::class)->handle($actor, $branch, ['phone' => '+00370'], $fingerprint, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($branch->fresh()->getRawOriginal('public_translations'))->toBe($second)->and($branch->fresh()->phone)->toBeNull();
})->with([
    'distinct malformed JSON' => ['{first', '{second'],
    'malformed JSON and JSON scalar with the same decoded text' => ['{broken', '"{broken"'],
    'malformed JSON and a stored object imitating its fingerprint marker' => ['{broken', json_encode(['invalid_storage_fingerprint' => hash('sha256', '{broken')], JSON_THROW_ON_ERROR)],
]);

test('real qr profile and locale updates use the same translated name and description as preview', function (): void {
    [, $branch] = profileSectionsFixture();
    $branch->update(['public_name' => 'Legacy venue', 'public_translations' => [
        'lt' => ['name' => 'Lietuviškas restoranas', 'description' => 'Lietuviškas aprašymas'],
        'ru' => ['name' => 'Русский ресторан', 'description' => 'Русское описание'],
    ]]);
    $point = ServicePoint::factory()->for($branch)->create();
    $qr = QrCode::factory()->for($point)->create();

    $this->get(route('public.qr.show', ['token' => $qr->public_token, 'lang' => 'lt']))
        ->assertOk()->assertSeeText('Lietuviškas restoranas')->assertSeeText('Lietuviškas aprašymas');
    Livewire::test(GuestEntry::class, ['token' => $qr->public_token, 'language' => 'lt'])
        ->set('guestName', 'Draft visitor')
        ->call('synchronizeGuestLocale', 'ru')
        ->assertSet('landing.venue_name', 'Русский ресторан')
        ->assertSet('landing.public_description', 'Русское описание')
        ->assertSet('guestName', 'Draft visitor');
});

test('profile validation errors are localized and preserve errors from other groups', function (string $locale): void {
    app()->setLocale($locale);
    [$actor, $branch] = profileSectionsFixture();
    $component = Livewire::actingAs($actor)->test(Settings::class, [
        'organization' => $branch->organization, 'brand' => $branch->brand, 'branch' => $branch,
    ])->set('settlement.serviceChargePercent', 'wrong')->call('saveSettlement')
        ->assertHasErrors(['settlement.serviceChargePercent']);
    $component->set('profileForm.email', 'wrong')->call('saveProfile')
        ->assertHasErrors(['profileForm.email', 'settlement.serviceChargePercent']);
    expect($component->instance()->getErrorBag()->first('profileForm.email'))->toContain(__('validation.attributes.email'));
    $component->set('profileForm.email', 'guest@example.test')->call('saveProfile')
        ->assertHasNoErrors(['profileForm.email'])->assertHasErrors(['settlement.serviceChargePercent']);
})->with(['en', 'lt', 'ru']);

test('unsaved profile preview applies the same plain text normalization as saving', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    Livewire::actingAs($actor)->test(Settings::class, ['organization' => $branch->organization, 'brand' => $branch->brand, 'branch' => $branch])
        ->set('profileForm.publicName', '  <b>Public venue</b>  ')
        ->set('profileForm.publicDescription', '<p>Public description</p>')
        ->call('openPreview', true)
        ->assertViewHas('preview', fn (array $preview): bool => $preview['venue_name'] === 'Public venue' && $preview['public_description'] === 'Public description')
        ->call('saveProfile')->assertHasNoErrors();
    expect($branch->fresh()->public_name)->toBe('Public venue')->and($branch->fresh()->public_description)->toBe('Public description');
});

test('translated preview normalizes title spacing exactly as saving', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    Livewire::actingAs($actor)->test(Settings::class, ['organization' => $branch->organization, 'brand' => $branch->brand, 'branch' => $branch])
        ->set('profileForm.translations.en.name', '  Public   venue  ')
        ->call('openPreview', true)
        ->assertViewHas('preview', fn (array $preview): bool => $preview['venue_name'] === 'Public venue')
        ->call('saveProfile')->assertHasNoErrors();
    expect($branch->fresh()->public_translations['en']['name'])->toBe('Public venue');
});

test('malformed nested translation input renders safely and is rejected without discarding drafts', function (): void {
    [$actor, $branch] = profileSectionsFixture();
    Livewire::actingAs($actor)->test(Settings::class, ['organization' => $branch->organization, 'brand' => $branch->brand, 'branch' => $branch])
        ->set('profileForm.publicName', 'Retained draft')
        ->set('profileForm.translations.en.name', ['hostile'])
        ->call('openPreview', true)
        ->assertViewHas('preview', fn (array $preview): bool => $preview['venue_name'] === 'Retained draft')
        ->call('saveProfile')->assertHasErrors(['profileForm.translations.en.name'])
        ->assertSet('profileForm.publicName', 'Retained draft')
        ->assertSet('profileForm.translations.en.name', ['hostile']);
    expect($branch->fresh()->public_name)->toBeNull()->and($branch->fresh()->public_translations)->toBeNull();
});

/** @return array{User, Branch} */
function profileSectionsFixture(): array
{
    test()->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Profile sections']);
    $brand = Brand::factory()->for($organization)->create();

    return [$actor, Branch::factory()->forBrand($brand)->create()];
}
