<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\ServicePoints\DeleteServicePointAction;
use App\Livewire\Forms\Floor\FloorFilterForm;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PointEditor;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PrintPanel;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Support\Floor\FloorOptions;
use App\Support\RestaurantSetupOptions;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function floorWorkspaceFixture(): array
{
    test()->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => 'Floor workspace organization']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $area = AreaNode::factory()->for($branch)->create(['name' => 'West hall']);
    $point = ServicePoint::factory()->for($branch)->for($area, 'areaNode')->create(['name' => 'Window table', 'display_number' => '01']);
    $qr = QrCode::factory()->for($point)->create();

    return compact('actor', 'organization', 'brand', 'branch', 'area', 'point', 'qr');
}

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('a numeric room bookmark opens its table and preserves permanent identity after editing', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'area' => $area, 'point' => $point, 'qr' => $qr] = floorWorkspaceFixture();
    $other = ServicePoint::factory()->for($branch)->create(['name' => 'Outside chosen room']);
    $identity = $point->only(['id', 'internal_code', 'area_node_id']);
    $qrIdentity = $qr->fresh()->getAttributes();
    $parameters = ['zone' => (string) $area->id, 'point' => (string) $point->id, 'panel' => 'properties'];

    Livewire::actingAs($actor)->withQueryParams($parameters)
        ->test(Index::class, compact('organization', 'brand', 'branch'))
        ->assertOk()->assertSet('point', (string) $point->id)
        ->assertViewHas('rows', fn (array $rows): bool => array_column($rows, 'id') === [$point->id])
        ->assertDontSee($other->name);

    Livewire::actingAs($actor)->test(PointEditor::class, ['branchId' => $branch->id, 'pointId' => $point->id])
        ->set('form.name', 'Renamed window table')->set('form.displayNumber', 'A-4')
        ->call('save')->assertHasNoErrors();

    expect($point->fresh()->only(array_keys($identity)))->toBe($identity)
        ->and($point->fresh()->display_number)->toBe('A-4')
        ->and($qr->fresh()->getAttributes())->toBe($qrIdentity);
    $this->actingAs($actor)->get(route('organizations.brands.branches.service-points.index', [
        $organization, $brand, $branch, ...$parameters,
    ]))->assertOk()->assertSee('Renamed window table')->assertDontSee($other->name);
});

test('floor zone filters accept valid URL integers without coercing malformed input', function (mixed $value): void {
    $form = new FloorFilterForm(new Index, 'filters');
    $form->area = $value;
    expect(fn () => $form->filters())->toThrow(ValidationException::class);
    expect($form->area)->toBe($value);
})->with([
    'boolean' => [true], 'false' => [false], 'array' => [[1]], 'fraction' => [1.5],
    'zero' => [0], 'negative' => [-1], 'foreign shape' => ['1,2'],
]);

test('a foreign room bookmark cannot reveal another restaurant', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch] = floorWorkspaceFixture();
    $foreign = AreaNode::factory()->create(['name' => 'Private foreign room']);
    $this->actingAs($actor)->get(route('organizations.brands.branches.service-points.index', [
        $organization, $brand, $branch, 'zone' => (string) $foreign->id,
    ]))->assertUnprocessable()->assertDontSee($foreign->name);
});

test('floor room type options use the existing localized names', function (string $locale): void {
    app()->setLocale($locale);
    $expected = RestaurantSetupOptions::areaTypeOptions();
    foreach (FloorOptions::types(true) as $option) {
        expect($option['label'])->toBe($expected[$option['value']]);
    }
})->with(['en', 'lt', 'ru']);

test('created room becomes an addressable editor and mobile return retains the selected room', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'area' => $area, 'point' => $point] = floorWorkspaceFixture();
    Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('showAreas')->assertSet('mobileView', 'zones')
        ->call('chooseArea', (string) $area->id)->assertSet('mobileView', 'tables')
        ->call('openPoint', $point->id)->call('clearEditor')
        ->assertSet('filters.area', (string) $area->id)->assertSet('mobileView', 'tables')
        ->call('createArea')->dispatch('floor-area-created', id: $area->id)
        ->assertSet('areaEditor', (string) $area->id)->assertSet('panel', 'area')
        ->assertViewHas('selectedAreaLabel', $area->name);
});

test('an empty bulk result preserves a prior selection and does not open a new operation', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'point' => $point] = floorWorkspaceFixture();
    Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $point->id)->dispatch('floor-bulk-created', ids: [])
        ->assertHasNoErrors()->assertSet('selectedIds', [$point->id])->assertSet('panel', '');
});

test('adding a QR to print retains existing hidden selection without duplicating the new target', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'point' => $point, 'qr' => $qr] = floorWorkspaceFixture();
    $other = ServicePoint::factory()->for($branch)->create(['name' => 'Other room table']);
    Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $other->id)->set('filters.search', $point->name)
        ->assertViewHas('hiddenSelectedCount', 1)
        ->dispatch('floor-print-point', pointId: $point->id, qrId: $qr->id)
        ->assertSet('selectedIds', [$other->id, $point->id])->assertSet('panel', 'print')
        ->dispatch('floor-print-point', pointId: $point->id, qrId: $qr->id)
        ->assertSet('selectedIds', [$other->id, $point->id]);
});

test('archiving the final row of a later page returns to an existing page without losing filters', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'area' => $area] = floorWorkspaceFixture();
    ServicePoint::factory()->count(20)->for($branch)->for($area, 'areaNode')->create();
    $component = Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('chooseArea', (string) $area->id)->set('filters.sort', 'oldest')->call('setPage', 2);
    $last = $branch->servicePoints()->orderByDesc('id')->firstOrFail();
    app(DeleteServicePointAction::class)->handle($actor, $branch, $last, $last->structure_version);
    $component->dispatch('floor-saved')->assertSet('paginators.page', 1)
        ->assertSet('filters.area', (string) $area->id)->assertSet('filters.sort', 'oldest')
        ->assertViewHas('rows', fn (array $rows): bool => count($rows) === 20);
});

test('print recovery opens only a selected table and keeps the exact set', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'point' => $point] = floorWorkspaceFixture();
    Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $point->id)->call('openSelection', 'print')
        ->dispatch('floor-print-recover-qr', pointId: $point->id)
        ->assertSet('selectedIds', [$point->id])->assertSet('point', (string) $point->id)->assertSet('panel', 'qr');
    $other = ServicePoint::factory()->for($branch)->create();
    Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $point->id)->dispatch('floor-print-recover-qr', pointId: $other->id)->assertForbidden();
});

test('a 200-table creation result suggests the documented first 100 without an invalid selection', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch] = floorWorkspaceFixture();
    $ids = ServicePoint::factory()->count(200)->for($branch)->create()->modelKeys();
    Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->dispatch('floor-bulk-created', ids: $ids)->assertHasNoErrors()->assertSet('selectedIds', array_slice($ids, 0, 100));
});

test('a multi-table print URL cannot silently restore only the last added QR', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'point' => $point, 'qr' => $qr] = floorWorkspaceFixture();
    $other = ServicePoint::factory()->for($branch)->create();
    $component = Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $other->id)->dispatch('floor-print-point', pointId: $point->id, qrId: $qr->id)
        ->assertSet('point', '')->assertSet('qrRecord', '')
        ->assertViewHas('expectedQrIds', [$point->id => $qr->id]);
    Livewire::actingAs($actor)->withQueryParams(['panel' => 'print', 'point' => $component->get('point'), 'qr_record' => $component->get('qrRecord')])
        ->test(Index::class, compact('organization', 'brand', 'branch'))->assertSet('panel', '')->assertSet('selectedIds', []);
});

test('the additive selection and explicit QR versions can prepare the same multi-table document', function (): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'point' => $point, 'qr' => $qr] = floorWorkspaceFixture();
    $other = ServicePoint::factory()->for($branch)->create();
    QrCode::factory()->for($other)->create();
    $component = Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $other->id)->dispatch('floor-print-point', pointId: $point->id, qrId: $qr->id);
    Livewire::actingAs($actor)->test(PrintPanel::class, [
        'branchId' => $branch->id, 'ids' => $component->get('selectedIds'), 'expectedQrIds' => $component->get('printQrIds'),
    ])->call('preparePrint')->assertHasNoErrors()
        ->assertViewHas('snapshot', fn (array $snapshot): bool => count($snapshot['items']) === 2);
});

test('floor service point types are localized in filters and rows', function (string $locale): void {
    ['actor' => $actor, 'organization' => $organization, 'brand' => $brand, 'branch' => $branch, 'point' => $point] = floorWorkspaceFixture();
    app()->setLocale($locale);
    foreach (FloorOptions::types() as $option) {
        expect($option['label'])->toBe(__('reports.service_point_types.'.$option['value']));
    }
    Livewire::actingAs($actor)->test(Index::class, compact('organization', 'brand', 'branch'))
        ->assertViewHas('rows', fn (array $rows): bool => $rows[0]['type'] === __('reports.service_point_types.'.$point->type->value));
})->with(['en', 'lt', 'ru']);
