<?php

declare(strict_types=1);

use App\Actions\Media\RemoveLocalImageAction;
use App\Actions\Media\ReplaceLocalImageAction;
use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Restaurants\IdentityEditor;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Support\Media\LocalImageConstraints;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    ParallelTesting::resolveTokenUsing(fn (): string => 'local-media-'.getmypid());
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
});

test('restaurant entity tables store local logo paths', function () {
    expect(Schema::hasColumns('organizations', ['logo_path']))->toBeTrue();
    expect(Schema::hasColumns('brands', ['logo_path']))->toBeTrue();
    expect(Schema::hasColumns('branches', ['logo_path']))->toBeTrue();
});

test('organization owner can upload replace and remove local logo', function () {
    [$organization, , , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'organization', 'objectId' => $organization->id])
        ->assertSee(__('uploads.labels.allowed_types', ['types' => LocalImageConstraints::allowedExtensionsLabel()]))
        ->assertSee(__('uploads.labels.max_size', ['size' => LocalImageConstraints::maxSizeLabel()]))
        ->set('logo', UploadedFile::fake()->image('organization-logo.png')->size(512))
        ->call('saveLogo')
        ->assertHasNoErrors();

    $organization->refresh();
    $firstPath = $organization->logo_path;

    expect($firstPath)->toStartWith('media/organizations/'.$organization->id.'/logos/');
    Storage::disk('public')->assertExists($firstPath);

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'organization', 'objectId' => $organization->id])
        ->set('logo', UploadedFile::fake()->image('organization-logo-2.jpg')->size(640))
        ->call('saveLogo')
        ->assertHasNoErrors();

    $organization->refresh();
    $secondPath = $organization->logo_path;

    expect($secondPath)->toStartWith('media/organizations/'.$organization->id.'/logos/');
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'organization', 'objectId' => $organization->id])
        ->call('removeLogo')
        ->assertHasNoErrors();

    expect($organization->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

test('brand manager can upload a local brand logo', function () {
    [$organization, $brand, , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'brand', 'objectId' => $brand->id])
        ->set('logo', UploadedFile::fake()->image('brand-logo.webp')->size(400))
        ->call('saveLogo')
        ->assertHasNoErrors()
        ->assertSee(__('center.save_logo'));

    $brand->refresh();

    expect($brand->logo_path)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/logos/');
    expect($brand->logoUrl())->toContain('/storage/'.$brand->logo_path);
    Storage::disk('public')->assertExists($brand->logo_path);

    $path = $brand->logo_path;

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'brand', 'objectId' => $brand->id])
        ->call('removeLogo')
        ->assertHasNoErrors();

    expect($brand->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('branch manager can upload a local branch logo', function () {
    [$organization, $brand, $branch, $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->set('logo', UploadedFile::fake()->image('branch-logo.jpg')->size(400))
        ->call('saveLogo')
        ->assertHasNoErrors()
        ->assertSee(__('center.save_logo'));

    $branch->refresh();

    expect($branch->logo_path)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/logos/');
    expect($branch->logoUrl())->toContain('/storage/'.$branch->logo_path);
    Storage::disk('public')->assertExists($branch->logo_path);

    $path = $branch->logo_path;

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'branch', 'objectId' => $branch->id])
        ->call('removeLogo')
        ->assertHasNoErrors();

    expect($branch->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('local logo uploads validate file type and size', function () {
    [$organization, , , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'organization', 'objectId' => $organization->id])
        ->set('logo', UploadedFile::fake()->create('logo.txt', 100, 'text/plain'))
        ->call('saveLogo')
        ->assertHasErrors('logo')
        ->assertSee(__('uploads.errors.invalid_type', ['formats' => LocalImageConstraints::allowedExtensionsLabel()]));

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'organization', 'objectId' => $organization->id])
        ->set('logo', UploadedFile::fake()->image('too-large.png')->size(3000))
        ->call('saveLogo')
        ->assertHasErrors('logo')
        ->assertSee(__('uploads.errors.too_large', ['size' => LocalImageConstraints::maxSizeLabel()]));

    expect($organization->refresh()->logo_path)->toBeNull();
});

test('local image uploads reject dangerous original extensions even when content is an image', function () {
    [$organization, , , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'organization', 'objectId' => $organization->id])
        ->set('logo', UploadedFile::fake()->image('shell.php')->size(100))
        ->call('saveLogo')
        ->assertHasErrors('logo');

    expect($organization->refresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('local image uploads reject scriptable formats', function (string $filename, string $mimeType) {
    [$organization, , , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(IdentityEditor::class, ['kind' => 'organization', 'objectId' => $organization->id])
        ->set('logo', UploadedFile::fake()->create($filename, 10, $mimeType))
        ->call('saveLogo')
        ->assertHasErrors('logo');

    expect($organization->refresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'php' => ['avatar.php', 'application/x-php'],
    'svg' => ['avatar.svg', 'image/svg+xml'],
    'html' => ['avatar.html', 'text/html'],
    'js' => ['avatar.js', 'application/javascript'],
]);

test('local image storage validates direct action calls and never uses the original filename', function () {
    $storeLocalImage = app(StoreLocalImageAction::class);

    expect(fn () => $storeLocalImage->handle(
        file: UploadedFile::fake()->create('avatar.php', 10, 'application/x-php'),
        directory: 'media/security-check',
    ))->toThrow(ValidationException::class);

    expect(fn () => $storeLocalImage->handle(
        file: UploadedFile::fake()->image('logo.png')->size(100),
        directory: '../security-check',
    ))->toThrow(RuntimeException::class, __('uploads.errors.not_writable'));

    $path = $storeLocalImage->handle(
        file: UploadedFile::fake()->image('my.original.logo.png')->size(100),
        directory: 'media/security-check',
    );

    expect($path)->toStartWith('media/security-check/')
        ->and(basename($path))->not->toContain('my.original.logo')
        ->and(basename($path))->toMatch('/^[0-9a-f-]{36}\.v1-[1-9][0-9]*x[1-9][0-9]*\.(jpg|png|webp)$/');

    Storage::disk('public')->assertExists($path);
});

test('failed image replacement keeps the old file and removes the uncommitted new file', function (): void {
    $oldPath = 'media/compensation/old-logo.png';
    Storage::disk('public')->put($oldPath, 'old logo');

    expect(fn () => app(ReplaceLocalImageAction::class)->handle(
        file: UploadedFile::fake()->image('new-logo.png')->size(100),
        directory: 'media/compensation',
        oldPath: $oldPath,
        persist: function (): never {
            throw new RuntimeException('Simulated persistence failure.');
        },
    ))->toThrow(RuntimeException::class, 'Simulated persistence failure.');

    Storage::disk('public')->assertExists($oldPath);
    expect(Storage::disk('public')->allFiles('media/compensation'))->toBe([$oldPath]);
});

test('failed image removal keeps the referenced file', function (): void {
    $oldPath = 'media/compensation/old-cover.png';
    Storage::disk('public')->put($oldPath, 'old cover');

    expect(fn () => app(RemoveLocalImageAction::class)->handle(
        oldPath: $oldPath,
        persist: function (): never {
            throw new RuntimeException('Simulated persistence failure.');
        },
    ))->toThrow(RuntimeException::class, 'Simulated persistence failure.');

    Storage::disk('public')->assertExists($oldPath);
});

function createPrompt28MediaContext(): array
{
    $owner = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'Prompt 28 Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Prompt 28 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Prompt 28 Branch']);

    return [$organization, $brand, $branch, $owner->fresh()];
}
