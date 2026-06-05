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

test('reports ui uses semantic json translation keys in every locale', function () {
    $reportKeys = [
        'reports.title',
        'reports.filters.date_range',
        'reports.filters.today',
        'reports.filters.yesterday',
        'reports.filters.last_7_days',
        'reports.filters.this_month',
        'reports.filters.custom',
        'reports.filters.branch',
        'reports.filters.status',
        'reports.actions.apply_filters',
        'reports.actions.reset_filters',
        'reports.actions.export_csv',
        'reports.revenue.title',
        'reports.revenue.total_paid',
        'reports.revenue.net_total',
        'reports.revenue.by_payment_method',
        'reports.orders.title',
        'reports.orders.total_orders',
        'reports.orders.confirmed',
        'reports.orders.cancelled',
        'reports.orders.average_order_amount',
        'reports.average_check.title',
        'reports.average_check.table_average',
        'reports.average_check.guest_average',
        'reports.popular_items.title',
        'reports.popular_items.quantity_sold',
        'reports.popular_items.total_amount',
        'reports.payments.title',
        'reports.payments.method',
        'reports.payments.amount',
        'reports.cancelled.title',
        'reports.cancelled.reason',
        'reports.cancelled.cancelled_by',
    ];

    $reportSupportKeys = [
        'reports.actions.export_type_csv',
        'reports.access_required',
        'reports.access_required_popular_items',
        'reports.cached_at',
        'reports.active_tables',
        'reports.new_orders_to_waiter',
        'reports.cooking_orders',
        'reports.ready_positions',
        'reports.quick_actions.title',
        'reports.quick_actions.view_cached_branch_analytics',
        'reports.exports.title',
        'reports.exports.description',
        'reports.exports.csv_only',
        'reports.exports.warning',
        'reports.exports.menu',
        'reports.exports.tables',
        'reports.csv.order_id',
        'reports.csv.payment_id',
        'reports.csv.branch',
        'reports.csv.service_point_id',
        'reports.csv.service_point',
        'reports.csv.table_session_id',
        'reports.csv.confirmed_at',
        'reports.csv.confirmed_by',
        'reports.csv.total_price',
        'reports.csv.currency',
        'reports.csv.items',
        'reports.csv.created_at',
        'reports.csv.scope',
        'reports.csv.guest_name',
        'reports.csv.recorded_by',
        'reports.csv.paid_at',
        'reports.csv.note',
        'reports.csv.menu_id',
        'reports.csv.menu_name',
        'reports.csv.menu_status',
        'reports.csv.category_id',
        'reports.csv.category_name',
        'reports.csv.parent_category',
        'reports.csv.item_id',
        'reports.csv.item_name',
        'reports.csv.item_description',
        'reports.csv.price',
        'reports.csv.kitchen_department',
        'reports.csv.weight',
        'reports.csv.volume',
        'reports.csv.calories',
        'reports.csv.is_available',
        'reports.csv.sort_order',
        'reports.csv.area',
        'reports.csv.type',
        'reports.csv.name',
        'reports.csv.display_number',
        'reports.csv.internal_code',
        'reports.csv.capacity',
        'reports.csv.position_x',
        'reports.csv.position_y',
        'reports.csv.yes',
        'reports.csv.no',
        'reports.statuses.orders.confirmed_by_waiter',
        'reports.statuses.orders.sent_to_kitchen_bar',
        'reports.statuses.orders.in_progress',
        'reports.statuses.orders.ready',
        'reports.statuses.orders.served',
        'reports.statuses.orders.payment_requested',
        'reports.statuses.orders.paid',
        'reports.statuses.orders.closed',
        'reports.statuses.orders.cancelled',
        'reports.statuses.menu.draft',
        'reports.statuses.menu.active',
        'reports.statuses.menu.archived',
        'reports.statuses.service_points.free',
        'reports.statuses.service_points.occupied',
        'reports.statuses.service_points.reserved',
        'reports.statuses.service_points.waiting_waiter',
        'reports.statuses.service_points.has_new_order',
        'reports.statuses.service_points.cooking',
        'reports.statuses.service_points.ready_to_serve',
        'reports.statuses.service_points.payment_requested',
        'reports.statuses.service_points.paid',
        'reports.statuses.service_points.closed',
        'reports.statuses.service_points.blocked',
        'reports.service_point_types.table',
        'reports.service_point_types.bar_seat',
        'reports.service_point_types.vip_table',
        'reports.service_point_types.room',
        'reports.service_point_types.booth',
        'reports.service_point_types.sunbed',
        'reports.service_point_types.hotel_room',
        'reports.service_point_types.pickup_window',
        'reports.service_point_types.delivery_point',
        'reports.service_point_types.other',
    ];

    foreach (['en', 'lt', 'ru'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true);

        expect($translations)->toBeArray()
            ->and($translations)->toHaveKeys($reportKeys)
            ->and($translations)->toHaveKeys($reportSupportKeys);
    }
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
