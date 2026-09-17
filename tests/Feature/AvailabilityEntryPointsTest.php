<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Livewire\Organizations\Brands\Branches\Settings;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('saving an already open restaurant profile preserves a later pause and its revision', function (): void {
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Separate operations']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $component = Livewire::actingAs($owner)->test(Settings::class, compact('organization', 'brand', 'branch'));
    $branch->forceFill(['is_temporarily_closed' => true, 'temporary_closed_reason' => 'Planned service', 'pause_version' => 4])->save();

    $component->set('form.publicName', 'Updated public name')->call('save')->assertHasNoErrors();

    expect($branch->fresh()->public_name)->toBe('Updated public name')
        ->and($branch->fresh()->is_temporarily_closed)->toBeTrue()
        ->and($branch->fresh()->temporary_closed_reason)->toBe('Planned service')
        ->and($branch->fresh()->pause_version)->toBe(4);
});
