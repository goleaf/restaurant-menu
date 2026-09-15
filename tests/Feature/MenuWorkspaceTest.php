<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\Menu\Catalog;
use App\Livewire\Organizations\Brands\Branches\Menu\Index;
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
use Illuminate\Support\Facades\ParallelTesting;
use Livewire\Livewire;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-workspace-'.getmypid());
    $this->seed(SystemPermissionsSeeder::class);
});

test('menu workspace first response has a bounded component and query budget', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = menuWorkspaceContext();
    MenuItem::factory()->count(30)->for($menu)->for($category, 'category')->sequence(fn ($sequence): array => [
        'name' => 'Workspace dish '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
        'description' => 'A prepared dish with seasonal vegetables.',
        'price_cents' => 1250,
    ])->create();
    $this->actingAs($owner);
    $url = route('organizations.brands.branches.menu.index', [$organization, $brand, $branch]);

    foreach (['cold', 'warm'] as $cacheState) {
        $startedAt = hrtime(true);
        $response = null;
        $queries = countDatabaseQueries(function () use ($url, &$response): void {
            $response = $this->get($url)->assertOk();
        });
        $html = $response->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $snapshots);
        $snapshotBytes = array_sum(array_map(fn (string $snapshot): int => strlen(html_entity_decode($snapshot, ENT_QUOTES | ENT_HTML5)), $snapshots[1]));

        if (getenv('MENU_WORKSPACE_METRICS') === '1') {
            fwrite(STDERR, json_encode([
                'cache' => $cacheState,
                'queries' => $queries,
                'response_bytes' => strlen($html),
                'snapshots' => count($snapshots[1]),
                'snapshot_bytes' => $snapshotBytes,
                'elapsed_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
                'peak_memory_bytes' => memory_get_peak_usage(true),
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }

        expect(count($snapshots[1]))->toBeLessThanOrEqual(5)
            ->and($queries)->toBeLessThanOrEqual(220)
            ->and(strlen($html))->toBeLessThan(1_250_000)
            ->and($snapshotBytes)->toBeLessThan(6000);
    }
});

test('menu workspace mounts only the selected allowlisted section', function (string $section, string $component): void {
    [$owner, $organization, $brand, $branch] = menuWorkspaceContext();
    $response = Livewire::actingAs($owner)->withQueryParams(['section' => $section])->test(Index::class, compact('organization', 'brand', 'branch'))
        ->assertSet('section', $section)
        ->assertSeeLivewire('organizations.brands.branches.menu.'.$component);

    foreach (['catalog', 'availability', 'variants', 'kitchen-departments', 'modifiers'] as $other) {
        if ($other !== $component) {
            $response->assertDontSeeLivewire('organizations.brands.branches.menu.'.$other);
        }
    }
})->with([
    ['catalog', 'catalog'],
    ['availability', 'availability'],
    ['variants', 'variants'],
    ['departments', 'kitchen-departments'],
    ['modifiers', 'modifiers'],
]);

test('menu workspace rejects an arbitrary component and normalizes a hostile URL', function (mixed $section): void {
    [$owner, $organization, $brand, $branch] = menuWorkspaceContext();
    Livewire::actingAs($owner)->withQueryParams(['section' => $section])->test(Index::class, compact('organization', 'brand', 'branch'))
        ->assertSet('section', 'catalog')
        ->call('selectSection', $section)->assertHasErrors('section')
        ->assertSet('section', 'catalog');
})->with(['../superadmin/users', 'App\\Livewire\\Superadmin\\Users', [['catalog']], true, 1, null]);

test('availability staff cannot select a management section even by changing public state', function (): void {
    [, $organization, $brand, $branch] = menuWorkspaceContext();
    $chef = User::factory()->create();
    OrganizationUser::factory()->for($organization)->for($chef, 'user')->create([
        'role_id' => Role::query()->where('code', SystemRole::HeadChef)->sole()->id,
    ]);

    Livewire::actingAs($chef)->withQueryParams(['section' => 'catalog'])->test(Index::class, compact('organization', 'brand', 'branch'))
        ->assertSet('section', 'availability')
        ->assertDontSeeLivewire('organizations.brands.branches.menu.catalog')
        ->call('selectSection', 'catalog')->assertHasErrors('section')
        ->set('section', 'modifiers')->assertSet('section', 'availability');
});

test('menu workspace rechecks revoked membership before changing sections', function (): void {
    [, $organization, $brand, $branch] = menuWorkspaceContext();
    $manager = User::factory()->create();
    $membership = OrganizationUser::factory()->for($organization)->for($manager, 'user')->create([
        'role_id' => Role::query()->where('code', SystemRole::Director)->sole()->id,
    ]);
    $component = Livewire::actingAs($manager)->test(Index::class, compact('organization', 'brand', 'branch'));
    $membership->forceFill(['status' => OrganizationUserStatus::Suspended])->save();
    $this->actingAs($manager->fresh());

    $component->call('selectSection', 'variants')->assertForbidden();
});

test('menu workspace denies a foreign branch before rendering any child', function (): void {
    [$owner, $organization, $brand] = menuWorkspaceContext();
    [, , , $branch] = menuWorkspaceContext();
    Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand', 'branch'))->assertForbidden();
});

test('menu workspace switches one child at a time and publishes section history effects', function (): void {
    [$owner, $organization, $brand, $branch] = menuWorkspaceContext();
    $component = Livewire::actingAs($owner)->test(Index::class, compact('organization', 'brand', 'branch'));

    expect($component->effects['url']['section']['as'])->toBe('section')
        ->and($component->effects['url']['section']['use'])->toBe('push');

    $component->call('selectSection', 'variants')->assertSet('section', 'variants')
        ->assertSeeLivewire('organizations.brands.branches.menu.variants')
        ->assertDontSeeLivewire('organizations.brands.branches.menu.catalog')
        ->call('selectSection', 'catalog')->assertSet('section', 'catalog')
        ->assertSeeLivewire('organizations.brands.branches.menu.catalog')
        ->assertDontSeeLivewire('organizations.brands.branches.menu.variants');
});

test('catalogue restores its bounded filter state from URL without including a foreign menu', function (): void {
    [$owner, $organization, $brand, $branch, $menu, $category] = menuWorkspaceContext();
    MenuItem::factory()->count(26)->for($menu)->for($category, 'category')->sequence(fn ($sequence): array => [
        'name' => 'Warm dish '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT),
        'is_available' => false,
    ])->create();
    [, , , , $foreignMenu, $foreignCategory] = menuWorkspaceContext();
    MenuItem::factory()->for($foreignMenu)->for($foreignCategory, 'category')->create(['name' => 'Foreign warm dish']);
    $parameters = ['organizationId' => $organization->id, 'brandId' => $brand->id, 'branchId' => $branch->id];

    Livewire::actingAs($owner)->withQueryParams(['q' => 'Warm', 'menu' => (string) $menu->id, 'availability' => 'unavailable', 'page' => 2])
        ->test(Catalog::class, $parameters)
        ->assertSet('filters.search', 'Warm')->assertSet('filters.menuId', $menu->id)
        ->assertSet('filters.availability', 'unavailable')->assertSet('filters.page', 2)
        ->assertSee('Warm dish 24')->assertDontSee('Warm dish 00')->assertDontSee('Foreign warm dish');

    Livewire::actingAs($owner)->withQueryParams(['menu' => (string) $foreignMenu->id])->test(Catalog::class, $parameters)
        ->assertDontSee('Foreign warm dish');
});

/** @return array{User, Organization, Brand, Branch, Menu, MenuCategory} */
function menuWorkspaceContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Workspace restaurant']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Workspace brand']);
    $branch = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Workspace branch']);
    $menu = Menu::factory()->for($branch)->create(['name' => 'Dinner', 'status' => MenuStatus::Active]);
    $category = MenuCategory::factory()->for($menu)->create(['name' => 'Mains']);

    return [$owner->fresh(), $organization, $brand, $branch, $menu, $category];
}
