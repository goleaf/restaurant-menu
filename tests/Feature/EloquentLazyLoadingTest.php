<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;

test('lazy loading prevention follows the application environment', function (string $environment, bool $prevented): void {
    $originalEnvironment = $this->app->environment();
    $originalPrevention = Model::preventsLazyLoading();

    try {
        $this->app->detectEnvironment(fn (): string => $environment);
        (new AppServiceProvider($this->app))->boot();

        expect(Model::preventsLazyLoading())->toBe($prevented);
    } finally {
        $this->app->detectEnvironment(fn (): string => $originalEnvironment);
        Model::preventLazyLoading($originalPrevention);
    }
})->with([
    'local' => ['local', true],
    'testing' => ['testing', true],
    'staging' => ['staging', true],
    'production' => ['production', false],
]);

test('unloaded collection relationships fail before issuing an extra query', function (): void {
    $users = User::factory()->count(2)->create();
    $loadedUsers = User::query()->select(['id'])->whereKey($users->modelKeys())->get();

    $queries = countDatabaseQueries(function () use ($loadedUsers): void {
        expect(fn () => $loadedUsers->firstOrFail()->roles)
            ->toThrow(LazyLoadingViolationException::class);
    });

    expect($queries)->toBe(0);
});

test('explicitly loaded relationships retain a constant query budget', function (int $userCount): void {
    $role = Role::factory()->create();
    $users = User::factory()->count($userCount)->hasAttached($role)->create();

    $queries = countDatabaseQueries(function () use ($users, $userCount, $role): void {
        $loadedUsers = User::query()
            ->select(['id'])
            ->whereKey($users->modelKeys())
            ->with('roles:id')
            ->get();

        expect($loadedUsers)->toHaveCount($userCount);

        foreach ($loadedUsers as $user) {
            expect($user->roles->modelKeys())->toBe([$role->id]);
        }
    });

    expect($queries)->toBe(2);
})->with([2, 15]);
