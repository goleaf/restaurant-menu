<?php

use App\Actions\Media\StoreLocalImageAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Index as BranchesIndex;
use App\Livewire\Organizations\Brands\Index as BrandsIndex;
use App\Livewire\Organizations\Index as OrganizationsIndex;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
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
        ->test(OrganizationsIndex::class)
        ->assertSee(__('uploads.labels.allowed_types', ['types' => StoreLocalImageAction::allowedExtensionsLabel()]))
        ->assertSee(__('uploads.labels.max_size', ['size' => StoreLocalImageAction::maxSizeLabel()]))
        ->set('organizationLogos.'.$organization->id, UploadedFile::fake()->image('organization-logo.png')->size(512))
        ->call('saveLogo', $organization->id)
        ->assertHasNoErrors();

    $organization->refresh();
    $firstPath = $organization->logo_path;

    expect($firstPath)->toStartWith('media/organizations/'.$organization->id.'/logos/');
    Storage::disk('public')->assertExists($firstPath);

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->set('organizationLogos.'.$organization->id, UploadedFile::fake()->image('organization-logo-2.jpg')->size(640))
        ->call('saveLogo', $organization->id)
        ->assertHasNoErrors();

    $organization->refresh();
    $secondPath = $organization->logo_path;

    expect($secondPath)->toStartWith('media/organizations/'.$organization->id.'/logos/');
    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->call('removeLogo', $organization->id)
        ->assertHasNoErrors();

    expect($organization->refresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

test('brand manager can upload a local brand logo', function () {
    [$organization, $brand, , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(BrandsIndex::class, ['organization' => $organization])
        ->set('brandLogos.'.$brand->id, UploadedFile::fake()->image('brand-logo.webp')->size(400))
        ->call('saveLogo', $brand->id)
        ->assertHasNoErrors()
        ->assertSee(__('uploads.actions.replace'));

    $brand->refresh();

    expect($brand->logo_path)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/logos/');
    expect($brand->logoUrl())->toContain('/storage/'.$brand->logo_path);
    Storage::disk('public')->assertExists($brand->logo_path);
});

test('branch manager can upload a local branch logo', function () {
    [$organization, $brand, $branch, $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(BranchesIndex::class, [
            'organization' => $organization,
            'brand' => $brand,
        ])
        ->set('branchLogos.'.$branch->id, UploadedFile::fake()->image('branch-logo.jpg')->size(400))
        ->call('saveLogo', $branch->id)
        ->assertHasNoErrors()
        ->assertSee(__('uploads.actions.replace'));

    $branch->refresh();

    expect($branch->logo_path)->toStartWith('media/organizations/'.$organization->id.'/brands/'.$brand->id.'/branches/'.$branch->id.'/logos/');
    expect($branch->logoUrl())->toContain('/storage/'.$branch->logo_path);
    Storage::disk('public')->assertExists($branch->logo_path);
});

test('local logo uploads validate file type and size', function () {
    [$organization, , , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->set('organizationLogos.'.$organization->id, UploadedFile::fake()->create('logo.txt', 100, 'text/plain'))
        ->call('saveLogo', $organization->id)
        ->assertHasErrors('organizationLogos.'.$organization->id)
        ->assertSee(__('uploads.errors.invalid_type', ['formats' => StoreLocalImageAction::allowedExtensionsLabel()]));

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->set('organizationLogos.'.$organization->id, UploadedFile::fake()->image('too-large.png')->size(3000))
        ->call('saveLogo', $organization->id)
        ->assertHasErrors('organizationLogos.'.$organization->id)
        ->assertSee(__('uploads.errors.too_large', ['size' => StoreLocalImageAction::maxSizeLabel()]));

    expect($organization->refresh()->logo_path)->toBeNull();
});

test('local image uploads reject dangerous original extensions even when content is an image', function () {
    [$organization, , , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->set('organizationLogos.'.$organization->id, UploadedFile::fake()->image('shell.php')->size(100))
        ->call('saveLogo', $organization->id)
        ->assertHasErrors('organizationLogos.'.$organization->id);

    expect($organization->refresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('local image uploads reject scriptable formats', function (string $filename, string $mimeType) {
    [$organization, , , $owner] = createPrompt28MediaContext();

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->set('organizationLogos.'.$organization->id, UploadedFile::fake()->create($filename, 10, $mimeType))
        ->call('saveLogo', $organization->id)
        ->assertHasErrors('organizationLogos.'.$organization->id);

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
        ->and(basename($path))->toMatch('/^[0-9a-f-]{36}\.(jpg|jpeg|png|webp)$/');

    Storage::disk('public')->assertExists($path);
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
