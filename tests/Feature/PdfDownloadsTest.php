<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DataExportType;
use App\Enums\QrCodeStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('authorized staff can download selected branch QR codes as a PDF', function (): void {
    [$organization, $brand, $branch, $servicePoint, $qrCode, $owner] = createPdfDownloadContext();

    $this->post(pdfQrDownloadUrl($organization, $brand, $branch), [
        'service_points' => [$servicePoint->id],
        'preset' => 'restaurant',
        'print_table_number' => true,
    ])->assertRedirect(route('login'));

    Date::setTestNow(CarbonImmutable::parse('2026-08-23 13:14:15'));

    try {
        $response = $this->actingAs($owner)
            ->post(pdfQrDownloadUrl($organization, $brand, $branch), [
                'service_points' => [$servicePoint->id],
                'preset' => 'restaurant',
                'print_table_number' => true,
            ])
            ->assertOk()
            ->assertDownload('restaurant-menu-qr-branch-'.$branch->id.'-2026-08-23-131415.pdf')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    } finally {
        Date::setTestNow();
    }

    expect($response->getContent())
        ->toStartWith('%PDF-')
        ->and(strlen((string) $response->getContent()))->toBeGreaterThan(5_000)
        ->and($qrCode->fresh()->public_token)->toBe($qrCode->public_token);
});

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

    $printUrl = route('organizations.brands.branches.qr.print', [$organization, $brand, $branch]);

    $this->actingAs($owner)
        ->from($printUrl)
        ->post(pdfQrDownloadUrl($organization, $brand, $branch), [
            'service_points' => [$foreignServicePoint->id],
            'preset' => 'minimal',
            'print_table_number' => false,
        ])
        ->assertRedirect($printUrl)
        ->assertSessionHasErrors('service_points.0');
});

test('authorized staff can download every existing report type as a PDF', function (): void {
    [, , $branch, , , $owner] = createPdfDownloadContext();

    foreach (DataExportType::cases() as $type) {
        $response = $this->actingAs($owner)
            ->get(route('restaurant.exports.pdf', [
                'branch' => $branch,
                'export' => $type->value,
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-23',
            ]))
            ->assertOk()
            ->assertDownload()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($response->getContent())
            ->toStartWith('%PDF-')
            ->and(strlen((string) $response->getContent()))->toBeGreaterThan(1_000);
    }
});

test('PDF reports preserve branch authorization and date range validation', function (): void {
    [, , $branch, , , $owner] = createPdfDownloadContext();
    $unassignedUser = User::factory()->create();
    $url = route('restaurant.exports.pdf', [
        'branch' => $branch,
        'export' => DataExportType::Orders->value,
        'date_from' => '2026-01-01',
        'date_to' => '2026-03-10',
    ]);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($unassignedUser)->get($url)->assertForbidden();
    $this->actingAs($owner)
        ->from(route('restaurant.exports.index'))
        ->get($url)
        ->assertRedirect(route('restaurant.exports.index'))
        ->assertSessionHasErrors('date_to');
});

test('QR and report screens expose PDF download controls', function (): void {
    [$organization, $brand, $branch, $servicePoint, , $owner] = createPdfDownloadContext();

    $this->actingAs($owner)
        ->get(route('organizations.brands.branches.qr.print', [$organization, $brand, $branch]))
        ->assertOk()
        ->assertSee(__('qr.actions.download_pdf'))
        ->assertSee(pdfQrDownloadUrl($organization, $brand, $branch), false);

    $this->actingAs($owner)
        ->get(route('organizations.brands.branches.service-points.qr.print', [
            $organization,
            $brand,
            $branch,
            $servicePoint,
            $servicePoint->activeQrCode,
        ]))
        ->assertOk()
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

function pdfQrDownloadUrl(Organization $organization, Brand $brand, Branch $branch): string
{
    return route('organizations.brands.branches.qr.pdf', [
        $organization,
        $brand,
        $branch,
    ]);
}
