<?php

declare(strict_types=1);

use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Enums\MenuStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;

test('guest sees allergen and dietary labels in a real browser', function () {
    $this->withVite();

    $organization = Organization::factory()->create();
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create(['currency' => 'EUR']);
    BranchSetting::factory()->for($branch)->create(['default_language' => 'en']);
    $servicePoint = ServicePoint::factory()->for($branch)->create([
        'status' => ServicePointStatus::Occupied,
        'is_active' => true,
    ]);
    $qrCode = QrCode::factory()->for($servicePoint)->create(['status' => QrCodeStatus::Active]);
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->waiterOpened()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->create([
        'status' => TableSessionGuestStatus::Active,
        'guest_name' => 'Allergy Browser Guest',
    ]);
    $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->active()->create(['name' => 'Main dishes']);
    MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->withAllergens(MenuAllergen::Gluten, MenuAllergen::Milk)
        ->withDietaryLabels(MenuDietaryLabel::Vegetarian)
        ->create(['name' => 'Allergy-aware pasta']);

    $this->withCookie(
        'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24),
        $guest->guest_token,
    );

    $page = visit(route('public.qr.show', ['token' => $qrCode->public_token], false));

    $page
        ->assertSee('Allergy-aware pasta')
        ->assertSee('Allergens')
        ->assertSee('Gluten-containing cereals')
        ->assertSee('Milk')
        ->assertSee('Dietary labels')
        ->assertSee('Vegetarian')
        ->assertSee('Tell staff about severe allergies. Labels do not guarantee the absence of traces or cross-contact.')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
