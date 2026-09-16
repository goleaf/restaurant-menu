<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\KitchenDepartmentType;
use App\Livewire\Bar\Dashboard as BarDashboard;
use App\Livewire\Kitchen\Dashboard as KitchenDashboard;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;

test('department invitation destination selects the authorized requested workplace', function (string $component, KitchenDepartmentType $type): void {
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Department destination']);
    $branch = Branch::factory()->for($organization)->create();
    $first = KitchenDepartment::factory()->for($branch)->forType($type)->create(['name' => 'First department', 'sort_order' => 0]);
    $second = KitchenDepartment::factory()->for($branch)->forType($type)->create(['name' => 'Invited department', 'sort_order' => 1]);
    $foreign = KitchenDepartment::factory()->forType($type)->create(['name' => 'Foreign department']);

    Livewire::actingAs($owner)->withQueryParams(['department' => (string) $second->id])
        ->test($component)->assertSet('selectedDepartmentId', (string) $second->id);

    Livewire::actingAs($owner)->withQueryParams(['department' => (string) $foreign->id])
        ->test($component)->assertForbidden()->assertDontSee($foreign->name);

    foreach ([[$second->id], $second->id.'.5', $second->id.'invalid'] as $invalid) {
        Livewire::actingAs($owner)->withQueryParams(['department' => $invalid])
            ->test($component)->assertStatus(422)->assertDontSee($foreign->name);
    }
})->with([
    'kitchen' => [KitchenDashboard::class, KitchenDepartmentType::Kitchen],
    'bar' => [BarDashboard::class, KitchenDepartmentType::Bar],
]);
