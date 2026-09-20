<?php

declare(strict_types=1);

use App\Actions\Branches\SaveBranchMediaAction;
use App\Actions\Branches\UpdateBranchPublicProfileAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('independent SQLite media writers preserve references and detect scoped conflicts', function (string $race): void {
    $directory = sys_get_temp_dir().'/profile-media-race-'.Str::uuid();
    File::makeDirectory($directory, 0700, true);
    $database = $directory.'/database.sqlite';
    touch($database);
    $image = $directory.'/upload.png';
    $uploadFixture = UploadedFile::fake()->image('upload.png');
    File::put($image, File::get($uploadFixture->getPathname()));
    $originalConnection = config('database.default');
    $originalDisk = config('filesystems.disks.public');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $database;
    $disk = ['driver' => 'local', 'root' => $directory.'/public', 'url' => '/storage', 'visibility' => 'public', 'throw' => true];
    try {
        config(['database.default' => 'profile_media_race', 'database.connections.profile_media_race' => $connection, 'filesystems.disks.public' => $disk]);
        DB::purge('profile_media_race');
        Storage::forgetDisk('public');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'profile_media_race', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $actor = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Media concurrency']);
        $brand = Brand::factory()->for($organization)->create();
        $branch = Branch::factory()->forBrand($brand)->create();
        $request = (string) Str::uuid();
        $tasks = [
            profileMediaWriter($connection, $disk, $actor->id, $branch->id, 'logo', $image, $request),
            profileMediaWriter($connection, $disk, $actor->id, $branch->id, $race === 'independent groups' ? 'profile' : 'logo', $image, $race === 'same request' ? $request : (string) Str::uuid()),
        ];
        config(['database.default' => $originalConnection]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        config(['database.default' => 'profile_media_race']);
        DB::purge('profile_media_race');
        $states = array_column($results, 'result');
        sort($states);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2)
            ->and(array_column($results, 'pid'))->not->toContain(getmypid())
            ->and($states)->toBe($race === 'different requests' ? ['conflict', 'saved'] : ['saved', 'saved'])
            ->and(AuditLog::query()->where('branch_id', $branch->id)->count())->toBe($race === 'independent groups' ? 2 : 1)
            ->and(Storage::disk('public')->allFiles())->toBe([$branch->fresh()->logo_path]);
        if ($race === 'independent groups') {
            expect($branch->fresh()->phone)->toBe('+370 009');
        }
    } finally {
        config(['database.default' => $originalConnection, 'filesystems.disks.public' => $originalDisk]);
        DB::disconnect('profile_media_race');
        DB::purge('profile_media_race');
        Storage::forgetDisk('public');
        File::deleteDirectory($directory);
    }
})->with(['independent groups', 'different requests', 'same request']);

/** @param array<string, mixed> $connection @param array<string, mixed> $disk */
function profileMediaWriter(array $connection, array $disk, int $actorId, int $branchId, string $kind, string $image, string $requestId): Closure
{
    return static function () use ($connection, $disk, $actorId, $branchId, $kind, $image, $requestId): array {
        config(['database.default' => 'profile_media_race', 'database.connections.profile_media_race' => $connection, 'filesystems.disks.public' => $disk]);
        DB::purge('profile_media_race');
        Storage::forgetDisk('public');
        $actor = User::query()->findOrFail($actorId);
        $branch = Branch::query()->findOrFail($branchId);
        $fingerprint = $kind === 'profile' ? UpdateBranchPublicProfileAction::fingerprint($branch) : hash('sha256', (string) $branch->logo_path);
        file_put_contents($connection['database'].'.ready.'.getmypid(), 'ready');
        $deadline = microtime(true) + 5;
        while (count(glob($connection['database'].'.ready.*')) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (count(glob($connection['database'].'.ready.*')) !== 2) {
            throw new RuntimeException('Both profile/media processes must read before either writes.');
        }
        try {
            if ($kind === 'profile') {
                app(UpdateBranchPublicProfileAction::class)->handle($actor, $branch, ['phone' => '+370 009'], $fingerprint, $requestId);
            } else {
                app(SaveBranchMediaAction::class)->handle($actor, $branch, 'logo', new UploadedFile($image, 'upload.png', 'image/png', test: true), $fingerprint, $requestId);
            }

            return ['result' => 'saved', 'pid' => getmypid()];
        } catch (ValidationException) {
            return ['result' => 'conflict', 'pid' => getmypid()];
        }
    };
}
