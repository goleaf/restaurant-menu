<?php

declare(strict_types=1);

use App\Actions\Analytics\BuildBasicAnalyticsDashboardAction;
use App\Actions\Dashboard\BuildRestaurantDashboardAction;
use App\Actions\Menus\GetGuestMenuForBranchAction;
use App\Actions\Users\UpdateUserDisplayFormatsAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Livewire\PublicQr\GuestMenu;
use App\Livewire\Settings\DisplayFormats;
use App\Livewire\Settings\Profile;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Menus\CatalogData;
use App\Services\Menus\DishPreviewQuery;
use App\Support\DisplayPreferences;
use App\Support\LocalizedDateFormatter;
use App\Support\LocalizedNumberFormatter;
use App\Support\MoneyFormatter;
use App\Support\Reports\BranchReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('profile contains independent personal format settings with language defaults', function (): void {
    $this->actingAs(User::factory()->create());
    $this->get(route('profile.edit'))->assertOk()->assertSeeLivewire(DisplayFormats::class);

    Livewire::test(DisplayFormats::class)
        ->assertSet('form.date_format', 'locale')
        ->assertSet('form.time_format', 'locale')
        ->assertSet('form.number_format', 'locale');
});

test('format examples are localized and preview does not save the user', function (string $locale): void {
    App::setLocale($locale);
    $this->actingAs($user = User::factory()->create(['locale' => $locale]));

    Livewire::test(DisplayFormats::class)
        ->set('form.date_format', 'd.m.Y')
        ->set('form.time_format', '24h')
        ->set('form.number_format', 'space_comma')
        ->assertSee('24.08.2026 15:00')
        ->assertSee('1 234,56')
        ->assertDontSee('ui.settings.formats.');

    expect($user->fresh()->date_format)->toBeNull()
        ->and($user->fresh()->number_format)->toBeNull();
})->with(['en', 'lt', 'ru']);

test('saving formats persists only current user choices and reset requires save', function (): void {
    $this->actingAs($user = User::factory()->create());
    $other = User::factory()->create();
    $originalName = $user->name;
    $user->name = 'Unrelated unsaved draft';

    $component = Livewire::test(DisplayFormats::class)
        ->set('form.date_format', 'd.m.Y')
        ->set('form.time_format', '24h')
        ->set('form.number_format', 'dot_comma')
        ->call('save')->assertHasNoErrors();

    expect($user->fresh()->only(['date_format', 'time_format', 'number_format']))
        ->toBe(['date_format' => 'd.m.Y', 'time_format' => '24h', 'number_format' => 'dot_comma'])
        ->and($user->fresh()->name)->toBe($originalName)
        ->and($other->fresh()->date_format)->toBeNull();

    $this->actingAs($user->fresh());
    Livewire::test(DisplayFormats::class)->assertSet('form.date_format', 'd.m.Y');
    $component->call('resetToDefaults')->assertSet('form.date_format', 'locale');
    expect($user->fresh()->date_format)->toBe('d.m.Y');
    $component->call('save')->assertHasNoErrors();
    expect($user->fresh()->date_format)->toBeNull();
});

test('format settings reject malformed transport values without any write', function (string $field, mixed $value): void {
    $this->actingAs($user = User::factory()->create());
    Livewire::test(DisplayFormats::class)
        ->update(calls: [['method' => 'save', 'params' => [], 'path' => '']], updates: ['form.'.$field => $value])
        ->assertHasErrors(['form.'.$field]);
    expect($user->fresh()->only(['date_format', 'time_format', 'number_format']))
        ->toBe(['date_format' => null, 'time_format' => null, 'number_format' => null]);
})->with(['date_format', 'time_format', 'number_format'])->with([null, true, 24, ['unexpected'], 'arbitrary-format']);

test('unauthenticated users cannot save personal formats', function (): void {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    Livewire::test(DisplayFormats::class)->assertUnauthorized();
});

test('saved date and time preferences preserve supplied timezone and never mutate dates', function (): void {
    App::setLocale('en');
    $this->actingAs(User::factory()->create(['date_format' => 'd.m.Y', 'time_format' => '24h']));
    $date = CarbonImmutable::parse('2026-08-24 15:00:09', 'Europe/Vilnius');
    DB::flushQueryLog();
    DB::enableQueryLog();
    expect(LocalizedDateFormatter::dateTime($date))->toBe('24.08.2026 15:00')
        ->and(LocalizedDateFormatter::timeWithSeconds($date))->toBe('15:00:09')
        ->and(LocalizedDateFormatter::date(null))->toBeNull()
        ->and($date->toIso8601String())->toBe('2026-08-24T15:00:09+03:00')
        ->and(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();

    Auth::user()->time_format = '12h';
    expect(LocalizedDateFormatter::time($date->startOfDay()))->toBe('12:00 AM')
        ->and(LocalizedDateFormatter::time($date->setTime(12, 0)))->toBe('12:00 PM')
        ->and(LocalizedDateFormatter::time($date))->toBe('3:00 PM');
});

test('number and money presets apply separators without changing exact decimal values', function (string $format, string $expected): void {
    App::setLocale('en');
    $this->actingAs(User::factory()->create(['number_format' => $format]));
    expect(LocalizedNumberFormatter::decimal(1234.56, 2))->toBe($expected)
        ->and(MoneyFormatter::formatCents(123456, 'EUR'))->toBe('€'.$expected)
        ->and(MoneyFormatter::formatSignedCents(-123456, 'EUR'))->toBe('-€'.$expected)
        ->and(MoneyFormatter::centsToDecimal(123456))->toBe('1234.56')
        ->and(MoneyFormatter::decimalToCents('1234.56'))->toBe(123456);
})->with([
    ['comma_dot', '1,234.56'], ['dot_comma', '1.234,56'],
    ['space_comma', '1 234,56'], ['space_dot', '1 234.56'],
]);

test('explicit locale defaults and unknown stored values do not inherit personal formats', function (): void {
    App::setLocale('en');
    $this->actingAs($user = User::factory()->create(['date_format' => 'd.m.Y', 'time_format' => '24h', 'number_format' => 'space_comma']));
    $date = CarbonImmutable::parse('2026-08-24 15:00');
    expect(LocalizedDateFormatter::dateTime($date, DisplayPreferences::defaults()))->toBe('08/24/2026 3:00 PM')
        ->and(MoneyFormatter::formatCents(123456, 'EUR', DisplayPreferences::defaults()))->toBe('€1,234.56');
    $user->date_format = 'invalid';
    $user->time_format = 'invalid';
    $user->number_format = 'invalid';
    expect(LocalizedDateFormatter::dateTime($date))->toBe('08/24/2026 3:00 PM')
        ->and(MoneyFormatter::formatCents(123456, 'EUR'))->toBe('€1,234.56');
});

test('shared staff cache keys distinguish personal formats and return to the original defaults', function (): void {
    $this->actingAs($user = User::factory()->create());
    $ids = collect([3, 1]);
    $access = ['reports' => $ids];
    $analytics = BuildBasicAnalyticsDashboardAction::cacheKeyForBranchIds($ids);
    $dashboard = BuildRestaurantDashboardAction::cacheKeyForAccess($access);
    $user->number_format = 'dot_comma';
    expect(BuildBasicAnalyticsDashboardAction::cacheKeyForBranchIds($ids))->not->toBe($analytics)
        ->and(BuildRestaurantDashboardAction::cacheKeyForAccess($access))->not->toBe($dashboard);
    $user->number_format = null;
    expect(BuildBasicAnalyticsDashboardAction::cacheKeyForBranchIds($ids))->toBe($analytics)
        ->and(BuildRestaurantDashboardAction::cacheKeyForAccess($access))->toBe($dashboard);
});

test('analytics cache retains the originating formats during deferred refresh', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-24 15:00:00', 'UTC'));
    config()->set('cache.stores.database.lock_lottery', [0, 1]);
    $branch = Branch::factory()->create(['timezone' => 'UTC']);
    $session = TableSession::factory()->recycle($branch)->active()->create();
    Order::factory()->forTableSession($session)->create(['total_price_cents' => 123456]);
    $this->mock(ResolveWaiterAccessibleBranchIdsAction::class)
        ->shouldReceive('handle')->andReturn(collect([$branch->id]));
    $first = User::factory()->create(['date_format' => 'd.m.Y', 'time_format' => '24h', 'number_format' => 'space_comma']);
    $second = User::factory()->create(['date_format' => 'Y-m-d', 'time_format' => '12h', 'number_format' => 'comma_dot']);
    $action = app(BuildBasicAnalyticsDashboardAction::class);
    $this->actingAs($second);
    $initial = $action->handle($first)['analytics'];
    $other = $action->handle($second)['analytics'];
    expect($initial['orders_today_total'])->toBe('€1 234,56')
        ->and($initial['cached_at'])->toBe('24.08.2026 15:00')
        ->and($initial['period_label'])->toBe('24.08.2026')
        ->and($other['orders_today_total'])->toBe('€1,234.56')
        ->and($other['cache_key'])->not->toBe($initial['cache_key']);

    $this->travel(240)->seconds();
    $action->handle($first);
    expect(app(DeferredCallbackCollection::class))->toHaveCount(1);
    app(InvokeDeferredCallbacks::class)
        ->terminate(Request::create('/'), response('ok'));
    $refreshed = Cache::store('database')->get($initial['cache_key']);
    expect($refreshed['cached_at'])->toBe('24.08.2026 15:04')
        ->and($refreshed['orders_today_total'])->toBe('€1 234,56')
        ->and($refreshed['currency_totals'][0]['total'])->toBe('€1 234,56');
});

test('guest menu cache and prices follow guest language when an administrator visits', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-24 15:00:00', 'UTC'));
    App::setLocale('en');
    $this->actingAs(User::factory()->create(['time_format' => '24h', 'number_format' => 'space_comma']));
    $branch = Branch::factory()->create(['timezone' => 'UTC']);
    $menu = Menu::factory()->for($branch)->active()->create();
    MenuAvailabilitySchedule::factory()->for($menu)->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '22:00']);
    $category = MenuCategory::factory()->for($menu)->create();
    MenuItem::factory()->for($menu)->for($category, 'category')->create(['price_cents' => 123456]);
    $action = app(GetGuestMenuForBranchAction::class);
    $payload = $action->handle($branch->id, 'en');
    expect($payload['availability']['detail'])->toContain('10:00 PM')->not->toContain('22:00');
    Livewire::test(GuestMenu::class, ['branchId' => $branch->id, 'currency' => 'EUR', 'guestCanAddItems' => false])
        ->assertSee('€1,234.56')->assertDontSee('€1 234,56');
    Auth::logout();
    expect($action->handle($branch->id, 'en'))->toBe($payload);
});

test('report period display preferences leave date ranges and fingerprints canonical', function (): void {
    $branch = Branch::factory()->make(['id' => 1, 'timezone' => 'Europe/Vilnius']);
    $period = BranchReportPeriod::fromSelection(collect([$branch]), 'custom', '2026-08-24', '2026-08-25');
    $fingerprint = $period->fingerprint();
    expect($period->label(DisplayPreferences::from(['date_format' => 'd.m.Y'])))->toBe('24.08.2026 – 25.08.2026')
        ->and($period->ranges[1]['date_from'])->toBe('2026-08-24')
        ->and($period->fingerprint())->toBe($fingerprint);
});

test('format migration is reversible without changing existing users', function (): void {
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    $user = User::factory()->create();
    $migration = require database_path('migrations/2026_09_20_154344_add_display_formats_to_users_table.php');
    $migration->down();
    expect(Schema::hasColumn('users', 'date_format'))->toBeFalse()
        ->and(User::query()->whereKey($user->id)->value('email'))->toBe($user->email);
    $migration->up();
    expect($user->fresh()->date_format)->toBeNull()
        ->and(Schema::hasColumns('users', ['date_format', 'time_format', 'number_format']))->toBeTrue();
});

test('catalogue labels use personal formats while keeping editor values unchanged', function (): void {
    $this->actingAs($user = User::factory()->create(['date_format' => 'd.m.Y', 'time_format' => '24h', 'number_format' => 'space_comma']));
    $this->travelTo(CarbonImmutable::parse('2026-08-24 12:00:00', 'UTC'));
    $branch = Branch::factory()->create(['timezone' => 'Europe/Vilnius']);
    $menu = Menu::factory()->for($branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create([
        'hidden_until' => '2026-08-24 13:00:00', 'weight' => '1234.50', 'volume' => '250.00', 'calories' => 1234,
    ]);
    $row = app(CatalogData::class)->editingItem($branch, $item->id);
    expect($row['hidden_until'])->toBe('2026-08-24T16:00')
        ->and($row['hidden_until_label'])->toBe('24.08.2026 16:00')
        ->and($row['weight'])->toBe('1234.50')
        ->and($row['weight_label'])->toBe('1 234,50')
        ->and($row['calories_label'])->toBe('1 234');
    $preview = app(DishPreviewQuery::class)->for($user, $branch, $item->id, 'en', null, []);
    expect($preview['evaluated_at'])->toBe('24.08.2026 15:00')
        ->and($item->fresh()->weight)->toBe('1234.50');
});

test('a rejected preference save preserves persistence and the authenticated user state', function (): void {
    $this->actingAs($user = User::factory()->create());
    User::saving(fn (User $model): bool => ! $model->isDirty('date_format'));
    try {
        app(UpdateUserDisplayFormatsAction::class)->handle($user, DisplayPreferences::from(['date_format' => 'd.m.Y']));
        $this->fail('A vetoed save must not report success.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('The display preferences could not be saved.');
    }
    expect($user->date_format)->toBeNull()->and($user->fresh()->date_format)->toBeNull();
});

test('saving interface language refreshes the format section without resetting its draft', function (): void {
    $this->actingAs(User::factory()->create(['locale' => 'en']));
    Livewire::test(Profile::class)
        ->set('locale', 'lt')->call('updateProfileInformation')
        ->assertDispatchedTo(DisplayFormats::class, 'profile-locale-updated');

    $formats = Livewire::test(DisplayFormats::class)->set('form.date_format', 'd.m.Y');
    Auth::user()->forceFill(['locale' => 'ru'])->save();
    App::setLocale('ru');
    $formats->dispatch('profile-locale-updated')
        ->assertSet('form.date_format', 'd.m.Y')
        ->assertSee('Формат даты');
});
