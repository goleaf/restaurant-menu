<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\ReissueQrCodeForServicePointAction;
use App\Enums\QrCodeStatus;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PrintPanel;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\QrPanel;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodeSvgRenderer;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'QR panel organization']);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($organization)->for($brand)->create();
    $this->point = ServicePoint::factory()->for($this->branch)->create(['display_number' => 'W-5']);
    $this->qr = QrCode::factory()->for($this->point)->create();
});

test('opening QR and print panels does not generate an identity or image', function (): void {
    $this->mock(QrCodeSvgRenderer::class)->shouldNotReceive('render');
    Livewire::actingAs($this->actor)->test(QrPanel::class, ['branchId' => $this->branch->id, 'pointId' => $this->point->id])
        ->assertSee($this->qr->short_code)->assertSet('operation', '');
    Livewire::actingAs($this->actor)->test(PrintPanel::class, ['branchId' => $this->branch->id, 'ids' => [$this->point->id]])
        ->assertSet('previewId', null)->assertDontSee('data:image/svg+xml;base64,', false);
    expect(Storage::disk('public')->allFiles('qr'))->toBe([])
        ->and(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(1);
});

test('one QR panel keeps the selected table through disable and replacement', function (): void {
    $component = Livewire::actingAs($this->actor)->test(QrPanel::class, ['branchId' => $this->branch->id, 'pointId' => $this->point->id])
        ->call('prepareOperation', 'disable')->set('form.reason', 'Damaged printed label')->call('applyOperation')->assertHasNoErrors()
        ->assertSet('pointId', $this->point->id)->assertSet('operation', '');
    expect($this->qr->fresh()->status)->toBe(QrCodeStatus::Disabled);
    $component->call('prepareOperation', 'reissue')->set('form.confirmation', $this->qr->short_code)->call('applyOperation')->assertHasNoErrors()
        ->assertSet('pointId', $this->point->id);
    expect(QrCode::query()->where('service_point_id', $this->point->id)->where('status', QrCodeStatus::Active)->count())->toBe(1);
});

test('QR validation retains the current operation and prefixes form errors', function (): void {
    Livewire::actingAs($this->actor)->test(QrPanel::class, ['branchId' => $this->branch->id, 'pointId' => $this->point->id])
        ->call('prepareOperation', 'reissue')->set('form.confirmation', 'WRONG')->call('applyOperation')
        ->assertHasErrors('form.confirmation')->assertSet('operation', 'reissue')->assertSet('form.confirmation', 'WRONG');
    expect($this->qr->fresh()->status)->toBe(QrCodeStatus::Active);
});

test('a stale QR panel confirmation cannot revoke a newly replaced identity', function (): void {
    $component = Livewire::actingAs($this->actor)->test(QrPanel::class, ['branchId' => $this->branch->id, 'pointId' => $this->point->id])
        ->call('prepareOperation', 'reissue')->set('form.confirmation', $this->qr->short_code);
    $replacement = app(ReissueQrCodeForServicePointAction::class)->handle($this->qr, $this->actor);
    $component->call('applyOperation')->assertHasErrors('expectedVersion');
    expect($replacement->fresh()->status)->toBe(QrCodeStatus::Active)
        ->and(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(2);
});

test('print preparation stores SVG privately while rendering the same selected identity', function (): void {
    $component = Livewire::actingAs($this->actor)->test(PrintPanel::class, ['branchId' => $this->branch->id, 'ids' => [$this->point->id]])
        ->call('preparePrint')->assertHasNoErrors()->assertSee($this->qr->short_code)
        ->assertSee('data:image/svg+xml;base64,', false);
    expect($component->get('previewId'))->toBeString()
        ->and(json_encode($component->snapshot, JSON_THROW_ON_ERROR))->not->toContain('data:image/svg+xml;base64,', $this->qr->public_token);
    expect(Storage::disk('public')->allFiles('qr'))->toBe([]);
});

test('PDF and browser print refuse a stale reviewed packet', function (): void {
    $component = Livewire::actingAs($this->actor)->test(PrintPanel::class, ['branchId' => $this->branch->id, 'ids' => [$this->point->id]])->call('preparePrint');
    app(ReissueQrCodeForServicePointAction::class)->handle($this->qr, $this->actor);
    $component->call('downloadPdf')->assertHasErrors('service_points')->assertNoFileDownloaded()
        ->call('printLabels')->assertHasErrors('service_points')->assertNotDispatched('floor-print-ready');
});

test('reviewed print settings cannot be silently replaced by a changed number flag or locale', function (): void {
    Livewire::actingAs($this->actor)->test(PrintPanel::class, ['branchId' => $this->branch->id, 'ids' => [$this->point->id]])
        ->call('preparePrint')->set('form.locale', 'lt')->call('downloadPdf')->assertHasErrors('service_points')->assertNoFileDownloaded();
});

test('expired reviewed packets require a new explicit preparation', function (): void {
    $component = Livewire::actingAs($this->actor)->test(PrintPanel::class, ['branchId' => $this->branch->id, 'ids' => [$this->point->id]])->call('preparePrint');
    $this->travel(16)->minutes();
    $component->call('downloadPdf')->assertHasErrors('previewId')->assertNoFileDownloaded();
});

test('QR panel rejects a foreign selected table and immutable identity tampering', function (): void {
    $foreign = ServicePoint::factory()->create();
    expect(fn () => Livewire::actingAs($this->actor)->test(QrPanel::class, ['branchId' => $this->branch->id, 'pointId' => $foreign->id]))->toThrow(ModelNotFoundException::class);
    expect(fn () => Livewire::actingAs($this->actor)->test(QrPanel::class, ['branchId' => $this->branch->id, 'pointId' => $this->point->id])->set('pointId', $foreign->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('QR image download is bound to the displayed identity and rejects a subsequent revocation', function (): void {
    $component = Livewire::actingAs($this->actor)->test(QrPanel::class, ['branchId' => $this->branch->id, 'pointId' => $this->point->id]);
    $component->call('downloadQrImage')->assertFileDownloaded(strtolower($this->qr->short_code).'.svg', contentType: 'image/svg+xml');
    $replacement = app(ReissueQrCodeForServicePointAction::class)->handle($this->qr, $this->actor);
    $component->call('downloadQrImage')->assertHasErrors('expectedVersion')->assertNoFileDownloaded();
    $component->call('openCurrentQr')->assertSet('qrId', $replacement->id)->assertSee($replacement->short_code)
        ->call('downloadQrImage')->assertFileDownloaded(strtolower($replacement->short_code).'.svg', contentType: 'image/svg+xml');
});
