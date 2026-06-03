<?php

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
        ->assertSee('Upload logo');

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
        ->assertSee('Upload logo');

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
        ->assertHasErrors('organizationLogos.'.$organization->id);

    Livewire::actingAs($owner)
        ->test(OrganizationsIndex::class)
        ->set('organizationLogos.'.$organization->id, UploadedFile::fake()->image('too-large.png')->size(3000))
        ->call('saveLogo', $organization->id)
        ->assertHasErrors('organizationLogos.'.$organization->id);

    expect($organization->refresh()->logo_path)->toBeNull();
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
