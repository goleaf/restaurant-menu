<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\BulkCreate;
use App\Models\Branch;
use App\Models\OrganizationUser;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Dom\HTMLDocument;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

/** @return array{User, Branch} */
function bulkFormErrorContext(): array
{
    $branch = Branch::factory()->create();
    $actor = User::factory()->create();
    OrganizationUser::factory()->forOrganization($branch->organization)->for($actor)
        ->forSystemRole(SystemRole::Owner)->active()->create();

    return [$actor, $branch];
}

test('bulk preview associates the domain range limit with the ending number field', function (string $locale): void {
    [$actor, $branch] = bulkFormErrorContext();
    app()->setLocale($locale);

    $component = Livewire::actingAs($actor)->test(BulkCreate::class, ['branchId' => $branch->id])
        ->set('form.bulkPrefix', 'Saved_input')
        ->set('form.bulkFrom', '1')
        ->set('form.bulkTo', '201')
        ->call('review');

    expect($component->instance()->getErrorBag()->keys())->toBe(['form.bulkTo']);

    $component->assertHasErrors(['form.bulkTo'])
        ->assertSet('form.bulkPrefix', 'Saved_input')
        ->assertSet('form.bulkTo', '201')
        ->assertSet('preview', [])
        ->assertSet('fingerprint', '')
        ->assertDispatched('floor-invalid');

    $message = __('ui.livewire.organizations.brands.branches.servicepoints.index.create_up_to', ['count' => 200]);
    $component->assertSee($message);
    $document = HTMLDocument::createFromString('<!doctype html><html><body>'.$component->html().'</body></html>');
    $field = $document->querySelector('input[wire\\:model="form.bulkTo"]');
    expect($field)->not->toBeNull()
        ->and($field->getAttribute('aria-invalid'))->toBe('true')
        ->and(ServicePoint::query()->exists())->toBeFalse();
})->with(['en', 'lt', 'ru']);

test('bulk confirmation preserves a stale preview error as an operation error without creating rows', function (): void {
    [$actor, $branch] = bulkFormErrorContext();
    $component = Livewire::actingAs($actor)->test(BulkCreate::class, ['branchId' => $branch->id])
        ->set('form.bulkPrefix', 'T')
        ->set('form.bulkFrom', '1')
        ->set('form.bulkTo', '2')
        ->call('review')->assertOk()->assertHasNoErrors();

    $existing = ServicePoint::factory()->for($branch)->create(['internal_code' => 'T1']);

    $component->call('apply')
        ->assertHasErrors(['expectedPreviewFingerprint'])
        ->assertSee(__('floor.errors.selection_changed'))
        ->assertSet('result', null)
        ->assertDispatched('floor-invalid');

    expect($component->instance()->getErrorBag()->keys())->toBe(['expectedPreviewFingerprint'])
        ->and(ServicePoint::query()->pluck('id')->all())->toBe([$existing->id]);
});
