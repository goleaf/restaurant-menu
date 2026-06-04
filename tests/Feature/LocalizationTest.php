<?php

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SupportedLocale;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Livewire\Settings\Profile;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('localization foundation supports fixed interface languages', function () {
    expect(Schema::hasColumn('users', 'locale'))->toBeTrue()
        ->and(SupportedLocale::values())->toBe(['ru', 'en', 'lt'])
        ->and(SupportedLocale::normalize('lt_LT'))->toBe('lt')
        ->and(SupportedLocale::normalize('de', 'ru'))->toBe('ru');
});

test('authenticated interface locale comes from user profile', function () {
    Route::middleware(['web', 'auth'])->get('/__locale-probe', fn () => App::currentLocale())
        ->name('localization.locale-probe');

    $user = User::factory()->create(['locale' => 'lt']);

    $this->actingAs($user)
        ->get('/__locale-probe')
        ->assertOk()
        ->assertSee('lt');
});

test('profile settings can update user interface language', function () {
    $user = User::factory()->create(['locale' => 'en']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertSet('locale', 'en')
        ->assertSee('Interface language')
        ->set('locale', 'ru')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->locale)->toBe('ru')
        ->and($user->preferredLocale())->toBe('ru');
});

test('profile settings rejects unsupported interface language', function () {
    $user = User::factory()->create(['locale' => 'en']);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('locale', 'de')
        ->call('updateProfileInformation')
        ->assertHasErrors(['locale' => ['in']]);

    expect($user->fresh()->locale)->toBe('en');
});

test('guest qr page uses branch default language and can switch language', function () {
    [$qrCode] = createPrompt77GuestQrContext('lt');

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('language', 'lt')
        ->assertSee('Jūsų vardas')
        ->assertSee('Įveskite vardą, kad tęstumėte.')
        ->set('language', 'en')
        ->assertSet('language', 'en')
        ->assertSee('Your name')
        ->assertSee('Enter your name to continue.');
});

function createPrompt77GuestQrContext(string $defaultLanguage = 'en'): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 77 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 77 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 77 Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'currency' => 'EUR',
        ]);

    BranchSetting::factory()
        ->for($branch)
        ->create(['default_language' => $defaultLanguage]);

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Localization table',
            'is_active' => true,
            'status' => ServicePointStatus::Free,
        ]);

    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt77locale'.fake()->unique()->numerify('######'),
            'short_code' => 'QR-L'.fake()->unique()->numerify('####'),
            'status' => QrCodeStatus::Active,
        ]);

    return [$qrCode, $branch, $servicePoint];
}
