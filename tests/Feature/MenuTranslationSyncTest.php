<?php

declare(strict_types=1);

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Actions\Menus\SyncMenuCategoryTranslationsAction;
use App\Actions\Menus\SyncMenuItemTranslationsAction;
use App\Actions\Menus\SyncMenuTranslationsAction;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

dataset('translation sync owners', [
    'menu' => [Menu::class, SyncMenuTranslationsAction::class],
    'category' => [MenuCategory::class, SyncMenuCategoryTranslationsAction::class],
    'dish' => [MenuItem::class, SyncMenuItemTranslationsAction::class],
]);

function translationSyncPayload(Model $owner, string $prefix = 'Updated'): array
{
    $payload = [];
    foreach (['en', 'lt', 'ru'] as $locale) {
        $payload[$locale] = $owner instanceof Menu
            ? "$prefix $locale"
            : ['name' => "$prefix $locale", 'description' => "Description $locale"];
    }

    return $payload;
}

function translationSyncSeed(Model $owner): void
{
    $relation = $owner->translations();
    foreach (['en', 'lt', 'ru'] as $locale) {
        $relation->getRelated()::factory()->create([
            $relation->getForeignKeyName() => $owner->getKey(),
            'language_code' => $locale,
            'name' => "Original $locale",
        ]);
    }
}

test('translation synchronization reads all requested locales once and preserves unchanged replay', function (string $model, string $action, bool $existing): void {
    $owner = $model::factory()->create();
    if ($existing) {
        translationSyncSeed($owner);
    }
    $table = $owner->translations()->getRelated()->getTable();
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        app($action)->handle($owner, translationSyncPayload($owner));
        $queries = collect(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $reads = $queries->filter(fn (array $query): bool => str_starts_with($query['query'], 'select')
        && str_contains($query['query'], '"'.$table.'"'));
    expect($owner->translations()->orderBy('language_code')->pluck('name')->all())
        ->toBe(['Updated en', 'Updated lt', 'Updated ru']);
    expect($reads)->toHaveCount(1, 'Total synchronization queries: '.$queries->count());
    expect($queries)->toHaveCount(match ($model) {
        Menu::class => 13,
        MenuCategory::class => 16,
        MenuItem::class => 22,
    });

    $snapshot = $owner->translations()->orderBy('language_code')->get()->map->getAttributes()->all();
    $replayQueries = countDatabaseQueries(fn () => app($action)->handle($owner, translationSyncPayload($owner)));
    expect($replayQueries)->toBe(1)
        ->and($owner->translations()->orderBy('language_code')->get()->map->getAttributes()->all())->toBe($snapshot);
})->with('translation sync owners')->with([true, false]);

test('translation synchronization ignores stale loaded relations and preserves omitted and foreign locales', function (string $model, string $action): void {
    $owner = $model::factory()->create();
    $foreign = $model::factory()->create();
    translationSyncSeed($owner);
    translationSyncSeed($foreign);
    $owner->load('translations');
    $owner->translations()->where('language_code', 'en')->firstOrFail()->update(['name' => 'New persisted name']);
    $payload = translationSyncPayload($owner, 'Original');

    app($action)->handle($owner, ['en' => $payload['en'], 'de' => $payload['en']]);

    expect($owner->translations()->orderBy('language_code')->pluck('name', 'language_code')->all())
        ->toBe(['en' => 'Original en', 'lt' => 'Original lt', 'ru' => 'Original ru'])
        ->and($foreign->translations()->orderBy('language_code')->pluck('name')->all())
        ->toBe(['Original en', 'Original lt', 'Original ru'])
        ->and(countDatabaseQueries(fn () => app($action)->handle($owner, [])))->toBe(0)
        ->and(countDatabaseQueries(fn () => app($action)->handle($owner, ['de' => $payload['en']])))->toBe(0);
})->with('translation sync owners');

test('a rejected translation write rolls back the surrounding operation', function (string $model, string $action, string $event, bool $existing): void {
    $owner = $model::factory()->create();
    if ($existing) {
        translationSyncSeed($owner);
    }
    $snapshot = $owner->translations()->orderBy('language_code')->get()->map->getAttributes()->all();
    $related = $owner->translations()->getRelated()::class;
    $related::$event(fn (Model $translation): bool => $translation->language_code !== 'lt');

    expect(fn () => DB::transaction(fn () => app($action)->handle($owner, translationSyncPayload($owner))))
        ->toThrow(RuntimeException::class);
    expect($owner->translations()->orderBy('language_code')->get()->map->getAttributes()->all())->toBe($snapshot);
})->with('translation sync owners')->with([
    'create veto' => ['creating', false],
    'update veto' => ['updating', true],
    'save veto' => ['saving', true],
]);

test('translation insertion recovers a unique conflict after its initial read', function (string $model, string $action): void {
    $owner = $model::factory()->create();
    $relation = $owner->translations();
    $armed = true;
    DB::listen(function (QueryExecuted $query) use ($relation, &$armed): void {
        if ($armed && str_starts_with($query->sql, 'select') && str_contains($query->sql, '"'.$relation->getRelated()->getTable().'"')) {
            $armed = false;
            $relation->getRelated()::factory()->create([
                $relation->getForeignKeyName() => $relation->getParent()->getKey(),
                'language_code' => 'en',
                'name' => 'Competing insert',
            ]);
        }
    });

    DB::transaction(fn () => app($action)->handle($owner, translationSyncPayload($owner)));

    expect($armed)->toBeFalse()
        ->and($owner->translations()->orderBy('language_code')->pluck('name')->all())
        ->toBe(['Updated en', 'Updated lt', 'Updated ru']);
})->with('translation sync owners');

test('clearing optional translations dispatches deletion events and invalidates all cached locales', function (string $model, string $action, bool $veto): void {
    $owner = $model::factory()->create();
    translationSyncSeed($owner);
    $branchId = $owner->menu()->value('branch_id');
    $cache = Cache::store(ForgetBranchCacheAction::cacheStore());
    $keys = ForgetBranchCacheAction::cacheKeysForBranch($branchId);
    foreach ($keys as $key) {
        $cache->put($key, 'cached content', 60);
    }
    $related = $owner->translations()->getRelated()::class;
    $deleted = [];
    $related::deleted(function (Model $translation) use (&$deleted): void {
        $deleted[] = $translation->language_code;
    });
    if ($veto) {
        $related::deleting(fn (): bool => false);
    }
    $operation = fn () => DB::transaction(fn () => app($action)->handle($owner, ['lt' => ['name' => '  ']]));

    if ($veto) {
        expect($operation)->toThrow(RuntimeException::class);
    } else {
        $operation();
    }

    expect($deleted)->toBe($veto ? [] : ['lt'])
        ->and($owner->translations()->where('language_code', 'lt')->exists())->toBe($veto)
        ->and($owner->translations()->where('language_code', 'en')->value('name'))->toBe('Original en');
    foreach ($keys as $key) {
        expect($cache->get($key))->toBe($veto ? 'cached content' : null);
    }
})->with([
    'category' => [MenuCategory::class, SyncMenuCategoryTranslationsAction::class],
    'dish' => [MenuItem::class, SyncMenuItemTranslationsAction::class],
])->with([true, false]);
