<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\MenuStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\AvailabilityOrderConcurrencyTasks;

test('a guest snapshot read before a concurrent pause cannot create an order after the pause commits', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'availability-order-concurrency-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'availability_concurrency', 'database.connections.availability_concurrency' => $connection]);
        DB::purge('availability_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'availability_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $actor = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Availability concurrency']);
        $branch = Branch::factory()->for($organization)->create(['timezone' => 'UTC']);
        BranchSetting::factory()->for($branch)->create();
        $point = ServicePoint::factory()->for($branch)->create(['is_active' => true]);
        $session = TableSession::factory()->forServicePoint($point)->waiterOpened()->active()->create();
        $guest = TableSessionGuest::factory()->for($session)->active()->create();
        $menu = Menu::factory()->for($branch)->create(['status' => MenuStatus::Active]);
        $category = MenuCategory::factory()->for($menu)->create(['is_active' => true]);
        $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run([
            AvailabilityOrderConcurrencyTasks::pause($connection, $branch->id, $actor->id),
            AvailabilityOrderConcurrencyTasks::add($connection, $session->id, $guest->id, $item->id),
        ], 20);
        config(['database.default' => 'availability_concurrency']);
        DB::purge('availability_concurrency');
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'result'))->toBe(['paused', 'unavailable'])
            ->and($branch->fresh()->is_temporarily_closed)->toBeTrue()
            ->and(DraftOrder::query()->count())->toBe(0)
            ->and(DraftOrderItem::query()->count())->toBe(0)
            ->and(AuditLog::query()->where('action', AuditLogAction::BranchAvailabilityChanged)->count())->toBe(1);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('availability_concurrency');
        DB::purge('availability_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm', $path.'.read', $path.'.writing', $path.'.attempt']);
    }
});
