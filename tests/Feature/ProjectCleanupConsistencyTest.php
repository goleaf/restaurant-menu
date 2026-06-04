<?php

use App\Models\User;

test('shared hosting infrastructure does not expose redis s3 websockets or docker tooling', function () {
    $composerJson = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $packageJson = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

    $composerPackages = array_merge(
        array_keys($composerJson['require'] ?? []),
        array_keys($composerJson['require-dev'] ?? []),
    );

    $nodePackages = array_merge(
        array_keys($packageJson['dependencies'] ?? []),
        array_keys($packageJson['devDependencies'] ?? []),
        array_keys($packageJson['optionalDependencies'] ?? []),
    );

    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections'))->toHaveKey('sqlite')
        ->and(config('database.redis'))->toBeNull()
        ->and(config('filesystems.default'))->toBe('public')
        ->and(config('filesystems.disks'))->toHaveKeys(['local', 'public'])
        ->and(config('filesystems.disks'))->not->toHaveKey('s3')
        ->and(config('broadcasting.default'))->not->toBeIn(['pusher', 'reverb'])
        ->and($composerPackages)->not->toContain('laravel/sail')
        ->and(implode(' ', $composerPackages))->not->toContain('redis')
        ->and(implode(' ', $composerPackages))->not->toContain('pusher')
        ->and(implode(' ', $nodePackages))->not->toContain('socket')
        ->and(implode(' ', $nodePackages))->not->toContain('docker');
});

test('default seeder keeps production seed clean from starter users', function () {
    $this->seed();

    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('public entry pages no longer contain starter placeholders', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-page="public-entry"', false)
        ->assertSee('Shared-hosting restaurant platform')
        ->assertDontSee('Guest placeholder')
        ->assertDontSee('not implemented yet');

    $this->get(route('guest.home'))
        ->assertOk()
        ->assertSee('data-layout="guest"', false)
        ->assertSee('Scan a table QR code')
        ->assertDontSee('Public guest area placeholder')
        ->assertDontSee('Placeholder');
});
