<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Availability\Index;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Arr;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('availability workspace gives narrow availability staff only their permitted section', function (): void {
    [$owner, $organization, $brand, $branch] = availabilityWorkspaceContext();
    $chef = User::factory()->create();
    OrganizationUser::factory()->for($organization)->for($chef, 'user')->create([
        'role_id' => Role::query()->where('code', SystemRole::HeadChef)->sole()->id,
    ]);

    Livewire::actingAs($chef)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->assertSet('section', 'stoplist')
        ->assertSee('data-availability-section="stoplist"', false)
        ->assertDontSee('data-availability-section="schedules"', false)
        ->call('selectSection', 'schedules')->assertForbidden();
});

test('availability workspace scopes its immutable branch before reading dishes', function (): void {
    [$owner, $organization, $brand] = availabilityWorkspaceContext();
    [, , , $branch] = availabilityWorkspaceContext();

    Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand', 'branch'))->assertForbidden();
});

test('stop list paginates twenty scoped items and preserves search in the URL', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    MenuItem::factory()->count(21)->for($menu)->for($category, 'category')->sequence(fn ($sequence): array => [
        'name' => 'Dish '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
    ])->create();
    [, , , , $foreignMenu, $foreignCategory] = availabilityWorkspaceContext();
    MenuItem::factory()->for($foreignMenu)->for($foreignCategory, 'category')->create(['name' => 'Dish foreign']);

    Livewire::actingAs($owner)->withQueryParams(['section' => 'stoplist', 'q' => 'Dish', 'page' => 2])
        ->test(Index::class, compact('organization', 'brand', 'branch'))
        ->assertSet('filters.search', 'Dish')->assertSet('filters.page', 2)
        ->assertSee('Dish 20')->assertDontSee('Dish 00')->assertDontSee('Dish foreign');
});

test('stop list previews one restriction without writing then applies its exact version', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
    $component = Livewire::actingAs($owner)->withQueryParams(['section' => 'stoplist'])
        ->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('openItem', $item->id)->set('restriction.operation', 'stop')
        ->call('previewRestriction')->assertHasNoErrors();
    expect($item->fresh()->is_available)->toBeTrue();
    $component->call('applyRestriction')->assertHasNoErrors();
    expect($item->fresh()->is_available)->toBeFalse();
});

test('stop list refuses a bulk target absent from the rendered current page', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $items = MenuItem::factory()->count(21)->for($menu)->for($category, 'category')->sequence(fn ($sequence): array => [
        'name' => 'Dish '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
    ])->create();

    Livewire::actingAs($owner)->withQueryParams(['section' => 'stoplist'])
        ->test(Index::class, compact('organization', 'brand', 'branch'))
        ->set('selectedItems', [$items->last()->id])->call('openBulk')->assertHasErrors('selectedItems.0');
    expect($items->last()->fresh()->is_available)->toBeTrue();
});

test('restaurant pause requires preview and preserves the exact target version', function (): void {
    [$owner, $organization, $brand, $branch] = availabilityWorkspaceContext();
    $component = Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('openPause')->set('pause.mode', 'indefinite')->set('pause.reason', 'Planned maintenance')
        ->call('applyPause')->assertHasErrors('pause.mode');
    expect($branch->fresh()->is_temporarily_closed)->toBeFalse();
    $component->call('previewPause')->assertHasNoErrors()->call('applyPause')->assertHasNoErrors();
    expect($branch->fresh()->is_temporarily_closed)->toBeTrue();
});

test('weekly menu draft explicitly distinguishes unrestricted from closed', function (): void {
    [$owner, $organization, $brand, $branch, $menu] = availabilityWorkspaceContext();
    $component = Livewire::actingAs($owner)->withQueryParams(['section' => 'schedules', 'menu' => $menu->id])
        ->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('openMenuSchedule')->set('weekly.mode', 'closed')->call('previewSchedule')->assertHasNoErrors();
    expect($menu->fresh()->schedule_is_closed)->toBeFalse();
    $component->call('applySchedule')->assertHasNoErrors();
    expect($menu->fresh()->schedule_is_closed)->toBeTrue();
    $component->call('openMenuSchedule')->set('weekly.mode', 'unrestricted')->call('previewSchedule')->call('applySchedule')->assertHasNoErrors();
    expect($menu->fresh()->schedule_is_closed)->toBeFalse();
});

test('future evaluation reads saved rules without changing a restriction', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => false]);
    $future = now('Europe/Vilnius')->addDays(2);
    Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->set('evaluation.mode', 'future')->set('evaluation.date', $future->format('Y-m-d'))->set('evaluation.time', '12:00')
        ->call('evaluate')->assertHasNoErrors();
    expect($item->fresh()->is_available)->toBeFalse()->and($branch->fresh()->pause_version)->toBe(0);
});

test('copying weekday intervals previews replacement and changes only the unsaved schedule', function (): void {
    [$owner, $organization, $brand, $branch] = availabilityWorkspaceContext();
    $component = Livewire::actingAs($owner)->withQueryParams(['section' => 'schedules'])
        ->test(Index::class, compact('organization', 'brand', 'branch'))->call('openHours')
        ->set('weekly.copyFrom', 0)->set('weekly.copyTo', [1, 2])
        ->set('weekly.openingHours.0.intervals', [['opens_at' => '08:00', 'closes_at' => '12:00']])
        ->call('previewDayCopy')->assertHasNoErrors()
        ->assertSet('weekly.openingHours.1.intervals.0.opens_at', '10:00')
        ->call('copyDays')->assertSet('weekly.openingHours.1.intervals.0.opens_at', '08:00');
    expect($branch->openingHours()->exists())->toBeFalse();
    $component->call('discardDraft')->assertSet('editor', '');
});

test('a preview cannot be applied after another account replaces the authenticated actor', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $component = Livewire::actingAs($owner)->withQueryParams(['section' => 'stoplist'])
        ->test(Index::class, compact('organization', 'brand', 'branch'))->call('openItem', $item->id)->call('previewRestriction');
    $this->actingAs(User::factory()->create());
    $component->call('applyRestriction')->assertStatus(409);
    expect($item->fresh()->is_available)->toBeTrue();
});

test('restriction preview detects changed restaurant dependencies before applying', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    $component = Livewire::actingAs($owner)->withQueryParams(['section' => 'stoplist'])
        ->test(Index::class, compact('organization', 'brand', 'branch'))->call('openItem', $item->id)->call('previewRestriction');
    $branch->forceFill(['is_temporarily_closed' => true, 'temporary_closed_reason' => 'Changed after preview', 'pause_version' => 1])->save();
    $component->call('applyRestriction')->assertHasErrors('restriction.operation');
    expect($item->fresh()->is_available)->toBeTrue();
});

test('availability list remains bounded as the saved catalogue grows', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    MenuItem::factory()->count(20)->for($menu)->for($category, 'category')->create();
    $this->actingAs($owner);
    $url = route('organizations.brands.branches.availability.index', [$organization, $brand, $branch, 'section' => 'stoplist']);
    $response = null;
    $before = countDatabaseQueries(function () use ($url, &$response): void {
        $response = $this->get($url)->assertOk();
    });
    MenuItem::factory()->count(60)->for($menu)->for($category, 'category')->create();
    $startedAt = hrtime(true);
    $after = countDatabaseQueries(function () use ($url, &$response): void {
        $response = $this->get($url)->assertOk();
    });
    $html = $response->getContent();
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $snapshots);
    $payloadBytes = array_sum(array_map(fn (string $snapshot): int => strlen(html_entity_decode($snapshot, ENT_QUOTES | ENT_HTML5)), $snapshots[1]));
    expect($after)->toBeLessThanOrEqual($before + 2)->and(substr_count($html, 'wire:key="availability-item-'))->toBe(20)
        ->and($payloadBytes)->toBeLessThan(20000);
    if (getenv('AVAILABILITY_METRICS') === '1') {
        fwrite(STDERR, json_encode(['twenty_item_queries' => $before, 'eighty_item_queries' => $after, 'html_bytes' => strlen($html), 'snapshot_bytes' => $payloadBytes,
            'peak_memory_bytes' => memory_get_peak_usage(true), 'elapsed_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2)], JSON_THROW_ON_ERROR).PHP_EOL);
    }
});

test('invalid stop list filters remain visible errors without clearing an open draft', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
    Livewire::actingAs($owner)->withQueryParams(['section' => 'stoplist'])
        ->test(Index::class, compact('organization', 'brand', 'branch'))->call('openItem', $item->id)
        ->set('restriction.reason', 'Keep this draft')->set('filters.search', ['bad'])
        ->assertHasErrors('filters.search')->assertSet('restriction.reason', 'Keep this draft')
        ->set('filters.search', '')->assertSet('restriction.reason', 'Keep this draft');
});

test('retrying the same pause apply after a lost response neither extends duration nor rejects its receipt', function (): void {
    [$owner, $organization, $brand, $branch] = availabilityWorkspaceContext();
    $component = Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('openPause')->set('pause.mode', 'duration')->set('pause.durationMinutes', 30)->set('pause.reason', 'Kitchen maintenance')
        ->call('previewPause')->assertHasNoErrors();
    $retry = clone $component;
    $component->call('applyPause')->assertHasNoErrors();
    $until = $branch->fresh()->getRawOriginal('temporary_closed_until');
    $this->travel(2)->minutes();
    $retry->call('applyPause')->assertHasNoErrors();
    expect($branch->fresh()->pause_version)->toBe(1)->and($branch->fresh()->getRawOriginal('temporary_closed_until'))->toBe($until);
});

test('availability form errors preserve translated fields parameters and the current draft', function (string $locale): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $component = Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand', 'branch'));
    foreach ([
        ['reason', '', 'required', 'availability.public_reason', []],
        ['reason', ['invalid'], 'string', 'availability.public_reason', []],
        ['reason', str_repeat('x', 256), 'max.string', 'availability.public_reason', ['max' => 255]],
        ['mode', 'unknown', 'in', 'availability.pause_mode', []],
        ['durationMinutes', 0, 'min.numeric', 'availability.duration_minutes', ['min' => 1]],
        ['untilTime', '25:90', 'date_format', 'availability.until_time', ['format' => 'H:i']],
    ] as [$field, $value, $rule, $attribute, $parameters]) {
        $component->call('discardDraft')->call('openPause')->set('pause.reason', 'Retained public explanation')
            ->set('pause.'.$field, $value)->call('previewPause')
            ->assertHasErrors('pause.'.$field)->assertSet('editor', 'pause')->assertSet('pause.'.$field, $value);
        $message = __('validation.'.$rule, ['attribute' => __($attribute, [], $locale), ...$parameters], $locale);
        expect($component->errors()->first('pause.'.$field))->toBe($message)
            ->and($message)->not->toContain('availability.', 'validation.', ':attribute', ':max', ':min', ':format');
        if ($locale !== 'en') {
            expect($message)->not->toBe(__('validation.'.$rule, ['attribute' => __($attribute, [], 'en'), ...$parameters], 'en'));
        }
    }
    expect($branch->fresh()->pause_version)->toBe(0);
})->with(['en', 'lt', 'ru']);

test('malformed weekly rows return localized field errors instead of throwing or silently filling gaps', function (string $locale): void {
    [$owner, $organization, $brand, $branch] = availabilityWorkspaceContext();
    $owner->update(['locale' => $locale]);
    app()->setLocale($locale);
    $component = Livewire::actingAs($owner)->withQueryParams(['section' => 'schedules'])
        ->test(Index::class, compact('organization', 'brand', 'branch'));
    foreach ([
        ['is_closed', 'required', 'availability.closed_day'],
        ['intervals', 'present', 'availability.intervals'],
        ['intervals.0.opens_at', 'present', 'availability.opens'],
        ['intervals.0.closes_at', 'present', 'availability.closes'],
    ] as [$missing, $rule, $attribute]) {
        $component->call('discardDraft')->call('openHours')->set('weekly.mode', 'weekly');
        $days = $component->get('weekly.openingHours');
        Arr::forget($days, '0.'.$missing);
        $component->set('weekly.openingHours', $days)->call('previewSchedule')
            ->assertHasErrors('weekly.openingHours.0.'.$missing)->assertSet('weekly.openingHours', $days)->assertSet('editor', 'hours');
        expect($component->errors()->first('weekly.openingHours.0.'.$missing))
            ->toBe(__('validation.'.$rule, ['attribute' => __($attribute, [], $locale)], $locale));
    }
    expect($branch->fresh()->opening_hours_version)->toBe(0)->and($branch->openingHours()->exists())->toBeFalse();
})->with(['en', 'lt', 'ru']);

test('bulk restriction preview loads its bounded target graph once for the current page', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = availabilityWorkspaceContext();
    $items = MenuItem::factory()->count(20)->for($menu)->for($category, 'category')->create();
    $component = Livewire::actingAs($owner)->withQueryParams(['section' => 'stoplist'])
        ->test(Index::class, compact('organization', 'brand', 'branch'))->call('openItem', $items->first()->id);
    $singleQueries = countDatabaseQueries(fn () => $component->call('previewRestriction')->assertHasNoErrors());
    $component->call('discardDraft')->set('selectedItems', $items->modelKeys())->call('openBulk');
    $bulkQueries = countDatabaseQueries(fn () => $component->call('previewRestriction')->assertHasNoErrors());
    expect($bulkQueries)->toBeLessThanOrEqual($singleQueries + 4)
        ->and($component->get('preview.rows'))->toHaveCount(20);
});

/** @return array{User, Organization, Brand, Branch, Menu, MenuCategory} */
function availabilityWorkspaceContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Availability organization']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create(['timezone' => 'Europe/Vilnius']);
    $menu = Menu::factory()->for($branch)->create(['name' => 'Dinner', 'status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Mains']);

    return [$owner->fresh(), $organization, $brand, $branch, $menu, $category];
}
