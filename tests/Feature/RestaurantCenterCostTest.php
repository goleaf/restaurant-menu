<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Restaurants\Index;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\Organizations\RestaurantCenterQuery;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('restaurant list query cost remains bounded when the accessible restaurant count grows', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $actor = User::factory()->create(['name' => 'Cost Owner', 'email' => 'center-cost@example.test']);
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Cost Organization']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Cost Brand']);
    $factory = Branch::factory()->for($organization)->for($brand)->sequence(fn ($sequence) => [
        'name' => 'Cost Restaurant '.str_pad((string) $sequence->index, 3, '0', STR_PAD_LEFT),
        'address' => 'Example street', 'city' => 'Vilnius', 'country' => 'Lithuania', 'timezone' => 'UTC', 'currency' => 'EUR',
    ]);
    $factory->count(25)->create();
    $query = app(RestaurantCenterQuery::class);
    $counts = [];
    DB::enableQueryLog();
    try {
        foreach ([25, 100] as $size) {
            if ($size === 100) {
                $factory->count(75)->create();
            }
            DB::flushQueryLog();
            $page = $query->restaurants($actor->fresh(), []);
            $rows = array_map(fn ($branch): array => $query->row($branch, 'branch', []), $page->items());
            $counts[] = count(DB::getQueryLog());
            expect($rows)->toHaveCount(20)->and($page->hasMorePages())->toBeTrue();
        }
        expect($counts[1])->toBe($counts[0])->toBeLessThanOrEqual(12);

        if (getenv('RESTAURANT_MEASURE') === '1') {
            $this->withVite();
            $this->actingAs($actor);
            config()->set('app.debug', false);
            $samples = [];
            foreach (range(0, 5) as $iteration) {
                DB::flushQueryLog();
                memory_reset_peak_usage();
                $start = hrtime(true);
                $response = $this->get(route('restaurants.index'))->assertOk();
                $samples[] = ['operation' => 'http', 'iteration' => $iteration, 'ms' => (hrtime(true) - $start) / 1e6,
                    'queries' => count(DB::getQueryLog()), 'bytes' => strlen($response->getContent()), 'peak_bytes' => memory_get_peak_usage(true)];
            }
            $component = Livewire::actingAs($actor)->test(Index::class);
            foreach (range(0, 5) as $iteration) {
                DB::flushQueryLog();
                memory_reset_peak_usage();
                $start = hrtime(true);
                $component->set('filters.search', $iteration % 2 === 0 ? 'Cost Restaurant' : 'Cost')->assertHasNoErrors();
                $samples[] = ['operation' => 'livewire', 'iteration' => $iteration, 'ms' => (hrtime(true) - $start) / 1e6,
                    'queries' => count(DB::getQueryLog()), 'bytes' => strlen(json_encode(['snapshot' => json_encode($component->snapshot, JSON_THROW_ON_ERROR), 'effects' => $component->effects], JSON_THROW_ON_ERROR)), 'peak_bytes' => memory_get_peak_usage(true)];
            }
            fwrite(STDOUT, "\nCENTER_MEASURE ".json_encode(['php' => PHP_VERSION, 'opcache_cli' => ini_get('opcache.enable_cli'), 'coverage' => extension_loaded('xdebug'), 'query_counts' => $counts, 'samples' => $samples], JSON_THROW_ON_ERROR)."\n");
        }
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});
