<?php

declare(strict_types=1);

use App\Actions\Branches\UpdateBranchCoverImageAction;
use App\Actions\Branches\UpdateBranchLogoAction;
use App\Actions\Brands\UpdateBrandLogoAction;
use App\Actions\Organizations\UpdateOrganizationLogoAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    ParallelTesting::resolveTokenUsing(fn (): string => 'entity-image-retry-'.getmypid());
    Storage::fake('public');
});

dataset('entity image actions', [
    'organization' => [Organization::class, UpdateOrganizationLogoAction::class, 'logo_path'],
    'brand' => [Brand::class, UpdateBrandLogoAction::class, 'logo_path'],
    'branch' => [Branch::class, UpdateBranchLogoAction::class, 'logo_path'],
    'cover' => [Branch::class, UpdateBranchCoverImageAction::class, 'cover_image_path'],
]);

test('entity image replacement resolves the current path when its caller holds a stale model', function (
    string $modelClass, string $actionClass, string $attribute,
): void {
    $model = $modelClass::factory()->create([$attribute => 'media/retry/original.png']);
    Storage::disk('public')->put($model->getAttribute($attribute), 'original');
    $stale = $model->fresh();

    app($actionClass)->handle($model, UploadedFile::fake()->image('first.png'));
    $firstPath = $model->fresh()->getAttribute($attribute);
    app($actionClass)->handle($stale, UploadedFile::fake()->image('second.png'));
    $finalPath = $model->fresh()->getAttribute($attribute);

    expect($finalPath)->not->toBe($firstPath)
        ->and(Storage::disk('public')->allFiles())->toBe([$finalPath]);
})->with('entity image actions');

test('entity image replacement safely retries the same model after outer rollback', function (
    string $modelClass, string $actionClass, string $attribute,
): void {
    $original = 'media/retry/original.png';
    $model = $modelClass::factory()->create([$attribute => $original]);
    Storage::disk('public')->put($original, 'original');

    expect(fn () => DB::transaction(function () use ($actionClass, $model): never {
        app($actionClass)->handle($model, UploadedFile::fake()->image('discarded.png'));

        throw new RuntimeException('Rollback the image operation.');
    }))->toThrow(RuntimeException::class, 'Rollback the image operation.');

    app($actionClass)->handle($model, UploadedFile::fake()->image('retry.png'));
    $finalPath = $model->fresh()->getAttribute($attribute);

    expect(Storage::disk('public')->allFiles())->toBe([$finalPath]);
})->with('entity image actions');

test('image writes preserve unrelated unsaved caller attributes', function (
    string $modelClass, string $actionClass, string $attribute,
): void {
    $model = $modelClass::factory()->create(['name' => 'Persisted name']);
    $model->name = 'Unsaved name';

    $result = app($actionClass)->handle($model, UploadedFile::fake()->image('image.png'));
    $persisted = $model->fresh();

    expect($result)->toBe($model)
        ->and($persisted->name)->toBe('Persisted name')
        ->and($model->name)->toBe('Unsaved name')
        ->and($model->isDirty('name'))->toBeTrue()
        ->and($model->getAttribute($attribute))->toBe($persisted->getAttribute($attribute))
        ->and($model->isDirty($attribute))->toBeFalse();
})->with('entity image actions');

test('image writes reject archived records before storing files', function (
    string $modelClass, string $actionClass, string $attribute,
): void {
    $model = $modelClass::factory()->create([$attribute => 'media/retry/original.png']);
    Storage::disk('public')->put($model->getAttribute($attribute), 'original');
    $stale = $model->fresh();
    $model->delete();

    expect(fn () => app($actionClass)->handle($stale, UploadedFile::fake()->image('image.png')))
        ->toThrow(ModelNotFoundException::class);

    expect(Storage::disk('public')->allFiles())->toBe(['media/retry/original.png']);
})->with('entity image actions');

test('logo removal resolves the persisted path despite a stale caller', function (
    string $modelClass, string $actionClass,
): void {
    $model = $modelClass::factory()->create(['logo_path' => 'media/retry/original.png']);
    Storage::disk('public')->put($model->logo_path, 'original');
    $stale = $model->fresh();
    app($actionClass)->handle($model, UploadedFile::fake()->image('replacement.png'));

    app($actionClass)->handle($stale, null);

    expect($model->fresh()->logo_path)->toBeNull()
        ->and($stale->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    [Organization::class, UpdateOrganizationLogoAction::class],
    [Brand::class, UpdateBrandLogoAction::class],
    [Branch::class, UpdateBranchLogoAction::class],
]);

test('image writes reject records moved outside the original organization', function (
    string $modelClass, string $actionClass, string $attribute,
): void {
    $model = $modelClass::factory()->create([$attribute => 'media/retry/original.png']);
    Storage::disk('public')->put($model->getAttribute($attribute), 'original');
    $stale = $model->fresh();
    $otherOrganization = Organization::factory()->create();
    $ownership = ['organization_id' => $otherOrganization->id];

    if ($model instanceof Branch) {
        $ownership['brand_id'] = Brand::factory()->create($ownership)->id;
    }

    $model->forceFill($ownership)->save();

    expect(fn () => app($actionClass)->handle($stale, UploadedFile::fake()->image('image.png')))
        ->toThrow(ModelNotFoundException::class);

    expect($model->fresh()->getAttribute($attribute))->toBe('media/retry/original.png')
        ->and(Storage::disk('public')->allFiles())->toBe(['media/retry/original.png']);
})->with([
    [Brand::class, UpdateBrandLogoAction::class, 'logo_path'],
    [Branch::class, UpdateBranchLogoAction::class, 'logo_path'],
    [Branch::class, UpdateBranchCoverImageAction::class, 'cover_image_path'],
]);

test('branch image writes reject a changed brand in the same organization', function (string $actionClass, string $attribute): void {
    $branch = Branch::factory()->create([$attribute => 'media/retry/original.png']);
    Storage::disk('public')->put($branch->getAttribute($attribute), 'original');
    $stale = $branch->fresh();
    $otherBrand = Brand::factory()->create(['organization_id' => $branch->organization_id]);
    $branch->update(['brand_id' => $otherBrand->id]);

    expect(fn () => app($actionClass)->handle($stale, UploadedFile::fake()->image('image.png')))
        ->toThrow(ModelNotFoundException::class);

    expect(Storage::disk('public')->allFiles())->toBe(['media/retry/original.png']);
})->with([
    [UpdateBranchLogoAction::class, 'logo_path'],
    [UpdateBranchCoverImageAction::class, 'cover_image_path'],
]);
