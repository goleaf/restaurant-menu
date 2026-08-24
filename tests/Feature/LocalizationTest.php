<?php

declare(strict_types=1);

use App\Actions\TableSessions\ApproveTableSessionJoinRequestAction;
use App\Actions\TableSessions\CreateGuestPendingTableSessionAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SupportedLocale;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Livewire\Settings\Profile;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\User;
use App\Support\LocalizedDateFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('localization foundation supports fixed interface languages', function () {
    expect(Schema::hasColumn('users', 'locale'))->toBeTrue()
        ->and(Schema::hasColumn('table_session_guests', 'locale'))->toBeTrue()
        ->and(Schema::hasColumn('table_session_join_requests', 'locale'))->toBeTrue()
        ->and(SupportedLocale::values())->toBe(['ru', 'en', 'lt'])
        ->and(SupportedLocale::normalize('lt_LT'))->toBe('lt')
        ->and(SupportedLocale::normalize('de', 'ru'))->toBe('ru');
});

test('language labels are translated through the shared json catalog', function () {
    $expected = [
        'en' => ['ru' => 'Russian', 'en' => 'English', 'lt' => 'Lithuanian'],
        'lt' => ['ru' => 'Rusų', 'en' => 'Anglų', 'lt' => 'Lietuvių'],
        'ru' => ['ru' => 'Русский', 'en' => 'Английский', 'lt' => 'Литовский'],
    ];

    foreach ($expected as $interfaceLocale => $labels) {
        App::setLocale($interfaceLocale);

        expect(SupportedLocale::labels())->toBe($labels);
    }
});

test('dates times and relative labels follow the active interface locale', function () {
    $value = CarbonImmutable::parse('2026-08-24 14:05:00');

    App::setLocale('en');
    $english = LocalizedDateFormatter::dateTime($value);

    App::setLocale('lt');
    $lithuanian = LocalizedDateFormatter::dateTime($value);

    App::setLocale('ru');
    $russian = LocalizedDateFormatter::dateTime($value);

    expect($english)->not->toBe($lithuanian)
        ->and($lithuanian)->not->toBe($russian)
        ->and(LocalizedDateFormatter::date($value))->not->toBeNull()
        ->and(LocalizedDateFormatter::time($value))->not->toBeNull()
        ->and(LocalizedDateFormatter::relative($value))->not->toBeNull()
        ->and(LocalizedDateFormatter::dateTime(null))->toBeNull();
});

test('livewire pagination uses semantic localized labels instead of vendor phrase fallbacks', function () {
    App::setLocale('ru');

    $paginator = new LengthAwarePaginator(
        items: range(11, 20),
        total: 30,
        perPage: 10,
        currentPage: 2,
        options: ['path' => '/organizations', 'pageName' => 'page'],
    );
    $html = view('livewire::tailwind', [
        'paginator' => $paginator,
        'elements' => [[
            1 => '/organizations?page=1',
            2 => '/organizations?page=2',
            3 => '/organizations?page=3',
        ]],
    ])->render();

    expect($html)->toContain('Навигация по страницам')
        ->toContain('Предыдущая')
        ->toContain('Следующая')
        ->toContain('Результаты 11–20 из 30')
        ->not->toContain('Showing');
});

test('lithuanian and russian plural forms handle compound counts', function () {
    $expected = [
        'lt' => [
            0 => 'Nėra pozicijų',
            1 => '1 pozicija',
            2 => '2 pozicijos',
            5 => '5 pozicijos',
            10 => '10 pozicijų',
            11 => '11 pozicijų',
            20 => '20 pozicijų',
            21 => '21 pozicija',
            22 => '22 pozicijos',
            25 => '25 pozicijos',
        ],
        'ru' => [
            0 => 'Нет позиций',
            1 => '1 позиция',
            2 => '2 позиции',
            5 => '5 позиций',
            10 => '10 позиций',
            11 => '11 позиций',
            20 => '20 позиций',
            21 => '21 позиция',
            22 => '22 позиции',
            25 => '25 позиций',
        ],
    ];

    foreach ($expected as $locale => $forms) {
        App::setLocale($locale);

        foreach ($forms as $count => $label) {
            expect(trans_choice('guest.cart.item_count', $count, ['count' => $count]))->toBe($label);
        }
    }
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

test('authenticated language switch persists the selected locale and ignores unsupported values', function () {
    Route::middleware(['web', 'auth'])->get('/__locale-switch-probe', fn () => App::currentLocale())
        ->name('localization.locale-switch-probe');

    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get('/__locale-switch-probe?lang=lt')
        ->assertOk()
        ->assertSee('lt');

    expect($user->fresh()->locale)->toBe('lt')
        ->and(session('interface_locale'))->toBe('lt');

    $this->get('/__locale-switch-probe?lang=de')
        ->assertOk()
        ->assertSee('lt');

    expect($user->fresh()->locale)->toBe('lt');
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
        ->and($user->preferredLocale())->toBe('ru')
        ->and(App::currentLocale())->toBe('ru')
        ->and(session('interface_locale'))->toBe('ru');
});

test('guest and pending join locale values are persisted independently', function () {
    expect(TableSessionGuest::factory()->create(['locale' => 'lt'])->locale)->toBe('lt')
        ->and(TableSessionJoinRequest::factory()->create(['locale' => 'ru'])->locale)->toBe('ru');
});

test('guest entry approval and menu switch preserve the guest locale', function () {
    [$qrCode, , $servicePoint] = createPrompt77GuestQrContext();
    $createGuestSession = app(CreateGuestPendingTableSessionAction::class);
    $firstToken = str_repeat('A', 64);
    $secondToken = str_repeat('B', 64);

    $firstEntry = $createGuestSession->handle($servicePoint, 'First guest', $firstToken, 'lt');
    $joinEntry = $createGuestSession->handle($servicePoint, 'Second guest', $secondToken, 'ru');

    $firstGuest = $firstEntry['guest'];
    $joinRequest = $joinEntry['join_request'];

    expect($firstGuest)->toBeInstanceOf(TableSessionGuest::class)
        ->and($firstGuest->locale)->toBe('lt')
        ->and($joinRequest)->toBeInstanceOf(TableSessionJoinRequest::class)
        ->and($joinRequest->locale)->toBe('ru');

    $approvedGuest = app(ApproveTableSessionJoinRequestAction::class)->handle($joinRequest, $firstGuest);

    expect($approvedGuest->locale)->toBe('ru');

    session()->put('guest_entries.'.$qrCode->public_token, [
        'table_session_id' => $firstEntry['table_session']->id,
        'guest_id' => $firstGuest->id,
        'guest_token' => $firstToken,
    ]);

    Livewire::test(GuestMenu::class, [
        'branchId' => $servicePoint->branch_id,
        'tableSessionId' => $firstEntry['table_session']->id,
        'currentGuestId' => $firstGuest->id,
        'publicToken' => $qrCode->public_token,
        'language' => 'lt',
    ])->set('language', 'ru');

    expect($firstGuest->fresh()->locale)->toBe('ru');
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
        ->assertSet('language', 'en');

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token, 'language' => 'en'])
        ->assertSee('Your name')
        ->assertSee('Enter your name to continue.');
});

test('dashboard entry views keep visible chrome translatable', function () {
    $dashboardSources = [
        resource_path('views/dashboard.blade.php'),
        resource_path('views/livewire/restaurant/dashboard.blade.php'),
    ];

    foreach ($dashboardSources as $dashboardSource) {
        $source = file_get_contents($dashboardSource);

        expect($source)
            ->not->toContain('>Workspace overview<')
            ->not->toContain('>Quick start<')
            ->not->toContain('>Restaurant staff<')
            ->not->toContain('>Platform staff<')
            ->not->toContain('>Restaurant workspace<')
            ->not->toContain('>Restaurant dashboard<')
            ->not->toContain('>Available step by step<')
            ->not->toContain('>Current implementation area<');
    }
});

test('payment ui uses semantic json translation keys in every locale', function () {
    $paymentKeys = [
        'payments.title',
        'payments.summary',
        'payments.table_total',
        'payments.total_paid',
        'payments.remaining',
        'payments.guest_total',
        'payments.guest_paid',
        'payments.guest_remaining',
        'payments.pay_whole_table',
        'payments.pay_guest',
        'payments.payment_history',
        'payments.close_session',
        'payments.close_session_warning',
        'payments.fully_paid',
        'payments.partially_paid',
        'payments.unpaid',
        'payments.paid',
        'ui.payment_methods.cash',
        'ui.payment_methods.card_terminal',
        'ui.payment_methods.other',
        'payments.forms.amount',
        'payments.forms.method',
        'payments.forms.note',
        'payments.forms.guest',
        'payments.messages.payment_recorded',
        'payments.messages.session_paid',
        'payments.errors.amount_required',
        'payments.errors.amount_invalid',
        'payments.errors.amount_exceeds_remaining',
        'payments.errors.method_required',
    ];

    foreach (['en', 'lt', 'ru'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true);

        expect($translations)->toBeArray()
            ->and($translations)->toHaveKeys($paymentKeys)
            ->and($translations)->not->toHaveKey('ui.payment_methods.card');
    }
});

test('staff and permission ui uses semantic json translation keys in every locale', function () {
    $staffKeys = [
        'staff.list',
        'staff.add',
        'staff.invite',
        'staff.invite_link',
        'staff.invite_code',
        'staff.create_manual',
        'staff.deactivate',
        'staff.reactivate',
        'staff.role',
        'staff.branch_access',
        'staff.organization_access',
        'staff.actions.update_permissions',
        'staff.messages.invitation_created',
        'staff.messages.staff_created',
        'staff.messages.staff_deactivated',
        'staff.roles.superadmin',
        'staff.roles.owner',
        'staff.roles.director',
        'staff.roles.restaurant_admin',
        'staff.roles.shift_manager',
        'staff.roles.waiter',
        'staff.roles.head_chef',
        'staff.roles.cook',
        'staff.roles.bartender',
        'staff.roles.cashier',
        'staff.roles.accountant',
        'staff.roles.marketer',
    ];

    $permissionKeys = [
        'permissions.groups.restaurant',
        'permissions.groups.branches',
        'permissions.groups.zones',
        'permissions.groups.service_points',
        'permissions.groups.qr',
        'permissions.groups.menu',
        'permissions.groups.orders',
        'permissions.groups.departments',
        'permissions.groups.payments',
        'permissions.groups.reports',
        'permissions.groups.staff',
        'permissions.groups.history',
        'permissions.labels.manage_menu',
        'permissions.labels.change_prices',
        'permissions.labels.change_availability',
        'permissions.labels.view_orders',
        'permissions.labels.confirm_orders',
        'permissions.labels.cancel_orders',
        'permissions.labels.send_to_departments',
        'permissions.labels.view_payments',
        'permissions.labels.manage_payments',
        'permissions.labels.view_reports',
        'permissions.labels.export_data',
        'permissions.labels.manage_staff',
        'permissions.labels.view_order_history',
    ];

    foreach (['en', 'lt', 'ru'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true);

        expect($translations)->toBeArray()
            ->and($translations)->toHaveKeys($staffKeys)
            ->and($translations)->toHaveKeys($permissionKeys);
    }
});

test('reports ui uses semantic json translation keys in every locale', function () {
    $reportKeys = [
        'reports.title',
        'reports.filters.today',
        'reports.filters.custom',
        'reports.filters.branch',
        'reports.filters.status',
        'reports.revenue.total_paid',
        'reports.revenue.net_total',
        'reports.orders.title',
        'reports.popular_items.title',
        'reports.popular_items.quantity_sold',
        'reports.payments.title',
        'reports.payments.method',
        'reports.payments.amount',
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

test('upload ui uses semantic json translation keys in every locale', function () {
    $uploadKeys = [
        'uploads.actions.choose_file',
        'uploads.actions.upload',
        'uploads.actions.remove',
        'uploads.actions.replace',
        'uploads.labels.logo',
        'uploads.labels.image',
        'uploads.labels.gallery',
        'uploads.labels.max_size',
        'uploads.labels.allowed_types',
        'uploads.messages.uploaded',
        'uploads.messages.removed',
        'uploads.errors.invalid_type',
        'uploads.errors.too_large',
        'uploads.errors.upload_failed',
        'uploads.errors.not_writable',
    ];

    $legacyUploadKeys = [
        'Allowed formats: :formats. Max size: :size.',
        'Upload a :formats image.',
        'Image must be :size or smaller.',
    ];

    foreach (['en', 'lt', 'ru'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true);

        expect($translations)->toBeArray()
            ->and($translations)->toHaveKeys($uploadKeys);

        foreach ($legacyUploadKeys as $legacyUploadKey) {
            expect($translations)->not->toHaveKey($legacyUploadKey);
        }
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
