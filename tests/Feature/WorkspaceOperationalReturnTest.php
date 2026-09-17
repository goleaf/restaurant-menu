<?php

declare(strict_types=1);

use App\Enums\KitchenDepartmentType;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\KitchenDepartment;
use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\OrganizationUser;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->original = Branch::factory()->create(['name' => 'Original restaurant']);
    $this->other = Branch::factory()->for($this->original->organization)->for($this->original->brand)->create(['name' => 'Other restaurant']);
    OrganizationUser::factory()->forOrganization($this->original->organization)->forUser($this->actor)->forSystemRole(SystemRole::Owner)->active()->create();
    $this->point = ServicePoint::factory()->for($this->original)->occupied()->create(['name' => 'Original table']);
    $this->tableSession = TableSession::factory()->forServicePoint($this->point)->active()->create();
    $this->withSession(['workspace.preference' => ['actor' => $this->actor->id, 'branch' => $this->other->id, 'destination' => 'overview']]);
});

test('a table detail return keeps its restaurant despite another tab changing the preference', function (): void {
    $otherPoint = ServicePoint::factory()->for($this->other)->occupied()->create(['name' => 'Other table']);
    TableSession::factory()->forServicePoint($otherPoint)->active()->create();

    $response = $this->actingAs($this->actor)
        ->get(route('restaurant.waiter.tables.show', $this->tableSession))->assertOk();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $return = $document->querySelector('[data-table-context-header] a');

    expect($return)->not->toBeNull()
        ->and($return->getAttribute('href'))->toBe(route('restaurant.waiter.dashboard', ['branch' => $this->original->id]))
        ->and($return->hasAttribute('wire:navigate'))->toBeTrue()
        ->and(session('workspace.preference.branch'))->toBe($this->other->id);

    $this->get($return->getAttribute('href'))->assertOk()
        ->assertSee('data-page="waiter-dashboard"', false)
        ->assertSeeText('Original table')->assertDontSeeText('Other table');

    $this->actingAs(User::factory()->create())->get($return->getAttribute('href'))->assertForbidden();
});

test('a printed ticket returns to its restaurant and department despite another tab changing the preference', function (KitchenDepartmentType $type): void {
    KitchenDepartment::factory()->for($this->original)->create(['type' => $type, 'name' => 'Earlier department', 'sort_order' => 1]);
    $department = KitchenDepartment::factory()->for($this->original)->create(['type' => $type, 'name' => 'Printed department', 'sort_order' => 20]);
    KitchenDepartment::factory()->for($this->other)->create(['type' => $type, 'name' => 'Other restaurant department']);
    $order = Order::factory()->forTableSession($this->tableSession)->sentToDepartments()->create();
    $ticket = KitchenTicket::factory()->forOrder($order)->for($department, 'kitchenDepartment')->create([
        'department_type' => $type->value,
        'department_name' => $department->name,
    ]);

    $response = $this->actingAs($this->actor)
        ->get(route('restaurant.departments.tickets.print', $ticket))->assertOk();
    $document = HTMLDocument::createFromString($response->getContent(), LIBXML_NOERROR);
    $return = $document->querySelector('.qr-print-toolbar a');

    expect($return)->not->toBeNull()
        ->and($return->getAttribute('href'))->toBe(route('restaurant.'.$type->value.'.dashboard', ['branch' => $this->original->id, 'department' => $department->id]))
        ->and($return->hasAttribute('wire:navigate'))->toBeTrue()
        ->and(session('workspace.preference.branch'))->toBe($this->other->id);

    $dashboard = $this->get($return->getAttribute('href'))->assertOk()
        ->assertSee('data-page="'.$type->value.'-dashboard"', false)
        ->assertDontSeeText('Other restaurant department');
    $document = HTMLDocument::createFromString($dashboard->getContent(), LIBXML_NOERROR);
    $snapshot = json_decode($document->querySelector('[data-page="'.$type->value.'-dashboard"]')->getAttribute('wire:snapshot'), true, flags: JSON_THROW_ON_ERROR);
    expect($snapshot['data']['branchId'])->toBe($this->original->id)
        ->and($snapshot['data']['selectedDepartmentId'])->toBe((string) $department->id)
        ->and($snapshot['data']['selectedDepartmentName'])->toBe($department->name);

    $this->actingAs(User::factory()->create())->get($return->getAttribute('href'))->assertForbidden();
})->with([KitchenDepartmentType::Kitchen, KitchenDepartmentType::Bar]);
