<?php

use App\Livewire\Onboarding\RestaurantSetup;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('restaurant onboarding wizard requires authentication', function () {
    $this->get(route('onboarding.restaurant'))
        ->assertRedirect(route('login'));
});

test('new user can create restaurant setup from onboarding wizard', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(RestaurantSetup::class)
        ->assertSee(__('ui.onboarding.restaurant_setup.nastroit_restoran'))
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_kompanii'))
        ->set('organizationName', 'Prompt 74 Food Group')
        ->call('createOrganization')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSee(__('ui.onboarding.restaurant_setup.nazvanie_restorana'))
        ->set('brandName', 'Prompt 74 Bistro')
        ->call('createBrand')
        ->assertHasNoErrors()
        ->assertSet('step', 3)
        ->set('branchName', 'Prompt 74 Bistro Old Town')
        ->set('branchAddress', 'Pilies 1')
        ->set('branchCity', 'Vilnius')
        ->set('branchCountry', 'Lithuania')
        ->set('branchTimezone', 'Europe/Vilnius')
        ->set('branchCurrency', 'EUR')
        ->call('createBranch')
        ->assertHasNoErrors()
        ->assertSet('step', 4)
        ->set('areaName', 'Главный зал')
        ->set('areaType', 'hall')
        ->call('createArea')
        ->assertHasNoErrors()
        ->assertSet('step', 5)
        ->set('tableCount', 3)
        ->set('tablePrefix', 'Стол')
        ->set('tableCapacity', 4)
        ->call('createServicePoints')
        ->assertHasNoErrors()
        ->assertSet('step', 6)
        ->call('generateQrCodes')
        ->assertHasNoErrors()
        ->assertSet('step', 7)
        ->set('menuName', 'Основное меню')
        ->set('categoryName', 'Завтраки')
        ->set('itemName', 'Сырники')
        ->set('itemPrice', '8.50')
        ->call('createStarterMenu')
        ->assertHasNoErrors()
        ->assertSet('step', 8)
        ->assertSee(__('ui.onboarding.restaurant_setup.otkryt_gostevoe_meniu'));

    expect(Organization::query()->where('name', 'Prompt 74 Food Group')->count())->toBe(1)
        ->and(Brand::query()->where('name', 'Prompt 74 Bistro')->count())->toBe(1)
        ->and(Branch::query()->where('name', 'Prompt 74 Bistro Old Town')->count())->toBe(1)
        ->and(AreaNode::query()->where('name', 'Главный зал')->count())->toBe(1)
        ->and(ServicePoint::query()->where('name', 'like', 'Стол%')->count())->toBe(3)
        ->and(QrCode::query()->count())->toBe(3)
        ->and(Menu::query()->where('name', 'Основное меню')->count())->toBe(1)
        ->and(MenuCategory::query()->where('name', 'Завтраки')->count())->toBe(1)
        ->and(MenuItem::query()->where('name', 'Сырники')->value('price'))->toBe('8.50');

    $qrCountBeforeSecondClick = QrCode::query()->count();

    $component
        ->call('generateQrCodes')
        ->assertHasNoErrors();

    expect(QrCode::query()->count())->toBe($qrCountBeforeSecondClick);

    $qrCode = QrCode::query()
        ->select(['id', 'public_token'])
        ->oldest('id')
        ->firstOrFail();
    $publicUrl = route('public.qr.show', ['token' => $qrCode->public_token]);
    $publicPathSegments = explode('/', trim((string) parse_url($publicUrl, PHP_URL_PATH), '/'));

    expect($publicPathSegments)->toBe(['q', $qrCode->public_token]);

    $this->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertSee('Prompt 74 Bistro');
});

test('onboarding summary does not expose another users setup ids', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $organization = Organization::factory()
        ->for($owner, 'owner')
        ->create(['name' => 'Hidden Prompt 74 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Hidden Prompt 74 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Hidden Prompt 74 Branch']);
    $area = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Hidden Prompt 74 Hall']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->for($area)
        ->create(['name' => 'Hidden Prompt 74 Table']);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create();
    $menu = Menu::factory()
        ->for($branch)
        ->create(['name' => 'Hidden Prompt 74 Menu']);

    Livewire::actingAs($intruder)
        ->test(RestaurantSetup::class)
        ->set('organizationId', $organization->id)
        ->set('brandId', $brand->id)
        ->set('branchId', $branch->id)
        ->set('areaNodeId', $area->id)
        ->set('servicePointIds', [$servicePoint->id])
        ->set('qrCodeIds', [$qrCode->id])
        ->set('menuId', $menu->id)
        ->assertDontSee('Hidden Prompt 74 Group')
        ->assertDontSee('Hidden Prompt 74 Brand')
        ->assertDontSee('Hidden Prompt 74 Branch')
        ->assertDontSee('Hidden Prompt 74 Hall')
        ->assertDontSee('Hidden Prompt 74 Menu');
});
