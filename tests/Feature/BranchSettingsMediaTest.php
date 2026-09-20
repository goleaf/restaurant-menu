<?php

declare(strict_types=1);

use App\Actions\Branches\SaveBranchMediaAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToCreateDirectory;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'settings-media-'.getmypid());
    Storage::fake('public');
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Media settings']);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->forBrand($brand)->create(['phone' => '+370 001']);
});

test('media replacement is independent versioned and replay safe', function (string $kind, string $column): void {
    $file = UploadedFile::fake()->image('profile.png');
    $action = app(SaveBranchMediaAction::class);
    $request = (string) Str::uuid();
    $result = $action->handle($this->actor, $this->branch, $kind, $file, hash('sha256', ''), $request);
    $replay = $action->handle($this->actor, $this->branch, $kind, $file, hash('sha256', ''), $request);

    expect($result)->toBe($replay)
        ->and($this->branch->fresh()->getAttribute($column))->toBe($result['path'])
        ->and($this->branch->fresh()->phone)->toBe('+370 001')
        ->and(Storage::disk('public')->allFiles())->toBe([$result['path']])
        ->and(AuditLog::query()->where('branch_id', $this->branch->id)->count())->toBe(1);
    expect(fn () => $action->handle($this->actor, $this->branch, $kind, null, hash('sha256', ''), (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    Storage::disk('public')->assertExists($result['path']);
})->with([['logo', 'logo_path'], ['cover', 'cover_image_path']]);

test('media preparation happens before the settings write transaction', function (): void {
    $transactionLevel = DB::transactionLevel();
    $this->mock(StoreLocalImageAction::class)->shouldReceive('handle')->once()->andReturnUsing(function () use ($transactionLevel): string {
        expect(DB::transactionLevel())->toBe($transactionLevel);
        Storage::disk('public')->put('prepared.png', 'prepared');

        return 'prepared.png';
    });
    app(SaveBranchMediaAction::class)->handle($this->actor, $this->branch, 'logo', UploadedFile::fake()->image('profile.png'), hash('sha256', ''), (string) Str::uuid());
});

test('media persistence veto rolls back its audit receipt and prepared file', function (): void {
    $this->branch->update(['logo_path' => 'original.png']);
    Storage::disk('public')->put('original.png', 'original');
    Branch::updating(fn (): bool => false);

    expect(fn () => app(SaveBranchMediaAction::class)->handle($this->actor, $this->branch, 'logo', UploadedFile::fake()->image('new.png'), hash('sha256', 'original.png'), (string) Str::uuid()))
        ->toThrow(RuntimeException::class);
    expect($this->branch->fresh()->logo_path)->toBe('original.png')
        ->and(Storage::disk('public')->allFiles())->toBe(['original.png'])
        ->and(AuditLog::query()->where('branch_id', $this->branch->id)->count())->toBe(0);
});

test('media after commit failure preserves the committed replacement', function (): void {
    Branch::saved(function (): void {
        DB::afterCommit(fn () => throw new RuntimeException('Observer failed after commit.'));
    });

    expect(fn () => app(SaveBranchMediaAction::class)->handle($this->actor, $this->branch, 'logo', UploadedFile::fake()->image('new.png'), hash('sha256', ''), (string) Str::uuid()))
        ->toThrow(RuntimeException::class, 'Observer failed after commit.');
    $path = $this->branch->fresh()->logo_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('removing the restaurant logo preserves inherited brand media', function (): void {
    $this->branch->brand->update(['logo_path' => 'brand.png']);
    $this->branch->update(['logo_path' => 'restaurant.png']);
    Storage::disk('public')->put('brand.png', 'brand');
    Storage::disk('public')->put('restaurant.png', 'restaurant');
    $action = app(SaveBranchMediaAction::class);
    $request = (string) Str::uuid();
    $result = $action->handle($this->actor, $this->branch, 'logo', null, hash('sha256', 'restaurant.png'), $request);
    expect($action->handle($this->actor, $this->branch, 'logo', null, hash('sha256', 'restaurant.png'), $request))->toBe($result)
        ->and($this->branch->fresh()->logo_path)->toBeNull()
        ->and($this->branch->brand->fresh()->logo_path)->toBe('brand.png');
    Storage::disk('public')->assertExists('brand.png');
    Storage::disk('public')->assertMissing('restaurant.png');
});

test('a real unwritable disk leaves the previous image reference and audit unchanged', function (): void {
    $this->branch->update(['logo_path' => 'original.png']);
    $rootFile = tempnam(sys_get_temp_dir(), 'blocked-media-root-');
    $originalDisk = config('filesystems.disks.public');
    try {
        config(['filesystems.disks.public' => ['driver' => 'local', 'root' => $rootFile, 'throw' => true]]);
        Storage::forgetDisk('public');

        expect(fn () => app(SaveBranchMediaAction::class)->handle($this->actor, $this->branch, 'logo', UploadedFile::fake()->image('new.png'), hash('sha256', 'original.png'), (string) Str::uuid()))
            ->toThrow(UnableToCreateDirectory::class);
        expect($this->branch->fresh()->logo_path)->toBe('original.png')
            ->and(AuditLog::query()->where('branch_id', $this->branch->id)->count())->toBe(0);
    } finally {
        config(['filesystems.disks.public' => $originalDisk]);
        Storage::forgetDisk('public');
        File::delete($rootFile);
    }
});
