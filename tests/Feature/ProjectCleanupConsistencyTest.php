<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

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

test('first party code does not reintroduce forbidden product modules', function () {
    $forbiddenModulePattern = '/(^|[\/\\\\])(training|checklist|issue|safe)([\/\\\\.]|$)/i';

    $matchingPaths = collect(File::allFiles(app_path()))
        ->map(fn (SplFileInfo $file): string => str_replace(base_path().'/', '', $file->getPathname()))
        ->filter(fn (string $path): bool => preg_match($forbiddenModulePattern, $path) === 1)
        ->values();

    expect($matchingPaths)->toBeEmpty();
});

test('first party code does not contain debug dumps or generated stub comments', function () {
    $debugPattern = '/\b(dd|dump|var_dump|print_r|ray)\s*\(|@dd\b|@dump\b|console\.log\s*\(|debugger;/i';
    $temporaryCommentPattern = '/\b(TODO|FIXME|HACK|XXX)\b|Well begun|Aristotle|Marcus Aurelius|Benjamin Franklin|Leonardo da Vinci|Seneca|George Eliot|Laozi|Mustafa Kemal/i';

    $matchingPaths = collect([
        app_path(),
        config_path(),
        database_path(),
        resource_path('views'),
        resource_path('js'),
        resource_path('css'),
        base_path('routes'),
    ])
        ->filter(fn (string $path): bool => File::exists($path))
        ->flatMap(fn (string $path) => File::allFiles($path))
        ->filter(fn (SplFileInfo $file): bool => in_array($file->getExtension(), ['php', 'blade.php', 'js', 'css'], true))
        ->mapWithKeys(function (SplFileInfo $file) use ($debugPattern, $temporaryCommentPattern): array {
            $contents = File::get($file->getPathname());

            if (preg_match($debugPattern, $contents) !== 1 && preg_match($temporaryCommentPattern, $contents) !== 1) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect($matchingPaths)->toBeEmpty();
});

test('blade status labels are rendered through translations', function () {
    $directStatusLabelPattern = '/\{\{\s*\$[A-Za-z_][A-Za-z0-9_]*\[[\'"]status_label[\'"]\]\s*\}\}/';

    $matchingPaths = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(function (SplFileInfo $file) use ($directStatusLabelPattern): array {
            $contents = File::get($file->getPathname());

            if (preg_match($directStatusLabelPattern, $contents) !== 1) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect($matchingPaths)->toBeEmpty();
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
