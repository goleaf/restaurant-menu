<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Forms\Floor\FloorFilterForm;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\Branches\AreaNodeQueryService;
use App\Services\Branches\FloorWorkspaceQuery;
use App\Services\Branches\ServicePointQueryService;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * @param  Closure(): TestResponse|Testable  $operation
 * @return array{0:TestResponse|Testable,1:array<string,mixed>}
 */
function measureFloorWorkspaceScreen(Closure $operation): array
{
    $models = [];
    $measuring = false;
    Event::listen('eloquent.retrieved: *', function (string $event, array $payload) use (&$models, &$measuring): void {
        if ($measuring && ($payload[0] ?? null) instanceof Model) {
            $class = $payload[0]::class;
            $models[$class] = ($models[$class] ?? 0) + 1;
        }
    });
    gc_collect_cycles();
    memory_reset_peak_usage();
    $memory = memory_get_usage();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $measuring = true;
    $started = hrtime(true);
    try {
        $screen = $operation();
        $elapsed = (hrtime(true) - $started) / 1_000_000;
        $queries = count(DB::getQueryLog());
        $peakDelta = memory_get_peak_usage() - $memory;
        $allocatedPeak = memory_get_peak_usage(true);
    } finally {
        $measuring = false;
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    $html = $screen instanceof Testable ? $screen->html() : $screen->getContent();
    $response = $screen instanceof Testable ? (new ReflectionProperty($screen, 'lastState'))->getValue($screen)->getResponse() : $screen;
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $embedded);
    $embeddedBytes = array_sum(array_map(fn (string $value): int => strlen(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')), $embedded[1]));
    ksort($models);

    return [$screen, [
        'queries' => $queries, 'models_retrieved' => array_sum($models), 'models_by_class' => $models,
        'memory_peak_delta_bytes' => $peakDelta, 'allocated_peak_bytes' => $allocatedPeak,
        'html_bytes' => strlen($html), 'embedded_snapshots_count' => count($embedded[1]), 'embedded_snapshots_bytes' => $embeddedBytes,
        'snapshot_bytes' => $screen instanceof Testable ? strlen(json_encode($screen->snapshot, JSON_THROW_ON_ERROR)) : null,
        'component_payload_bytes' => $screen instanceof Testable ? strlen(json_encode(['snapshot' => json_encode($screen->snapshot, JSON_THROW_ON_ERROR), 'effects' => $screen->effects], JSON_THROW_ON_ERROR)) : null,
        'response_body_bytes' => strlen($response->getContent()),
        'elapsed_ms' => round($elapsed, 3),
    ]];
}

test('the floor workspace keeps bounded rows and hydration while search and view share the same table identity', function (): void {
    $this->travelTo(now()->setDate(2026, 9, 18)->setTime(12, 0));
    fake()->seed(552001);
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create(['name' => 'Floor cost owner', 'email' => 'floor-cost@example.test']);
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Floor cost organization']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Floor cost brand']);
    $branch = Branch::factory()->for($organization)->for($brand)->create(['name' => 'Floor cost restaurant']);
    $areas = AreaNode::factory()->count(10)->for($branch)->sequence(fn ($sequence): array => [
        'name' => sprintf('Cost hall %02d', $sequence->index), 'type' => 'hall', 'sort_order' => $sequence->index,
    ])->create();
    $factory = ServicePoint::factory()->for($branch)->sequence(fn ($sequence): array => [
        'area_node_id' => $areas[$sequence->index % 10]->id, 'name' => sprintf('Cost table %04d', $sequence->index),
        'internal_code' => sprintf('COST-%04d', $sequence->index), 'display_number' => sprintf('%04d', $sequence->index),
        'type' => 'table', 'capacity' => 4, 'position_x' => 0, 'position_y' => 0,
    ])->afterCreating(fn (ServicePoint $point) => QrCode::factory()->forServicePoint($point)->active()->create([
        'public_token' => hash('sha256', 'floor-measurement-'.$point->internal_code),
        'short_code' => 'QR-'.$point->internal_code,
    ]));
    $points = $factory->count(40)->create();
    $filters = (new FloorFilterForm(new Index, 'filters'))->filters();
    $query = app(ServicePointQueryService::class);
    $page = null;
    $smallQueries = countDatabaseQueries(function () use ($query, $branch, $filters, &$page): void {
        $page = $query->paginate($branch, $filters, 20);
    });
    expect($page->count())->toBe(20)->and($page->hasMorePages())->toBeTrue();
    $points = $points->merge($factory->count(160)->create());
    $largeQueries = countDatabaseQueries(function () use ($query, $branch, $filters, &$page): void {
        $page = $query->paginate($branch, $filters, 20);
    });
    expect($largeQueries)->toBe($smallQueries)->and($page->count())->toBe(20)
        ->and($page->hasMorePages())->toBeTrue()->and($points)->toHaveCount(200);

    if (getenv('FLOOR_MEASURE') === '1') {
        $this->withVite();
    }
    config()->set('app.debug', false);
    $this->actingAs($actor);
    $parameters = compact('organization', 'brand', 'branch');
    $samples = [];
    [$response, $samples['http']] = measureFloorWorkspaceScreen(fn () => $this->get(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch])));
    $response->assertOk();
    expect(substr_count($response->getContent(), '<article class="rm-floor__point"'))->toBe(20);
    [$component, $samples['livewire_mount']] = measureFloorWorkspaceScreen(fn () => Livewire::actingAs($actor)->test(Index::class, $parameters));
    $component->assertOk()->assertHasNoErrors()->assertViewHas('rows', fn (array $rows): bool => count($rows) === 20);
    $target = $points->last();
    $component->call('selectPoint', $target->id)->assertHasNoErrors();
    [$component, $samples['search']] = measureFloorWorkspaceScreen(fn () => $component->set('filters.search', $target->name));
    $component->assertHasNoErrors()->assertSet('selectedIds', [$target->id])
        ->assertViewHas('rows', fn (array $rows): bool => array_column($rows, 'id') === [$target->id]);
    [$component, $samples['mode']] = measureFloorWorkspaceScreen(fn () => $component->set('filters.mode', 'list'));
    $component->assertHasNoErrors()->assertSet('selectedIds', [$target->id])
        ->assertViewHas('rows', fn (array $rows): bool => array_column($rows, 'id') === [$target->id]);
    foreach (['http' => 21, 'livewire_mount' => 21, 'search' => 1, 'mode' => 1] as $operation => $expectedPoints) {
        expect($samples[$operation]['models_by_class'][ServicePoint::class] ?? 0)->toBe($expectedPoints);
    }

    if (getenv('FLOOR_MEASURE') === '1') {
        $loaded = [];
        foreach ([Index::class, FloorFilterForm::class, AreaNodeQueryService::class, FloorWorkspaceQuery::class, ServicePointQueryService::class, ServicePoint::class, QrCode::class] as $class) {
            $path = (new ReflectionClass($class))->getFileName();
            expect(str_starts_with($path, base_path().DIRECTORY_SEPARATOR))->toBeTrue();
            $loaded[$class] = ['path' => $path, 'sha256' => hash_file('sha256', $path)];
        }
        fwrite(STDOUT, "\nFLOOR_MEASURE ".json_encode([
            'php' => PHP_VERSION, 'coverage' => extension_loaded('xdebug') || extension_loaded('pcov'), 'opcache_cli' => ini_get('opcache.enable_cli'),
            'fixture' => ['areas' => 10, 'tables' => 200, 'page_size' => 20], 'query_counts' => [$smallQueries, $largeQueries],
            'loaded' => $loaded, 'test_sha256' => hash_file('sha256', __FILE__), 'samples' => $samples,
        ], JSON_THROW_ON_ERROR)."\n");
    }
});
