<?php

use App\Actions\Branches\CreateBranchAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\AreaNode;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
});

test('branches store public restaurant profile fields', function () {
    expect(Schema::hasColumns('branches', [
        'public_name',
        'public_description',
        'cover_image_path',
        'phone',
        'email',
        'website_url',
        'instagram_url',
        'facebook_url',
        'tiktok_url',
    ]))->toBeTrue();
});

test('branch manager can update public restaurant profile from settings', function () {
    [$organization, $brand, $branch, $owner] = createPrompt101Branch();

    Livewire::actingAs($owner)
        ->test(Settings::class, ['organization' => $organization, 'brand' => $brand, 'branch' => $branch])
        ->set('publicName', 'Bella Pizza Old Town')
        ->set('publicDescription', 'Wood-fired pizza, fresh pasta, and family dinners.')
        ->set('phone', '+370 600 00000')
        ->set('email', 'hello@bella.example')
        ->set('websiteUrl', 'https://bella.example')
        ->set('instagramUrl', 'https://instagram.com/bella')
        ->set('facebookUrl', 'https://facebook.com/bella')
        ->set('tiktokUrl', 'https://tiktok.com/@bella')
        ->set('defaultLanguage', 'lt')
        ->set('defaultCurrency', 'usd')
        ->set('publicLogo', UploadedFile::fake()->image('restaurant-logo.png')->size(512))
        ->set('coverImage', UploadedFile::fake()->image('restaurant-cover.jpg')->size(1024))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Settings saved.');

    $branch->refresh();
    $settings = $branch->settings()->firstOrFail();

    expect($branch->public_name)->toBe('Bella Pizza Old Town');
    expect($branch->public_description)->toBe('Wood-fired pizza, fresh pasta, and family dinners.');
    expect($branch->phone)->toBe('+370 600 00000');
    expect($branch->email)->toBe('hello@bella.example');
    expect($branch->website_url)->toBe('https://bella.example');
    expect($branch->instagram_url)->toBe('https://instagram.com/bella');
    expect($branch->facebook_url)->toBe('https://facebook.com/bella');
    expect($branch->tiktok_url)->toBe('https://tiktok.com/@bella');
    expect($settings->default_language)->toBe('lt');
    expect($settings->default_currency)->toBe('USD');
    expect($branch->currency)->toBe('USD');
    expect($branch->logo_path)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/logos/');
    expect($branch->cover_image_path)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/covers/');

    Storage::disk('public')->assertExists($branch->logo_path);
    Storage::disk('public')->assertExists($branch->cover_image_path);
});

test('public qr landing displays branch public profile and local media', function () {
    [$organization, $brand, $branch] = createPrompt101Branch(withOwner: false);
    $area = AreaNode::factory()->for($branch)->create(['name' => 'Main Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($area)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Window Table',
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt101publicprofile',
            'short_code' => 'QR-P101',
            'status' => QrCodeStatus::Active,
        ]);

    $logoPath = 'media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/logos/logo.png';
    $coverPath = 'media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/covers/cover.jpg';
    Storage::disk('public')->put($logoPath, 'logo');
    Storage::disk('public')->put($coverPath, 'cover');

    $branch->update([
        'public_name' => 'Bella Public Profile',
        'public_description' => 'Fresh pizza near the cathedral.',
        'logo_path' => $logoPath,
        'cover_image_path' => $coverPath,
        'phone' => '+370 611 11111',
        'email' => 'vilnius@bella.example',
        'website_url' => 'https://bella.example',
        'instagram_url' => 'https://instagram.com/bella',
        'facebook_url' => 'https://facebook.com/bella',
        'tiktok_url' => 'https://tiktok.com/@bella',
    ]);
    $branch->settings()->update([
        'default_language' => 'lt',
        'default_currency' => 'USD',
    ]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSee('data-page="guest-qr-landing"', false)
        ->assertSee('/storage/'.$logoPath, false)
        ->assertSee('/storage/'.$coverPath, false)
        ->assertSeeText('Bella Public Profile')
        ->assertSeeText('Fresh pizza near the cathedral.')
        ->assertSeeText('+370 611 11111')
        ->assertSeeText('vilnius@bella.example')
        ->assertSee('https://bella.example', false)
        ->assertSee('https://instagram.com/bella', false)
        ->assertSee('https://facebook.com/bella', false)
        ->assertSee('https://tiktok.com/@bella', false)
        ->assertSeeText('Lietuvių')
        ->assertSeeText('USD');
});

test('public qr landing uses tidy fallbacks when profile details are empty', function () {
    [, , $branch] = createPrompt101Branch(withOwner: false);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Fallback Table',
            'is_active' => true,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt101fallback',
            'short_code' => 'QR-FALL',
            'status' => QrCodeStatus::Active,
        ]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSeeText($branch->name)
        ->assertSeeText('Restaurant details will appear here soon.')
        ->assertSeeText('Contact details are not published yet.')
        ->assertSeeText('en')
        ->assertSeeText('EUR');
});

function createPrompt101Branch(bool $withOwner = true): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 101 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 101 Brand']);
    $branch = app(CreateBranchAction::class)->handle($brand, [
        'name' => 'Prompt 101 Branch',
        'address' => 'Pilies 1',
        'city' => 'Vilnius',
        'country' => 'Lithuania',
        'timezone' => 'Europe/Vilnius',
        'currency' => 'EUR',
        'is_active' => true,
    ]);

    if ($withOwner) {
        return [$organization, $brand, $branch, $owner->fresh()];
    }

    return [$organization, $brand, $branch];
}
