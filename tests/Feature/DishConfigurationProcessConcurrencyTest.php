<?php

declare(strict_types=1);

use App\Actions\Menus\CreateMenuItemVariantAction;
use App\Actions\Modifiers\CloneModifierGroupForMenuItemAction;
use App\Enums\AuditLogAction;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\DishConfigurationFixtures;

test('independent sqlite variant creators bind receipts and reject stale competing commands', function (string $race): void {
    withDishConfigurationDatabase(function (array $connection, string $original) use ($race): void {
        [$actor, $branch, $item] = DishConfigurationFixtures::context();
        $requestId = (string) Str::uuid();
        $data = ['type' => 'portion', 'name' => 'Large', 'price' => '12.50', 'weight' => null, 'volume' => null,
            'is_default' => true, 'is_available' => false, 'sort_order' => 7,
            'translations' => ['en' => 'Large', 'lt' => 'Didelė', 'ru' => 'Большая']];
        $first = ['data' => $data, 'request_id' => $requestId, 'version' => $item->variants_version];
        $second = $first;
        if ($race === 'different payload') {
            $second['data']['name'] = 'Different portion';
        } elseif ($race === 'different request') {
            $second['request_id'] = (string) Str::uuid();
        }
        $tasks = [dishConfigurationProcess($connection, $actor->id, $branch->id, $item->id, 'variant', $first),
            dishConfigurationProcess($connection, $actor->id, $branch->id, $item->id, 'variant', $second)];
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'dish_configuration_concurrency']);
        DB::purge('dish_configuration_concurrency');
        $states = array_column($results, 'result');
        sort($states);
        $expected = match ($race) {
            'same command' => ['saved', 'saved'],
            'different payload' => ['denied', 'saved'],
            'different request' => ['conflict', 'saved'],
        };
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'pid'))->not->toContain(getmypid())
            ->and($states)->toBe($expected)
            ->and($item->variants()->count())->toBe(1)
            ->and(MenuOperation::query()->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditLogAction::DishConfigurationChanged)->count())->toBe(1);
        $saved = $item->variants()->sole();
        expect(array_unique(array_column($results, 'id')))->toBe([$saved->id])
            ->and($saved->price_cents)->toBe(1250)->and($saved->is_available)->toBeFalse()
            ->and($saved->sort_order)->toBe(7)
            ->and($saved->translations()->pluck('name', 'language_code')->all())->toBe($data['translations']);
    });
})->with(['same command', 'different payload', 'different request']);

test('independent sqlite copy requests clone and rebind a shared modifier group only once', function (): void {
    withDishConfigurationDatabase(function (array $connection, string $original): void {
        [$actor, $branch, $item] = DishConfigurationFixtures::context();
        $other = MenuItem::factory()->for($item->menu)->for($item->category, 'category')->create();
        $group = ModifierGroup::factory()->for($branch)->withTranslations()->create();
        $option = ModifierOption::factory()->for($group, 'group')->withTranslations()->create([
            'price_delta_cents' => -125, 'is_available' => false, 'sort_order' => 9,
        ]);
        $item->modifierGroups()->attach($group);
        $other->modifierGroups()->attach($group);
        $before = [$option->fresh()->getRawOriginal(), $option->translations()->get()->toArray(),
            $other->fresh()->getRawOriginal(), $group->translations()->get()->toArray()];
        $command = ['group_id' => $group->id, 'name' => 'Private extras', 'links_version' => $item->modifier_links_version,
            'group_version' => $group->fresh()->content_version, 'request_id' => (string) Str::uuid()];
        $task = dishConfigurationProcess($connection, $actor->id, $branch->id, $item->id, 'clone', $command);
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run([$task, $task], 20);
        config(['database.default' => 'dish_configuration_concurrency']);
        DB::purge('dish_configuration_concurrency');

        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'pid'))->not->toContain(getmypid())
            ->and(array_column($results, 'result'))->toBe(['saved', 'saved'])
            ->and(array_unique(array_column($results, 'id')))->toHaveCount(1)
            ->and(ModifierGroup::query()->where('branch_id', $branch->id)->count())->toBe(2)
            ->and(MenuOperation::query()->count())->toBe(1)
            ->and(AuditLog::query()->where('action', AuditLogAction::DishConfigurationChanged)->count())->toBe(1)
            ->and($item->fresh()->modifier_links_version)->toBe($command['links_version'] + 1)
            ->and($group->fresh()->content_version)->toBe($command['group_version'] + 1);
        $copy = $item->modifierGroups()->sole();
        expect($copy->id)->toBe($results[0]['id'])->not->toBe($group->id)
            ->and($other->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$group->id])
            ->and([$option->fresh()->getRawOriginal(), $option->translations()->get()->toArray(),
                $other->fresh()->getRawOriginal(), $group->translations()->get()->toArray()])->toBe($before);
        $copiedOption = $copy->options()->sole();
        expect($copiedOption->id)->not->toBe($option->id)
            ->and($copiedOption->only(['name', 'price_delta_cents', 'is_available', 'sort_order']))->toBe($option->only(['name', 'price_delta_cents', 'is_available', 'sort_order']))
            ->and($copiedOption->translations()->pluck('name', 'language_code')->all())->toBe($option->translations()->pluck('name', 'language_code')->all());
    });
});

/** @param Closure(array<string,mixed>,string):void $run */
function withDishConfigurationDatabase(Closure $run): void
{
    $path = tempnam(sys_get_temp_dir(), 'dish-configuration-race-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'dish_configuration_concurrency', 'database.connections.dish_configuration_concurrency' => $connection]);
        DB::purge('dish_configuration_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'dish_configuration_concurrency', '--force' => true]))->toBe(0);
        test()->seed(SystemPermissionsSeeder::class);
        $run($connection, $original);
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('dish_configuration_concurrency');
        DB::purge('dish_configuration_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
}

/** @param array<string,mixed> $connection @param array<string,mixed> $command */
function dishConfigurationProcess(array $connection, int $actorId, int $branchId, int $itemId, string $kind, array $command): Closure
{
    return static function () use ($connection, $actorId, $branchId, $itemId, $kind, $command): array {
        config(['database.default' => 'dish_configuration_concurrency', 'database.connections.dish_configuration_concurrency' => $connection]);
        DB::purge('dish_configuration_concurrency');
        $actor = User::query()->findOrFail($actorId);
        $branch = Branch::query()->findOrFail($branchId);
        $item = MenuItem::query()->findOrFail($itemId);
        $group = $kind === 'clone' ? ModifierGroup::query()->findOrFail($command['group_id']) : null;
        if (($kind === 'variant' && $item->variants()->exists())
            || ($kind === 'clone' && ! $item->modifierGroups()->whereKey($group->id)->exists())) {
            throw new RuntimeException('Both configuration writers must read the original state before either write.');
        }
        file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
        $deadline = microtime(true) + 5;
        while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (count(glob($connection['database'].'.ready.*')) !== 2) {
            throw new RuntimeException('Dish configuration writers failed to rendezvous.');
        }
        try {
            $saved = $kind === 'variant'
                ? app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, $command['data'], $command['version'], $command['request_id'])
                : app(CloneModifierGroupForMenuItemAction::class)->handle($actor, $branch, $item, $group, $command['name'], $command['links_version'], $command['group_version'], $command['request_id']);

            return ['result' => 'saved', 'pid' => getmypid(), 'id' => $saved->id];
        } catch (ValidationException) {
            return ['result' => 'conflict', 'pid' => getmypid()];
        } catch (AuthorizationException) {
            return ['result' => 'denied', 'pid' => getmypid()];
        }
    };
}
