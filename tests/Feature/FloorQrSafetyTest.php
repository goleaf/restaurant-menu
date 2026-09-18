<?php

declare(strict_types=1);

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\ApplyFloorQrAction;
use App\Actions\QrCodes\BuildQrLabelsPdfAction;
use App\Actions\QrCodes\DisableQrCodeAction;
use App\Actions\QrCodes\ReissueQrCodeForServicePointAction;
use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Enums\QrCodeStatus;
use App\Enums\QrLabelPreset;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Brand;
use App\Models\FloorOperation;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use App\Services\QrCodes\PublicQrUrl;
use App\Services\QrCodes\QrCodeQueryService;
use App\Services\QrCodes\QrPrintSnapshotQuery;
use App\Services\QrCodeSvgRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $this->organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Floor QR restaurant']);
    $brand = Brand::factory()->for($this->organization)->create();
    $this->branch = Branch::factory()->for($this->organization)->for($brand)->create();
    $this->point = ServicePoint::factory()->for($this->branch)->create(['name' => 'Window table', 'display_number' => '12']);
    $this->qr = QrCode::factory()->for($this->point)->create();
});

test('QR image publication rejects a short successful write and retry preserves the identity', function (): void {
    $actual = Storage::disk('public');
    $shortWrite = true;
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('exists')->andReturnUsing(fn (string $path): bool => $actual->exists($path));
    $disk->shouldReceive('get')->andReturnUsing(fn (string $path): ?string => $actual->get($path));
    $disk->shouldReceive('put')->andReturnUsing(function (string $path, string $contents) use ($actual, &$shortWrite): bool {
        return $actual->put($path, $shortWrite ? substr($contents, 0, 32) : $contents, 'public');
    });
    $disk->shouldReceive('move')->andReturnUsing(fn (string $from, string $to): bool => $actual->move($from, $to));
    $disk->shouldReceive('delete')->andReturnUsing(fn (string $path): bool => $actual->delete($path));
    $factory = Mockery::mock(FilesystemFactory::class);
    $factory->shouldReceive('disk')->with('public')->andReturn($disk);
    $action = app()->makeWith(StoreQrCodeImageAction::class, ['filesystem' => $factory]);
    $identity = $this->qr->public_token;

    expect(fn () => $action->handle($this->qr))->toThrow(RuntimeException::class);
    expect($actual->allFiles('qr'))->toBe([]);
    $shortWrite = false;
    $path = $action->handle($this->qr);
    expect($actual->allFiles('qr'))->toBe([$path])->and($actual->get($path))->toContain('</svg>')
        ->and($this->qr->fresh()->public_token)->toBe($identity)
        ->and(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(1);
});

test('a corrupt existing QR image is not presented as ready and repair keeps its identity', function (): void {
    $images = app(StoreQrCodeImageAction::class);
    $path = $images->pathFor($this->qr);
    Storage::disk('public')->put($path, '<svg>partial');
    $query = app(QrCodeQueryService::class);
    expect($query->presentPanel($query->panel($this->branch, $this->point->id))['image_url'])->toBeNull();
    $images->handle($this->qr);
    expect($query->presentPanel($query->panel($this->branch, $this->point->id))['image_url'])->not->toBeNull()
        ->and(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(1);
});

test('QR image print and public link use the configured origin despite an alternate request host', function (): void {
    config()->set('app.url', 'https://trusted.restaurant.test/restaurant');
    $original = app('request');
    URL::setRequest(Request::create('https://alternate.example.test/workspace'));
    try {
        $expectedUrl = 'https://trusted.restaurant.test/restaurant'.route('public.qr.show', ['token' => $this->qr->public_token], false);
        $query = app(QrCodeQueryService::class);
        expect($query->presentPanel($query->panel($this->branch, $this->point->id))['public_url'])->toBe($expectedUrl);
        $path = app(StoreQrCodeImageAction::class)->handle($this->qr);
        expect(Storage::disk('public')->get($path))->toBe(app(QrCodeSvgRenderer::class)->render($expectedUrl));
        $snapshot = app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Classic, true, 'en');
        expect(base64_decode(substr($snapshot['items'][0]['qr_image_data_uri'], strlen('data:image/svg+xml;base64,')), true))
            ->toBe(app(QrCodeSvgRenderer::class)->render($expectedUrl, 420));
    } finally {
        URL::setRequest($original);
    }
});

test('public QR URL rejects unsafe configured origins', function (string $origin): void {
    config()->set('app.url', $origin);
    expect(fn () => app(PublicQrUrl::class)->forToken($this->qr->public_token))->toThrow(LogicException::class);
})->with(['javascript:alert(1)', 'https://user:password@example.test', 'https://example.test?redirect=foreign', 'https://example.test/#foreign', "https://example.test/\nforeign"]);

test('print availability rejects an archived current branch despite a stale object and active assignment', function (): void {
    $role = Role::query()->where('code', SystemRole::Owner->value)->firstOrFail();
    BranchUser::factory()->forBranch($this->branch)->forUser($this->actor)->forRole($role)->create();
    $stale = $this->branch->fresh();
    expect(app(QrPrintSnapshotQuery::class)->availability($this->actor, $stale, [$this->point->id]))->toHaveCount(1);
    $this->branch->delete();
    expect(fn () => app(QrPrintSnapshotQuery::class)->availability($this->actor, $stale, [$this->point->id]))
        ->toThrow(ModelNotFoundException::class);
});

test('real QR PDF preserves bounded selected identities across long localized multipage labels', function (int $count, string $locale): void {
    config()->set('app.url', 'https://trusted.restaurant.test');
    $this->branch->update(['name' => 'Riverside family restaurant — Šeimos restoranas — Семейный ресторан у реки']);
    $points = ServicePoint::factory()->count($count)->for($this->branch)->has(QrCode::factory())->create([
        'name' => 'Window table — Stalas prie lango — Стол у большого окна',
        'display_number' => null,
    ]);
    $before = memory_get_usage(true);
    $started = hrtime(true);
    $snapshot = app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, $points->modelKeys(), QrLabelPreset::Restaurant, true, $locale);
    $pdf = app(BuildQrLabelsPdfAction::class)->handle($this->actor, $this->branch, $points->modelKeys(), QrLabelPreset::Restaurant, true, $snapshot);
    expect($snapshot['items'])->toHaveCount($count)->and($pdf['contents'])->toStartWith('%PDF-')
        ->and(strlen($pdf['contents']))->toBeLessThan(4_000_000)
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $pdf['contents']))->toBeGreaterThan(1);
    $artifacts = getenv('P5_QR_ARTIFACTS');
    if (is_string($artifacts) && is_dir($artifacts)) {
        $prefix = $artifacts.'/labels-'.$count.'-'.$locale;
        file_put_contents($prefix.'.pdf', $pdf['contents']);
        file_put_contents($prefix.'.json', json_encode([
            'count' => $count, 'locale' => $locale, 'pdf_bytes' => strlen($pdf['contents']),
            'snapshot_bytes' => strlen(serialize($snapshot)), 'elapsed_ms' => (hrtime(true) - $started) / 1_000_000,
            'memory_delta' => memory_get_usage(true) - $before, 'peak_memory' => memory_get_peak_usage(true),
            'expected_urls' => $points->load('activeQrCode')->map(fn (ServicePoint $point): string => app(PublicQrUrl::class)->forToken($point->activeQrCode->public_token))->all(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        foreach ($snapshot['items'] as $index => $item) {
            file_put_contents($prefix.'-'.$index.'.svg', base64_decode(substr($item['qr_image_data_uri'], strlen('data:image/svg+xml;base64,')), true));
        }
    }
})->with([[7, 'en'], [7, 'lt'], [7, 'ru'], [100, 'en']]);

test('QR SVG itself preserves four quiet zone modules without relying on page padding', function (): void {
    $url = route('public.qr.show', ['token' => $this->qr->public_token]);
    $expected = (new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd)))->writeString($url);

    expect(app(QrCodeSvgRenderer::class)->render($url))->toBe($expected);
});

test('QR disable and its audit roll back together', function (): void {
    $audit = Mockery::mock(RecordAuditLogAction::class);
    $audit->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));

    expect(fn () => (new DisableQrCodeAction($audit))->handle($this->qr, $this->actor, 'Damaged label'))
        ->toThrow(RuntimeException::class, 'Audit unavailable.');

    expect($this->qr->fresh()->status)->toBe(QrCodeStatus::Active);
});

test('a stale disable cannot turn a revoked QR back into a temporarily disabled identity', function (): void {
    $stale = $this->qr;
    $replacement = app(ReissueQrCodeForServicePointAction::class)->handle($this->qr, $this->actor);

    app(DisableQrCodeAction::class)->handle($stale, $this->actor, 'Stale request');

    expect($stale->fresh()->status)->toBe(QrCodeStatus::Revoked)
        ->and($replacement->fresh()->status)->toBe(QrCodeStatus::Active);
});

test('reviewed print selection binds identities labels preset and language without storing images', function (): void {
    $snapshot = app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Classic, true, 'lt');

    expect($snapshot['service_point_ids'])->toBe([$this->point->id])
        ->and($snapshot['preset'])->toBe('classic')
        ->and($snapshot['locale'])->toBe('lt')
        ->and($snapshot['items'][0]['qr_code_id'])->toBe($this->qr->id)
        ->and($snapshot['items'][0]['service_point_label'])->toBe('12')
        ->and($snapshot['items'][0]['qr_image_data_uri'])->toStartWith('data:image/svg+xml;base64,')
        ->and(Storage::disk('public')->allFiles('qr'))->toBe([]);
});

test('additive print selection preserves explicit QR identities while allowing other selected tables', function (): void {
    $other = ServicePoint::factory()->for($this->branch)->has(QrCode::factory())->create();
    $ids = [$this->point->id, $other->id];
    $expected = [$this->point->id => $this->qr->id];
    $query = app(QrPrintSnapshotQuery::class);
    expect($query->availability($this->actor, $this->branch, $ids, $expected))->toHaveCount(2);
    $snapshot = $query->prepare($this->actor, $this->branch, $ids, QrLabelPreset::Minimal, false, 'en', $expected);
    expect($snapshot['items'])->toHaveCount(2);
    app(ReissueQrCodeForServicePointAction::class)->handle($this->qr, $this->actor);
    expect(fn () => $query->prepare($this->actor, $this->branch, $ids, QrLabelPreset::Minimal, false, 'en', $expected))->toThrow(ValidationException::class);
});

test('partial print identity maps reject foreign targets and invalid QR identifiers', function (string $invalid): void {
    $expected = match ($invalid) {
        'foreign' => [$this->point->id + 999 => $this->qr->id],
        'string' => [$this->point->id => (string) $this->qr->id],
        default => [$this->point->id => 0],
    };
    expect(fn () => app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, 'en', $expected))
        ->toThrow(ValidationException::class);
})->with(['foreign', 'string', 'zero']);

test('reviewed PDF rejects a reissued selected QR instead of silently printing its replacement', function (): void {
    $snapshot = app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, 'en');
    app(ReissueQrCodeForServicePointAction::class)->handle($this->qr, $this->actor);

    expect(fn () => app(BuildQrLabelsPdfAction::class)->handle($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, $snapshot))
        ->toThrow(ValidationException::class);
});

test('reviewed PDF rejects changed printed labels and selection settings', function (string $change): void {
    $snapshot = app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, true, 'en');
    $preset = QrLabelPreset::Minimal;

    if ($change === 'label') {
        $this->point->update(['display_number' => '13']);
    } else {
        $preset = QrLabelPreset::Classic;
    }

    expect(fn () => app(BuildQrLabelsPdfAction::class)->handle($this->actor, $this->branch, [$this->point->id], $preset, true, $snapshot))
        ->toThrow(ValidationException::class);
})->with(['label', 'preset']);

test('PDF rechecks the actors present permission after a print preview', function (): void {
    $snapshot = app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, 'en');
    $this->organization->users()->updateExistingPivot($this->actor->id, ['status' => 'suspended']);

    expect(fn () => app(BuildQrLabelsPdfAction::class)->handle($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, $snapshot))
        ->toThrow(AuthorizationException::class);
});

test('the reusable PDF action rejects more than one hundred selected tables before rendering', function (): void {
    $points = ServicePoint::factory()->count(101)->for($this->branch)->has(QrCode::factory())->create();
    $this->mock(QrCodeSvgRenderer::class)->shouldReceive('render')->andThrow(new RuntimeException('Unbounded rendering.'));

    expect(fn () => app(BuildQrLabelsPdfAction::class)->handle($this->actor, $this->branch, $points->modelKeys(), QrLabelPreset::Minimal, false))
        ->toThrow(ValidationException::class);
});

test('old QR print links reject a replacement identity during preview', function (): void {
    app(ReissueQrCodeForServicePointAction::class)->handle($this->qr, $this->actor);

    expect(fn () => app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, 'en', [$this->point->id => $this->qr->id]))
        ->toThrow(ValidationException::class);
});

test('a QR operation receipt repeats exactly one replacement and repairs its file after a storage failure', function (): void {
    $requestId = (string) Str::uuid();
    $renderer = Mockery::mock(QrCodeSvgRenderer::class);
    $renderer->shouldReceive('render')->once()->andThrow(new RuntimeException('Image unavailable.'));
    $this->instance(QrCodeSvgRenderer::class, $renderer);
    $apply = app(ApplyFloorQrAction::class);
    expect(fn () => $apply->handle($this->actor, $this->branch, $this->point, 'reissue', $this->qr->id, 0, $requestId, '', $this->qr->short_code))
        ->toThrow(RuntimeException::class, 'Image unavailable.');

    expect($this->qr->fresh()->status)->toBe(QrCodeStatus::Revoked)
        ->and(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(2)
        ->and(FloorOperation::query()->where('request_id', $requestId)->count())->toBe(1);
    $this->instance(QrCodeSvgRenderer::class, new QrCodeSvgRenderer);
    $replacement = app(ApplyFloorQrAction::class)->handle($this->actor, $this->branch, $this->point, 'reissue', $this->qr->id, 0, $requestId, '', $this->qr->short_code);

    expect(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(2)
        ->and($replacement->status)->toBe(QrCodeStatus::Active)
        ->and(Storage::disk('public')->allFiles('qr'))->toHaveCount(1);
});

test('a new stale QR command cannot revoke a more recent replacement', function (): void {
    $action = app(ApplyFloorQrAction::class);
    $replacement = $action->handle($this->actor, $this->branch, $this->point, 'reissue', $this->qr->id, 0, (string) Str::uuid(), '', $this->qr->short_code);
    expect(fn () => $action->handle($this->actor, $this->branch, $this->point, 'reissue', $this->qr->id, 0, (string) Str::uuid(), '', $this->qr->short_code))
        ->toThrow(ValidationException::class);

    expect($replacement->fresh()->status)->toBe(QrCodeStatus::Active)
        ->and(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(2);
});

test('QR repair preserves identity and refuses a different restaurant or retired QR', function (): void {
    $action = app(ApplyFloorQrAction::class);
    $repaired = $action->handle($this->actor, $this->branch, $this->point, 'repair', $this->qr->id, 0, (string) Str::uuid());
    expect($repaired->id)->toBe($this->qr->id)->and($repaired->public_token)->toBe($this->qr->public_token)
        ->and($repaired->structure_version)->toBe(0);
    $foreign = Branch::factory()->create();
    expect(fn () => $action->handle($this->actor, $foreign, $this->point, 'repair', $this->qr->id, 0, (string) Str::uuid()))
        ->toThrow(AuthorizationException::class);
    app(DisableQrCodeAction::class)->handle($this->qr, $this->actor, 'Damaged label');
    expect(fn () => $action->handle($this->actor, $this->branch, $this->point, 'repair', $this->qr->id, 1, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
});

test('QR receipt replay rechecks revoked administrator membership', function (): void {
    $requestId = (string) Str::uuid();
    $action = app(ApplyFloorQrAction::class);
    $action->handle($this->actor, $this->branch, $this->point, 'repair', $this->qr->id, 0, $requestId);
    $this->organization->users()->updateExistingPivot($this->actor->id, ['status' => 'suspended']);
    expect(fn () => $action->handle($this->actor, $this->branch, $this->point, 'repair', $this->qr->id, 0, $requestId))
        ->toThrow(AuthorizationException::class);
});

test('print fingerprint detects an active disabled active cycle even when the final label is unchanged', function (): void {
    $snapshot = app(QrPrintSnapshotQuery::class)->prepare($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, 'en');
    $this->qr->forceFill(['status' => QrCodeStatus::Disabled])->save();
    $this->qr->forceFill(['status' => QrCodeStatus::Active])->save();
    expect(fn () => app(BuildQrLabelsPdfAction::class)->handle($this->actor, $this->branch, [$this->point->id], QrLabelPreset::Minimal, false, $snapshot))
        ->toThrow(ValidationException::class);
});

test('a vetoed QR status write cannot commit its audit or command receipt', function (string $operation): void {
    $pointId = $this->point->id;
    $blocked = true;
    QrCode::saving(function (QrCode $qrCode) use ($pointId, &$blocked): ?bool {
        return $blocked && $qrCode->service_point_id === $pointId ? false : null;
    });
    try {
        expect(fn () => app(ApplyFloorQrAction::class)->handle($this->actor, $this->branch, $this->point, $operation, $this->qr->id, 0, (string) Str::uuid(), 'Damaged label', $this->qr->short_code))
            ->toThrow(RuntimeException::class);
    } finally {
        $blocked = false;
    }
    expect($this->qr->fresh()->status)->toBe(QrCodeStatus::Active)
        ->and(FloorOperation::query()->where('target_id', $this->point->id)->count())->toBe(0);
})->with(['disable', 'reissue']);

test('ordinary creation cannot replace a disabled QR without the explicit replacement operation', function (): void {
    app(DisableQrCodeAction::class)->handle($this->qr, $this->actor, 'Damaged label');
    expect(fn () => app(ApplyFloorQrAction::class)->handle($this->actor, $this->branch, $this->point, 'generate', null, null, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect(QrCode::query()->where('service_point_id', $this->point->id)->count())->toBe(1)
        ->and($this->qr->fresh()->status)->toBe(QrCodeStatus::Disabled);
});
