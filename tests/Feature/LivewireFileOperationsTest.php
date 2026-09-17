<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\DataExportType;
use App\Enums\QrCodeStatus;
use App\Enums\SystemRole;
use App\Livewire\Exports\Index as Exports;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PrintPanel;
use App\Livewire\Superadmin\Dashboard;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FileOperationPage;

beforeEach(function (): void {
    Livewire::listen('call', static function (): void {
        request()->setLaravelSession(session()->driver());
    });
    $this->seed(SystemPermissionsSeeder::class);
    $this->owner = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'File operations']);
    $this->brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($this->brand)->create();
    $this->point = ServicePoint::factory()->for($this->branch)->create();
    $this->qr = QrCode::factory()->for($this->point)->create(['status' => QrCodeStatus::Active]);
});

test('report PDFs download through the authorized Livewire page', function (DataExportType $type): void {
    $component = Livewire::actingAs($this->owner)->withQueryParams(['branch' => $this->branch->id])->test(Exports::class)
        ->call('downloadPdf', $this->branch->id, $type->value)->assertHasNoErrors()->assertFileDownloaded();

    $download = $component->effects['download'];
    expect($download['contentType'])->toBe('application/pdf')
        ->and(base64_decode($download['content'], true))->toStartWith('%PDF-');
})->with(DataExportType::cases());

test('report Livewire validation preserves the shared calendar range contract', function (): void {
    Livewire::actingAs($this->owner)->test(Exports::class)
        ->set('period.date_from', '2026-03-01')->set('period.date_to', '2026-04-01')
        ->call('downloadPdf', $this->branch->id, DataExportType::Orders->value)
        ->assertHasErrors('period.date_to')->assertNoFileDownloaded();
});

test('report downloads reject a different restaurant from their locked page context', function (): void {
    $other = Branch::factory()->for($this->organization)->for($this->brand)->create();
    Livewire::actingAs($this->owner)->withQueryParams(['branch' => $this->branch->id])->test(Exports::class)
        ->call('downloadPdf', $other->id, DataExportType::Orders->value)->assertForbidden();
});

test('QR printing downloads a PDF without replacing permanent identity', function (int $selectionCount): void {
    $ids = [$this->point->id];
    if ($selectionCount === 2) {
        $secondPoint = ServicePoint::factory()->for($this->branch)->create();
        QrCode::factory()->for($secondPoint)->create(['status' => QrCodeStatus::Active]);
        $ids[] = $secondPoint->id;
    }
    $identities = QrCode::query()->orderBy('id')->pluck('public_token', 'id')->all();
    $component = Livewire::actingAs($this->owner)->test(PrintPanel::class, ['branchId' => $this->branch->id, 'ids' => $ids])
        ->assertNoFileDownloaded()->call('preparePrint')->assertHasNoErrors()
        ->call('downloadPdf')->assertHasNoErrors()->assertFileDownloaded(contentType: 'application/pdf');

    expect(base64_decode($component->effects['download']['content'], true))->toStartWith('%PDF-')
        ->and(QrCode::query()->count())->toBe($selectionCount)
        ->and(QrCode::query()->orderBy('id')->pluck('public_token', 'id')->all())->toBe($identities)
        ->and($this->qr->fresh()->public_token)->toBe($this->qr->public_token);
})->with(['single' => [1], 'bulk' => [2]]);

test('CSV preparation finishes database reads before its binary response', function (): void {
    $component = Livewire::actingAs($this->owner)->withQueryParams(['branch' => $this->branch->id])->test(Exports::class)
        ->call('downloadCsv', $this->branch->id, DataExportType::ServicePoints->value)->assertHasNoErrors()->assertNoFileDownloaded();
    $grant = array_key_last(session('prepared_downloads'));
    $component->assertRedirect(route('restaurant.files.download', ['grant' => $grant]));
    session()->save();
    $response = $this->actingAs($this->owner)->withCookie(config('session.cookie'), session()->getId())->get(route('restaurant.files.download', ['grant' => $grant]))->assertOk();
    DB::enableQueryLog();
    DB::flushQueryLog();
    ob_start();
    $response->baseResponse->sendContent();
    $contents = ob_get_clean();
    expect($contents)->toContain($this->point->name)
        ->and(DB::getQueryLog())->toBe([]);
});

test('backup preparation requires a recent password before creating a file', function (): void {
    $role = Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail();
    $this->owner->roles()->attach($role);
    Livewire::actingAs($this->owner)->test(Dashboard::class)
        ->set('sqliteBackup.reason', 'Private recovery snapshot')->set('sqliteBackup.confirmation', 'BACKUP')
        ->call('downloadBackup')->assertRedirect(route('password.confirm'));
    expect(session('prepared_downloads'))->toBeNull();
});

test('a retried signed CSV preparation reuses its file and rejects changed or consumed attempts', function (): void {
    Storage::fake('local');
    $this->actingAs($this->owner);
    $snapshot = FileOperationPage::open($this, route('restaurant.exports.index', ['branch' => $this->branch->id]), 'exports');
    $params = [$this->branch->id, DataExportType::ServicePoints->value];
    $first = FileOperationPage::call($this, $snapshot, 'downloadCsv', params: $params)->assertOk();
    $retry = FileOperationPage::call($this, $snapshot, 'downloadCsv', params: $params)->assertOk();
    expect($retry->json('components.0.effects.redirect'))->toBe($first->json('components.0.effects.redirect'))
        ->and(Storage::disk('local')->files('prepared-downloads'))->toHaveCount(1);
    FileOperationPage::call($this, $snapshot, 'downloadCsv', params: [$this->branch->id, DataExportType::Menu->value])->assertConflict();
    $this->get($first->json('components.0.effects.redirect'))->assertOk();
    FileOperationPage::call($this, $snapshot, 'downloadCsv', params: $params)->assertConflict();
    expect(Storage::disk('local')->files('prepared-downloads'))->toHaveCount(1);
});
