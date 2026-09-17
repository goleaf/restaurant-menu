<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DataExportType;
use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Livewire\Exports\Index as ExportPage;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PrintPanel;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\ManualPayment;
use App\Models\Order;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('authorized staff can download selected branch QR codes as a PDF', function (): void {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $owner] = createPdfDownloadContext();

    $this->get(route('organizations.brands.branches.qr.print', [$organization, $brand, $branch]))->assertRedirect(route('login'));

    Date::setTestNow(CarbonImmutable::parse('2026-08-23 13:14:15'));
    $preparedView = [];
    View::composer('pdf.qr-labels', function (Illuminate\View\View $view) use (&$preparedView): void {
        $preparedView = $view->getData();
    });

    try {
        $response = Livewire::actingAs($owner)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$servicePoint->id], 'expectedQrIds' => [$servicePoint->id => $qrCode->id]])
            ->set('form.preset', 'restaurant')->set('form.printTableNumber', true)
            ->call('preparePrint')->assertHasNoErrors()
            ->call('downloadPdf')->assertHasNoErrors()
            ->assertFileDownloaded('restaurant-menu-qr-branch-'.$branch->id.'-2026-08-23-131415.pdf', contentType: 'application/pdf');
    } finally {
        Date::setTestNow();
    }

    expect(base64_decode($response->effects['download']['content'], true))
        ->toStartWith('%PDF-')
        ->and(strlen((string) base64_decode($response->effects['download']['content'], true)))->toBeGreaterThan(5_000)
        ->and($qrCode->fresh()->public_token)->toBe($qrCode->public_token)
        ->and($preparedView['preset'])->toBe(QrLabelPreset::Restaurant->value)
        ->and($preparedView)->not->toHaveKey('theme');
});

test('PDF QR presets use self contained compiled styles without browser assets', function (QrLabelPreset $preset): void {
    $html = view('pdf.qr-labels', [
        'branchName' => 'Vilniaus virtuvė — Кухня',
        'rows' => [],
        'printTableNumber' => false,
        'preset' => $preset->value,
    ])->render();

    expect($html)
        ->toContain('data-qr-preset="'.$preset->value.'"')
        ->toMatch('/\[data-qr-preset=["\']?'.preg_quote($preset->value, '/').'["\']?\]/')
        ->toContain('55mm')
        ->toContain('DejaVu Sans')
        ->not->toContain('var(', '@vite', 'data-flux', '<script', 'rel="stylesheet"');
})->with(QrLabelPreset::cases());

test('QR PDF selection rejects service points from another branch', function (): void {
    [$organization, $brand, $branch, , , $owner] = createPdfDownloadContext();
    $otherBranch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create();
    $foreignServicePoint = ServicePoint::factory()->for($otherBranch)->create();
    QrCode::factory()
        ->for($foreignServicePoint)
        ->create(['status' => QrCodeStatus::Active]);

    Livewire::actingAs($owner)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$foreignServicePoint->id]])
        ->assertHasErrors('servicePointIds')->assertNoFileDownloaded();
});

test('authorized staff can download every existing report type as a PDF', function (): void {
    [, , $branch, $servicePoint, , $owner] = createPdfDownloadContext();
    $tableSession = TableSession::factory()->forServicePoint($servicePoint)->active()->create();
    Order::factory()
        ->forTableSession($tableSession)
        ->create([
            'confirmed_at' => CarbonImmutable::parse('2026-08-10 12:30:00'),
            'total_price_cents' => 2450,
            'currency' => 'EUR',
        ]);
    ManualPayment::factory()
        ->forTableSession($tableSession)
        ->for($owner, 'recordedBy')
        ->create([
            'payment_method' => ManualPaymentMethod::CardTerminal,
            'scope' => ManualPaymentScope::Table,
            'amount_cents' => 2450,
            'currency' => 'EUR',
            'paid_at' => CarbonImmutable::parse('2026-08-10 12:45:00'),
        ]);

    foreach (DataExportType::cases() as $type) {
        $response = Livewire::actingAs($owner)->withQueryParams(['branch' => $branch->id])->test(ExportPage::class)
            ->set('period.date_from', '2026-08-01')->set('period.date_to', '2026-08-23')
            ->call('downloadPdf', $branch->id, $type->value)->assertHasNoErrors()
            ->assertFileDownloaded(contentType: 'application/pdf');

        expect(base64_decode($response->effects['download']['content'], true))
            ->toStartWith('%PDF-')
            ->and(strlen((string) base64_decode($response->effects['download']['content'], true)))->toBeGreaterThan(1_000);
    }
});

test('PDF reports preserve branch authorization and date range validation', function (): void {
    [, , $branch, , , $owner] = createPdfDownloadContext();
    $unassignedUser = User::factory()->create();
    $this->get(route('restaurant.exports.index'))->assertRedirect(route('login'));
    Livewire::actingAs($unassignedUser)->withQueryParams(['branch' => $branch->id])->test(ExportPage::class)->assertForbidden();
    Livewire::actingAs($owner)->withQueryParams(['branch' => $branch->id])->test(ExportPage::class)
        ->set('period.date_from', '2026-01-01')->set('period.date_to', '2026-03-10')
        ->call('downloadPdf', $branch->id, DataExportType::Orders->value)->assertHasErrors('period.date_to')->assertNoFileDownloaded();
});

test('QR and report screens expose PDF download controls', function (): void {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $owner] = createPdfDownloadContext();

    $this->actingAs($owner)
        ->get(route('organizations.brands.branches.qr.print', [$organization, $brand, $branch]))
        ->assertRedirect(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch, 'zone' => 'all']));

    $this->actingAs($owner)
        ->get(route('organizations.brands.branches.service-points.qr.print', [
            $organization,
            $brand,
            $branch,
            $servicePoint,
            $qrCode,
        ]))
        ->assertRedirect(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch, 'panel' => 'print', 'point' => $servicePoint->id, 'qr_record' => $qrCode->id]));

    Livewire::actingAs($owner)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$servicePoint->id], 'expectedQrIds' => [$servicePoint->id => $qrCode->id]])
        ->assertDontSee('wire:click="downloadPdf"', false)->assertNoFileDownloaded()
        ->call('preparePrint')->assertHasNoErrors()
        ->assertSee('wire:click="downloadPdf"', false)
        ->assertSee(__('qr.actions.download_pdf'));

    $this->actingAs($owner)
        ->get(route('restaurant.exports.index'))
        ->assertOk()
        ->assertSee(__('reports.actions.export_type_pdf', [
            'type' => DataExportType::Orders->label(),
        ]));
});

/**
 * @return array{Organization, Brand, Branch, ServicePoint, QrCode, User}
 */
function createPdfDownloadContext(): array
{
    $owner = User::factory()->create(['name' => 'PDF Owner']);
    $organization = (new CreateOrganizationAction)->handle($owner, ['name' => 'PDF Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'PDF Restaurant']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'PDF Old Town']);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Window Table',
            'display_number' => '12',
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'pdfdownloadtoken1234567890',
            'short_code' => 'QR-PDF1',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $owner->id,
        ]);

    return [$organization, $brand, $branch, $servicePoint, $qrCode, $owner->fresh()];
}
