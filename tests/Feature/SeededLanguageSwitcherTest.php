<?php

use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Enums\SupportedLocale;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\QrCode;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;

test('translation audit passes for seeded demo ui', function () {
    $this->artisan('translations:audit')->assertSuccessful();
});

test('demo seeder creates database translations for every supported menu locale', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $branch = seededLanguageDemoBranch();
    $payloads = collect(SupportedLocale::values())
        ->mapWithKeys(fn (string $locale): array => [
            $locale => app(GetGuestMenuForBranchAction::class)->handle($branch->id, $locale),
        ]);

    expect(MenuCategory::query()->count())->toBeGreaterThan(0)
        ->and(MenuItem::query()->count())->toBeGreaterThan(0);

    foreach (SupportedLocale::values() as $locale) {
        expect($payloads[$locale]['language'])->toBe($locale)
            ->and(MenuCategory::query()
                ->whereDoesntHave('translations', fn ($query) => $query->where('language_code', $locale))
                ->exists())->toBeFalse()
            ->and(MenuItem::query()
                ->whereDoesntHave('translations', fn ($query) => $query->where('language_code', $locale))
                ->exists())->toBeFalse();
    }

    expect($payloads['en']['categories'][0]['name'])->toBe('Pizza')
        ->and($payloads['lt']['categories'][0]['name'])->toBe('Picos')
        ->and($payloads['ru']['categories'][0]['name'])->toBe('Пицца')
        ->and($payloads['en']['categories'][0]['items'][0]['name'])->toBe('Margherita')
        ->and($payloads['lt']['categories'][0]['items'][0]['name'])->toBe('Margarita')
        ->and($payloads['ru']['categories'][0]['items'][0]['name'])->toBe('Маргарита');
});

test('seeded guest language switcher persists and renders translated ui and menu content', function () {
    $this->seed(DemoRestaurantSeeder::class);

    $branch = seededLanguageDemoBranch();
    $qrCode = seededLanguageDemoQrCode();

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('language', 'en')
        ->assertSeeText('Your name')
        ->set('language', 'lt')
        ->assertSet('language', 'lt')
        ->assertSeeText('Jūsų vardas');

    expect(session('interface_locale'))->toBe('lt')
        ->and(App::currentLocale())->toBe('lt');

    Livewire::test(GuestMenu::class, [
        'branchId' => $branch->id,
        'currency' => 'EUR',
        'language' => 'en',
    ])
        ->assertSet('language', 'en')
        ->assertSeeText('Pizza')
        ->assertSeeText('Margherita')
        ->assertSeeText('Tomato sauce, mozzarella, basil.')
        ->set('language', 'lt')
        ->assertSet('language', 'lt')
        ->assertSeeText('Picos')
        ->assertSeeText('Margarita')
        ->assertSeeText('Pomidorų padažas, mocarela, bazilikas.')
        ->set('language', 'ru')
        ->assertSet('language', 'ru')
        ->assertSeeText('Пицца')
        ->assertSeeText('Маргарита')
        ->assertSeeText('Томатный соус, моцарелла, базилик.')
        ->set('language', 'de')
        ->assertSet('language', 'en')
        ->assertSeeText('Margherita');

    expect(session('interface_locale'))->toBe('en');
});

function seededLanguageDemoBranch(): Branch
{
    return Branch::query()
        ->where('name', 'Bella Pizza Old Town')
        ->firstOrFail();
}

function seededLanguageDemoQrCode(): QrCode
{
    return QrCode::query()
        ->where('status', 'active')
        ->orderBy('id')
        ->firstOrFail();
}
