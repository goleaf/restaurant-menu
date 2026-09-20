<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\KitchenDepartmentType;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

test('preparation HTTP and refresh payloads bound a large ticket without exposing another department', function (): void {
    fake()->seed(10);
    $this->travelTo(CarbonImmutable::parse('2026-09-20T12:00:00Z'));
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Preparation measurement']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $kitchen = KitchenDepartment::factory()->for($branch)->forType(KitchenDepartmentType::Kitchen)->create();
    $bar = KitchenDepartment::factory()->for($branch)->forType(KitchenDepartmentType::Bar)->create();
    $order = Order::factory()->recycle($branch)->sentToDepartments()->create();
    $kitchenTicket = KitchenTicket::factory()->forOrder($order)->create([
        'kitchen_department_id' => $kitchen->id, 'department_type' => 'kitchen', 'department_name' => 'Measurement kitchen',
    ]);
    $barTicket = KitchenTicket::factory()->forOrder($order)->create([
        'kitchen_department_id' => $bar->id, 'department_type' => 'bar', 'department_name' => 'Measurement bar',
    ]);
    $items = OrderItem::factory()->count(1200)->for($order)->create([
        'table_session_guest_id' => null, 'item_name' => 'Measurement kitchen item', 'quantity' => 2,
    ]);
    foreach ($items as $item) {
        KitchenTicketItem::factory()->forDispatchedOrderItem($kitchenTicket, $item)->pending()->create();
    }
    $barItem = OrderItem::factory()->for($order)->create(['table_session_guest_id' => null, 'item_name' => 'Hidden bar measurement item']);
    KitchenTicketItem::factory()->forDispatchedOrderItem($barTicket, $barItem)->pending()->create();
    unset($items);
    $cook = User::factory()->create();
    OrganizationUser::factory()->forOrganization($organization)->forUser($cook)
        ->forRole(Role::query()->where('code', SystemRole::Cook->value)->firstOrFail())->active()->create();
    $this->actingAs($cook);
    $baseline = getenv('PREPARATION_PROFILE_VARIANT') === 'baseline';
    $route = $baseline ? 'restaurant.kitchen.dashboard' : 'restaurant.preparation.dashboard';
    $component = $baseline ? 'kitchen.dashboard' : 'departments.dashboard';
    $hydrated = 0;
    $queries = 0;
    $collecting = false;
    Event::listen('eloquent.retrieved: *', function () use (&$hydrated, &$collecting): void {
        if ($collecting) {
            $hydrated++;
        }
    });
    DB::listen(function (QueryExecuted $event) use (&$queries, &$collecting): void {
        if ($collecting) {
            $queries++;
        }
    });
    $measure = function (Closure $operation) use (&$hydrated, &$queries, &$collecting): array {
        $hydrated = 0;
        $queries = 0;
        gc_collect_cycles();
        $memory = memory_get_usage();
        memory_reset_peak_usage();
        $start = hrtime(true);
        $collecting = true;
        try {
            $response = $operation();
        } finally {
            $collecting = false;
        }

        return [$response, [
            'sql_queries' => $queries, 'hydrated_models' => $hydrated,
            'peak_memory_delta_bytes' => memory_get_peak_usage() - $memory,
            'elapsed_ms' => round((hrtime(true) - $start) / 1e6, 2),
        ]];
    };
    [$page, $getMetrics] = $measure(fn () => $this->get(route($route, [
        'branch' => $branch->id, 'department' => $kitchen->id, 'filter' => 'new',
    ])));
    $page->assertOk()->assertDontSee('Hidden bar measurement item');
    $html = $page->getContent();
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = collect($matches[1])->map(fn (string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_HTML5))
        ->first(fn (string $value): bool => json_decode($value, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component);
    expect($snapshot)->toBeString();
    $snapshotData = preparationProfileState(json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR)['data']);
    $expectedRows = $baseline ? 1200 : 100;
    expect($snapshotData['tickets'][0]['items'])->toHaveCount($expectedRows);
    if (! $baseline) {
        expect($snapshotData['tickets'][0]['full_item_count'])->toBe(1200)
            ->and($snapshotData['tickets'][0]['has_next_item_page'])->toBeTrue()
            ->and($snapshotData['portionCount'])->toBe(2400);
    }
    $getMetrics += ['html_bytes' => strlen($html), 'component_snapshot_bytes' => strlen($snapshot), 'shown_rows' => $expectedRows];
    unset($page, $html, $snapshotData);
    [$refresh, $refreshMetrics] = $measure(fn () => $this->postJson(route('default-livewire.update'), ['components' => [[
        'snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => 'refreshDepartment', 'params' => [], 'path' => '']],
    ]]], ['X-Livewire' => '']));
    $refresh->assertOk();
    $refreshSnapshot = $refresh->json('components.0.snapshot');
    $refreshState = preparationProfileState(json_decode($refreshSnapshot, true, flags: JSON_THROW_ON_ERROR)['data']);
    expect($refreshState['tickets'][0]['items'])->toHaveCount($expectedRows);
    $refreshMetrics += [
        'response_bytes' => strlen($refresh->getContent()),
        'component_snapshot_bytes' => strlen($refreshSnapshot),
        'effects_bytes' => strlen(json_encode($refresh->json('components.0.effects'), JSON_THROW_ON_ERROR)),
        'shown_rows' => $expectedRows,
    ];
    $output = getenv('PREPARATION_PROFILE_OUTPUT');
    if (is_string($output) && $output !== '') {
        file_put_contents($output, json_encode([
            'variant' => $baseline ? 'baseline' : 'candidate', 'php' => PHP_VERSION,
            'fixture' => ['kitchen_rows' => 1200, 'bar_rows' => 1, 'kitchen_quantity_per_row' => 2, 'filter' => 'new', 'actor' => 'cook'],
            'requests' => 2, 'request_scope' => 'Laravel GET plus one Livewire refresh POST; excludes browser assets and networking',
            'get' => $getMetrics, 'refresh' => $refreshMetrics,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
    }
});

function preparationProfileState(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    if (count($value) === 2 && isset($value[1]['s']) && array_key_exists(0, $value)) {
        $value = $value[0];
    }

    return is_array($value) ? array_map(preparationProfileState(...), $value) : $value;
}
