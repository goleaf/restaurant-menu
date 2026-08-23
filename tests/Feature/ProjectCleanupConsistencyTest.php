<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;

test('livewire pages use class components with separate presentation views', function () {
    $singleFileComponentPattern = '/<\?php\s+.*?new(?:\s+#\[[^]]+\])?\s+class\s+extends\s+Component/s';

    $matchingPaths = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(function (SplFileInfo $file) use ($singleFileComponentPattern): array {
            if (preg_match($singleFileComponentPattern, File::get($file->getPathname())) !== 1) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect(config('livewire.make_command.type'))->toBe('class')
        ->and($matchingPaths)->toBeEmpty();
});

test('first party blade templates do not contain php directives or ordinary php blocks', function () {
    $forbiddenPhpPattern = '/@(?:php|endphp)\b|<\?(?:php|=)/i';

    $matchingPaths = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(function (SplFileInfo $file) use ($forbiddenPhpPattern): array {
            if (preg_match($forbiddenPhpPattern, File::get($file->getPathname())) !== 1) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect($matchingPaths->all())->toBe([]);
});

test('blade templates do not resolve application services or invoke livewire methods', function () {
    $forbiddenDependencyPattern = '/(?:\\\\)?(?:App\\\\(?:Models|Actions|Services)|Illuminate\\\\)|\\b(?:DB|Cache|Auth|Gate|Storage|Http|Route)::|\\b(?:app|resolve|container|request|auth|config|session)\\s*\\(/';
    $livewireMethodPattern = '/\\$this->[A-Za-z_][A-Za-z0-9_]*\\s*\\(/';
    $domainModelPropertyPattern = '/\\$(?:organization|brand|branch|menu|category|item|department|modifierGroup|modifierOption|schedule|servicePoint|qrCode|staffMember|user)->/';

    $matchingPaths = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(function (SplFileInfo $file) use ($domainModelPropertyPattern, $forbiddenDependencyPattern, $livewireMethodPattern): array {
            $contents = File::get($file->getPathname());

            if (
                preg_match($forbiddenDependencyPattern, $contents) !== 1
                && preg_match($livewireMethodPattern, $contents) !== 1
                && preg_match($domainModelPropertyPattern, $contents) !== 1
            ) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect($matchingPaths->all())->toBe([]);
});

test('application operations use explicit dependency injection', function () {
    $serviceLocatorPattern = '/\b(?:app|resolve)\s*\(/';

    $matchingPaths = collect([
        app_path('Actions'),
        app_path('Livewire'),
        app_path('Observers'),
    ])
        ->flatMap(fn (string $path) => File::allFiles($path))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(function (SplFileInfo $file) use ($serviceLocatorPattern): array {
            if (preg_match($serviceLocatorPattern, File::get($file->getPathname())) !== 1) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect($matchingPaths->all())->toBe([]);
});

test('livewire components delegate persistence to application actions', function () {
    $directPersistencePattern = '/(?:(?:::|->)(?:create|updateOrCreate|firstOrCreate|save|saveOrFail|delete|update|attach|detach|sync|syncWithoutDetaching)\s*\()/';

    $matchingPaths = collect(File::allFiles(app_path('Livewire')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(function (SplFileInfo $file) use ($directPersistencePattern): array {
            if (preg_match($directPersistencePattern, File::get($file->getPathname())) !== 1) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect($matchingPaths->all())->toBe([]);
});

test('livewire page metadata and action errors are localized', function () {
    $hardcodedPresentationPattern = '/#\[Title\s*\(|\baddError\s*\([^,]+,\s*[\'\"]/';

    $matchingPaths = collect(File::allFiles(app_path('Livewire')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->mapWithKeys(function (SplFileInfo $file) use ($hardcodedPresentationPattern): array {
            if (preg_match($hardcodedPresentationPattern, File::get($file->getPathname())) !== 1) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => true];
        })
        ->keys()
        ->values();

    expect($matchingPaths->all())->toBe([]);
});

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

    expect($matchingPaths->all())->toBe([]);
});

test('first party code does not contain debug dumps or generated stub comments', function () {
    $debugPattern = '/\b(dd|dump|var_dump|print_r|ray)\s*\(|@dd\b|@dump\b|console\.log\s*\(|debugger;/i';
    $temporaryCommentPattern = '/(?:\/\/|#|\/\*+|\*|\{\{--)[^\r\n]*(?:\b(?:TODO|FIXME|HACK|XXX)\b|Well begun|Aristotle|Marcus Aurelius|Benjamin Franklin|Leonardo da Vinci|Seneca|George Eliot|Laozi|Mustafa Kemal)/i';

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

    expect($matchingPaths->all())->toBe([]);
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

    expect($matchingPaths->all())->toBe([]);
});

test('public entry pages no longer contain starter placeholders', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-page="public-entry"', false)
        ->assertSee(__('ui.views.welcome.shared_hosting_restaurant_platform'))
        ->assertDontSee('Guest placeholder')
        ->assertDontSee('not implemented yet');

    $this->get(route('guest.home'))
        ->assertOk()
        ->assertSee('data-layout="guest"', false)
        ->assertSee('Scan a table QR code')
        ->assertDontSee('Public guest area placeholder')
        ->assertDontSee('Placeholder');
});

test('first party views avoid known responsive and visual anti patterns', function () {
    $forbiddenPatterns = [
        'truncated page heading' => '/<h1\b[^>]*\btruncate\b[^>]*>/i',
        'colored side stripe' => '/\bborder-(?:l|r|s|e)-(?:[2-9]|\[[^]]+\])\b/i',
        'forced initial dark theme' => '/<html\b[^>]*\bclass=["\'][^"\']*\bdark\b[^"\']*["\']/i',
        'oversized modal radius' => '/\brounded-(?:t-)?(?:2xl|3xl)\b/i',
    ];

    $matchingPaths = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->mapWithKeys(function (SplFileInfo $file) use ($forbiddenPatterns): array {
            $contents = File::get($file->getPathname());
            $matches = collect($forbiddenPatterns)
                ->filter(fn (string $pattern): bool => preg_match($pattern, $contents) === 1)
                ->keys()
                ->values()
                ->all();

            if ($matches === []) {
                return [];
            }

            return [str_replace(base_path().'/', '', $file->getPathname()) => $matches];
        });

    expect($matchingPaths->all())->toBe([]);
});

test('first party content images reserve their rendered aspect ratio', function () {
    $imagesWithoutDimensions = collect(File::allFiles(resource_path('views')))
        ->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->flatMap(function (SplFileInfo $file): array {
            preg_match_all('/<img\b[^>]*>/is', File::get($file->getPathname()), $matches);

            return collect($matches[0])
                ->filter(fn (string $tag): bool => preg_match('/\bwidth\s*=/', $tag) !== 1 || preg_match('/\bheight\s*=/', $tag) !== 1)
                ->map(fn (): string => str_replace(base_path().'/', '', $file->getPathname()))
                ->all();
        })
        ->unique()
        ->values();

    expect($imagesWithoutDimensions->all())->toBe([]);
});
