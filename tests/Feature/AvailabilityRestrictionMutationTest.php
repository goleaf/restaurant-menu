<?php

declare(strict_types=1);

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Menus\SetMenuItemsRestrictionAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\AuditLog;
use App\Models\AvailabilityCommand;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Support\Availability\AvailabilityDependencyFingerprint;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Fictional availability tests']);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($organization)->for($brand)->create(['timezone' => 'Europe/Vilnius']);
    $menu = Menu::factory()->for($this->branch)->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $this->item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['is_available' => true]);
});

test('explicit restriction commands preserve independent hiding and retries have one audit', function (): void {
    $this->item->update(['hidden_until' => now()->addDay()]);
    $version = $this->item->fresh()->availability_version;
    $targets = [['id' => $this->item->id, 'version' => $version]];
    $request = (string) Str::uuid();
    $action = app(SetMenuItemsRestrictionAction::class);
    $first = $action->handle($this->actor, $this->branch, $targets, 'stop', null, 'Europe/Vilnius', $request);
    $repeat = $action->handle($this->actor, $this->branch, $targets, 'stop', null, 'Europe/Vilnius', $request);
    expect($repeat)->toBe($first)->and($this->item->fresh()->is_available)->toBeFalse()
        ->and($this->item->fresh()->hidden_until)->not->toBeNull()
        ->and($this->item->fresh()->availability_version)->toBe($version + 1)
        ->and(AvailabilityCommand::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('entity_type', 'menu_item')->where('entity_id', $this->item->id)->where('new_values->operation', 'stop')->count())->toBe(1);
    expect(fn () => $action->handle($this->actor, $this->branch, $targets, 'resume', null, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(ValidationException::class);
});

test('bulk restrictions are atomic with stale or foreign targets', function (): void {
    $foreign = MenuItem::factory()->create();
    $action = app(SetMenuItemsRestrictionAction::class);
    expect(fn () => $action->handle($this->actor, $this->branch, [['id' => $this->item->id, 'version' => 0], ['id' => $foreign->id, 'version' => 0]], 'stop', null, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(ValidationException::class);
    expect($this->item->fresh()->is_available)->toBeTrue()->and(AvailabilityCommand::query()->count())->toBe(0);
});

test('failed restriction audit rolls back the field revision and command receipt', function (): void {
    $this->mock(RecordAuditLogAction::class)->shouldReceive('handle')->andThrow(new RuntimeException('Fictional audit failure'));
    expect(fn () => app(SetMenuItemsRestrictionAction::class)->handle($this->actor, $this->branch, [['id' => $this->item->id, 'version' => 0]], 'stop', null, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(RuntimeException::class);
    expect($this->item->fresh()->is_available)->toBeTrue()->and($this->item->fresh()->availability_version)->toBe(0)
        ->and(AvailabilityCommand::query()->count())->toBe(0);
});

test('availability revisions detect an intervening stop and resume even when the flag matches again', function (): void {
    $this->item->update(['is_available' => false]);
    $this->item->update(['is_available' => true]);
    expect($this->item->fresh()->availability_version)->toBe(2);
    expect(fn () => app(SetMenuItemsRestrictionAction::class)->handle($this->actor, $this->branch, [['id' => $this->item->id, 'version' => 0]], 'stop', null, 'Europe/Vilnius', (string) Str::uuid()))->toThrow(ValidationException::class);
});

test('a changed parent dependency invalidates a confirmed item preview inside the write transaction', function (): void {
    $contexts = [$this->item->id => AvailabilityDependencyFingerprint::item($this->item->fresh())];
    $this->branch->update(['is_temporarily_closed' => true]);
    expect(fn () => app(SetMenuItemsRestrictionAction::class)->handle($this->actor, $this->branch,
        [['id' => $this->item->id, 'version' => 0]], 'stop', null, 'Europe/Vilnius', (string) Str::uuid(), null, $contexts))->toThrow(ValidationException::class);
    expect($this->item->fresh()->is_available)->toBeTrue()->and(AvailabilityCommand::query()->count())->toBe(0);
});
