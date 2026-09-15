<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\User;

test('menu child reads refresh authorization through the original route middleware', function (string $component, array $calls, array $updates, bool $revokeManagement): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->for($user, 'owner')->create();
    $membership = OrganizationUser::factory()->forOrganization($organization)->forUser($user)->forSystemRole(SystemRole::Owner)->active()->create();
    $permission = Permission::factory()->forSystemPermission(SystemPermission::ManageMenu)->create();
    $membership->load('role')->role->permissions()->attach($permission, ['enabled' => true]);
    $availability = Permission::factory()->forSystemPermission(SystemPermission::ChangeAvailability)->create();
    $membership->role->permissions()->attach($availability, ['enabled' => true]);
    $branch = Branch::factory()->for($organization)->create();

    $page = $this->actingAs($user)->get(route('organizations.brands.branches.menu.index', [$organization, $branch->brand_id, $branch, 'section' => $component]));
    $page->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = collect($matches[1])->map(fn (string $value): string => html_entity_decode($value, ENT_QUOTES))
        ->first(fn (string $value): bool => str_ends_with(json_decode($value, true)['memo']['name'], '.'.$component));
    expect($snapshot)->toBeString();
    $membership->role->permissions()->detach($revokeManagement ? $permission : $availability);

    $response = $this->actingAs($user->fresh())->postJson(route('default-livewire.update'), [
        'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => $calls]],
    ], ['X-Livewire' => '']);
    if ($revokeManagement) {
        $response->assertForbidden();
    } else {
        $response->assertOk();
        expect(json_decode($response->json('components.0.snapshot'), true)['data']['canChangeAvailability'])->toBeFalse();
    }
})->with([
    'catalogue filters' => ['catalog', [], ['filters.search' => 'Soup']],
    'modifiers render' => ['modifiers', [['method' => '$refresh', 'params' => [], 'path' => '']], []],
    'variants render' => ['variants', [['method' => '$refresh', 'params' => [], 'path' => '']], []],
])->with(['management revoked' => true, 'availability revoked' => false]);
