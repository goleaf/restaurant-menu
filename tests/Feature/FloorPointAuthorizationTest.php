<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\BulkCreate;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PointEditor;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('table editors stop returning protected content after table management is revoked', function (string $panel): void {
    $this->seed(SystemPermissionsSeeder::class);
    $branch = Branch::factory()->create();
    $actor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($branch->organization)->forUser($actor)
        ->forSystemRole(SystemRole::Owner)->active()->create();
    $point = ServicePoint::factory()->for($branch)->create(['name' => 'Restricted physical properties']);
    if ($panel === 'archived') {
        $point->delete();
    }
    $parameters = ['branchId' => $branch->id];
    if (in_array($panel, ['existing', 'archived'], true)) {
        $parameters['pointId'] = $point->id;
    }
    $component = Livewire::actingAs($actor)->test($panel === 'bulk' ? BulkCreate::class : PointEditor::class, $parameters)->assertOk();
    if ($panel === 'bulk') {
        $component->call('review')->assertHasNoErrors();
    }
    PermissionUserOverride::factory()->forUser($actor)->forOrganization($branch->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ManageServicePoints->value)->firstOrFail())
        ->denied()->create();

    expect(Gate::forUser($actor)->allows('view', $branch))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('generateQr', $branch))->toBeTrue()
        ->and(Gate::forUser($actor)->allows('manageServicePoints', $branch))->toBeFalse();

    $component->call('$refresh')->assertForbidden();
})->with(['existing', 'archived', 'new', 'bulk']);
