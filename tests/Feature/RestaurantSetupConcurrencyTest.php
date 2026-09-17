<?php

declare(strict_types=1);

use App\Actions\Onboarding\CreateRestaurantSetupAction;
use App\Enums\AuditLogAction;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\RestaurantOnboarding;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\RestaurantSetupConcurrencyTasks;

it('serializes competing setup receipts on independent sqlite writers while preserving the first restaurant', function (bool $firstLaunch, bool $changedPayload): void {
    $path = tempnam(sys_get_temp_dir(), 'restaurant-setup-concurrency-');
    expect($path)->toBeString();
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'restaurant_setup_concurrency', 'database.connections.restaurant_setup_concurrency' => $connection]);
        DB::purge('restaurant_setup_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'restaurant_setup_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $owner = User::factory()->create();
        $firstData = ['organizationId' => null, 'brandId' => null,
            'organizationName' => 'First business', 'brandName' => 'First brand', 'branchName' => 'First restaurant',
            'branchAddress' => 'Example 12', 'branchCity' => 'Vilnius', 'branchCountryCode' => 'LT',
            'branchTimezone' => 'Europe/Vilnius', 'branchCurrency' => 'EUR'];
        $first = app(CreateRestaurantSetupAction::class)->handle($owner, $firstData, (string) Str::uuid());
        $firstBefore = [$first->fresh()->getAttributes(), $first->branch->fresh()->getAttributes(), $first->brand->fresh()->getAttributes(), $first->organization->fresh()->getAttributes()];
        $actor = $firstLaunch ? User::factory()->create() : $owner;
        $data = [...$firstData, 'organizationId' => $firstLaunch ? null : $first->organization_id,
            'brandId' => $firstLaunch ? null : $first->brand_id,
            'organizationName' => 'Concurrent business', 'brandName' => 'Concurrent brand', 'branchName' => 'Concurrent restaurant'];
        $secondData = $changedPayload ? [...$data, 'branchName' => 'Competing restaurant'] : $data;
        $key = (string) Str::uuid();
        $tasks = [
            RestaurantSetupConcurrencyTasks::create($connection, $actor->id, $data, $key),
            RestaurantSetupConcurrencyTasks::create($connection, $actor->id, $secondData, $key),
        ];
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)->not->toContain(getmypid())
            ->and(array_unique(array_column($results, 'php')))->toBe([PHP_VERSION])
            ->and(max(array_column($results, 'started')))->toBeLessThan(min(array_column($results, 'finished')));
        $states = array_column($results, 'state');
        sort($states);
        expect($states)->toBe($changedPayload ? ['conflict', 'created'] : ['created', 'created']);
        config(['database.default' => 'restaurant_setup_concurrency']);
        DB::purge('restaurant_setup_concurrency');
        $created = RestaurantOnboarding::query()->where('creation_key', $key)->sole();
        foreach ($results as $result) {
            if ($result['state'] === 'created') {
                expect($result['setup_id'])->toBe($created->id)->and($result['branch_id'])->toBe($created->branch_id);
            } else {
                expect($result['errors'])->toBe(['creation']);
            }
        }
        $winner = $results[0]['state'] === 'created' ? $data : $secondData;
        expect(app(CreateRestaurantSetupAction::class)->handle($actor, $winner, $key)->id)->toBe($created->id)
            ->and(Branch::query()->count())->toBe(2)
            ->and(Brand::query()->count())->toBe($firstLaunch ? 2 : 1)
            ->and(Organization::query()->count())->toBe($firstLaunch ? 2 : 1)
            ->and(RestaurantOnboarding::query()->count())->toBe(2)
            ->and(AuditLog::query()->where('action', AuditLogAction::RestaurantSetupCreated->value)->where('entity_id', $created->id)->count())->toBe(1)
            ->and($created->branch->settings()->count())->toBe(1)
            ->and($created->branch->kitchenDepartments()->count())->toBe(4)
            ->and($created->branch->is_active)->toBeFalse()->and($created->completed_at)->toBeNull()
            ->and([$first->fresh()->getAttributes(), $first->branch->fresh()->getAttributes(), $first->brand->fresh()->getAttributes(), $first->organization->fresh()->getAttributes()])->toBe($firstBefore);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('restaurant_setup_concurrency');
        DB::purge('restaurant_setup_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
})->with([true, false])->with([true, false]);
