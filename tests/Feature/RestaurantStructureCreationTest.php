<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\Organizations\CreateStructureIdentityAction;
use App\Livewire\Restaurants\Index;
use App\Livewire\Restaurants\StructureCreate;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\StructureCreationReceipt;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Existing organization']);
});

it('creates an empty organization once for an existing owner and opens its canonical editor', function (): void {
    $page = Livewire::actingAs($this->actor)->test(StructureCreate::class, ['kind' => 'organization'])
        ->set('form.name', 'Second organization')->call('save')->assertHasNoErrors();
    $created = Organization::query()->where('name', 'Second organization')->sole();
    $page->assertDispatched('structure-identity-created', kind: 'organization', id: $created->id)
        ->call('save')->assertHasNoErrors();
    expect(Organization::query()->count())->toBe(2)
        ->and($created->brands()->exists())->toBeFalse()
        ->and(StructureCreationReceipt::query()->count())->toBe(1);
    Livewire::actingAs($this->actor)->test(Index::class)
        ->dispatch('structure-identity-created', kind: 'organization', id: $created->id)
        ->assertSet('createKind', null)->assertSet('kind', 'organization')->assertSet('objectId', $created->id);
});

it('creates an empty brand once under the authorized organization', function (): void {
    $page = Livewire::actingAs($this->actor)->test(StructureCreate::class, ['kind' => 'brand', 'organizationId' => $this->organization->id])
        ->set('form.name', 'Independent brand')->call('save')->assertHasNoErrors()->call('save')->assertHasNoErrors();
    $brand = Brand::query()->sole();
    expect($brand->organization_id)->toBe($this->organization->id)->and($brand->branches()->exists())->toBeFalse();
    $page->assertDispatched('structure-identity-created', kind: 'brand', id: $brand->id);
});

it('does not persist while opening addressable creation and retains contextual filters', function (): void {
    $this->actingAs($this->actor)->get(route('restaurants.index', ['view' => 'structure', 'organization' => $this->organization->id, 'create' => 'brand', 'q' => 'unused', 'sort' => 'name_desc']))
        ->assertOk()->assertSee('wire:submit="save"', false)->assertSee($this->organization->name);
    expect(Brand::query()->exists())->toBeFalse()->and(StructureCreationReceipt::query()->exists())->toBeFalse();
});

it('retains validation errors and raw input without creating any identity', function (mixed $name): void {
    Livewire::actingAs($this->actor)->test(StructureCreate::class, ['kind' => 'organization'])
        ->set('form.name', $name)->call('save')->assertHasErrors('form.name')->assertSet('form.name', $name);
    expect(Organization::query()->count())->toBe(1)->and(StructureCreationReceipt::query()->exists())->toBeFalse();
})->with(['empty' => [''], 'array' => [['bad']], 'boolean' => [true], 'too long' => [str_repeat('a', 121)]]);

it('rejects a changed payload on the same key without another identity', function (): void {
    $page = Livewire::actingAs($this->actor)->test(StructureCreate::class, ['kind' => 'organization'])
        ->set('form.name', 'Original creation')->call('save')->assertHasNoErrors();
    $page->set('form.name', 'Different creation')->call('save')->assertHasErrors('creation')->assertSet('form.name', 'Different creation');
    expect(Organization::query()->count())->toBe(2)->and(Organization::query()->where('name', 'Different creation')->exists())->toBeFalse();
});

it('rechecks brand creation permission for both new submissions and completed replays', function (bool $completed): void {
    $page = Livewire::actingAs($this->actor)->test(StructureCreate::class, ['kind' => 'brand', 'organizationId' => $this->organization->id])->set('form.name', 'Brand');
    if ($completed) {
        $page->call('save')->assertHasNoErrors();
    }
    $this->organization->memberships()->where('user_id', $this->actor->id)->update(['status' => 'suspended']);
    $page->call('save')->assertForbidden();
    expect(Brand::query()->count())->toBe($completed ? 1 : 0);
})->with([false, true]);

it('refuses cross-account or archived organization creation contexts', function (): void {
    Livewire::actingAs(User::factory()->create())->test(StructureCreate::class, ['kind' => 'brand', 'organizationId' => $this->organization->id])->assertForbidden();
    $this->organization->delete();
    $this->actingAs($this->actor)->get(route('restaurants.index', ['view' => 'structure', 'organization' => $this->organization->id, 'create' => 'brand']))->assertNotFound();
    expect(Brand::query()->exists())->toBeFalse();
});

it('rejects actor replacement after the form was rendered', function (): void {
    $page = Livewire::actingAs($this->actor)->test(StructureCreate::class, ['kind' => 'organization'])->set('form.name', 'Stolen');
    $this->actingAs(User::factory()->create());
    $page->call('save')->assertStatus(409);
    expect(Organization::query()->count())->toBe(1);
});

it('rolls back the identity and receipt when the required receipt write is vetoed', function (): void {
    Event::listen('eloquent.creating: '.StructureCreationReceipt::class, fn (): bool => false);
    try {
        expect(fn () => app(CreateStructureIdentityAction::class)->handle($this->actor, 'organization', null, ['name' => 'Rolled back'], (string) Str::uuid()))->toThrow(RuntimeException::class);
    } finally {
        Event::forget('eloquent.creating: '.StructureCreationReceipt::class);
    }
    expect(Organization::query()->count())->toBe(1)->and(StructureCreationReceipt::query()->exists())->toBeFalse();
});

it('rolls back rejected identity writes without leaving a receipt', function (string $kind): void {
    $class = $kind === 'organization' ? Organization::class : Brand::class;
    Event::listen('eloquent.creating: '.$class, fn (): bool => false);
    try {
        expect(fn () => app(CreateStructureIdentityAction::class)->handle($this->actor, $kind, $kind === 'brand' ? $this->organization->id : null, ['name' => 'Rejected identity'], (string) Str::uuid()))->toThrow(RuntimeException::class, $kind === 'organization' ? 'Required organization could not be saved.' : 'Required structure identity could not be saved.');
    } finally {
        Event::forget('eloquent.creating: '.$class);
    }
    expect(Organization::query()->count())->toBe(1)->and(Brand::query()->count())->toBe(0)
        ->and(StructureCreationReceipt::query()->exists())->toBeFalse();
})->with(['organization', 'brand']);

it('reauthorizes an organization receipt against its current membership', function (): void {
    $key = (string) Str::uuid();
    $action = app(CreateStructureIdentityAction::class);
    $created = $action->handle($this->actor, 'organization', null, ['name' => 'Created'], $key);
    $created->memberships()->where('user_id', $this->actor->id)->update(['status' => 'suspended']);
    expect(fn () => $action->handle($this->actor, 'organization', null, ['name' => 'Created'], $key))->toThrow(AuthorizationException::class);
    expect(Organization::query()->count())->toBe(2)->and(StructureCreationReceipt::query()->count())->toBe(1);
});

it('has a valid hidden factory receipt and actor scoped request uniqueness', function (): void {
    $receipt = StructureCreationReceipt::factory()->create();
    expect($receipt->actor_id)->toBe($receipt->organization->owner_user_id)
        ->and($receipt->resource_id)->toBe($receipt->organization_id)
        ->and($receipt->toArray())->not->toHaveKeys(['request_key', 'payload_hash']);
    $key = (string) Str::uuid();
    $action = app(CreateStructureIdentityAction::class);
    $first = $action->handle($this->actor, 'organization', null, ['name' => 'First'], $key);
    $other = User::factory()->create();
    $second = $action->handle($other, 'organization', null, ['name' => 'Second'], $key);
    expect($first->id)->not->toBe($second->id)->and($second->owner_user_id)->toBe($other->id);
});
