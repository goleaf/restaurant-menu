<?php

use App\Enums\QrCodeStatus;
use App\Models\AreaNode;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

test('qr codes table stores permanent qr identity fields', function () {
    expect(Schema::hasTable('qr_codes'))->toBeTrue();
    expect(Schema::hasColumns('qr_codes', [
        'service_point_id',
        'public_token',
        'short_code',
        'status',
        'created_by_user_id',
        'revoked_at',
        'revoked_by_user_id',
    ]))->toBeTrue();
});

test('qr code statuses include permanent qr lifecycle states', function () {
    expect(QrCodeStatus::values())->toBe([
        'active',
        'disabled',
        'revoked',
    ]);
});

test('qr code schema does not store mutable service point labels', function () {
    expect(Schema::hasColumn('qr_codes', 'display_number'))->toBeFalse();
    expect(Schema::hasColumn('qr_codes', 'table_number'))->toBeFalse();
    expect(Schema::hasColumn('qr_codes', 'service_point_name'))->toBeFalse();
    expect(Schema::hasColumn('qr_codes', 'area_node_id'))->toBeFalse();
    expect(Schema::hasColumn('qr_codes', 'area_name'))->toBeFalse();
    expect(Schema::hasColumn('qr_codes', 'branch_id'))->toBeFalse();
});

test('qr code belongs to service point and audit users', function () {
    $creator = User::factory()->create();
    $revoker = User::factory()->create();
    $servicePoint = ServicePoint::factory()->create(['name' => 'Table 4']);

    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'public-token-without-table-data',
            'short_code' => 'QR-PRINT-1',
            'status' => QrCodeStatus::Revoked,
            'created_by_user_id' => $creator->id,
            'revoked_at' => now(),
            'revoked_by_user_id' => $revoker->id,
        ]);

    expect($qrCode->servicePoint->is($servicePoint))->toBeTrue();
    expect($servicePoint->qrCodes()->whereKey($qrCode->id)->exists())->toBeTrue();
    expect($qrCode->createdBy->is($creator))->toBeTrue();
    expect($qrCode->revokedBy->is($revoker))->toBeTrue();
    expect($qrCode->fresh()->status)->toBe(QrCodeStatus::Revoked);
    expect($qrCode->fresh()->revoked_at)->not->toBeNull();
});

test('one service point can have only one active qr code', function () {
    $servicePoint = ServicePoint::factory()->create(['name' => 'Table 9']);

    $activeQrCode = QrCode::factory()
        ->for($servicePoint)
        ->create(['status' => QrCodeStatus::Active]);

    expect($servicePoint->fresh()->activeQrCode->is($activeQrCode))->toBeTrue();

    expect(fn () => QrCode::factory()
        ->for($servicePoint)
        ->create(['status' => QrCodeStatus::Active]))
        ->toThrow(QueryException::class);
});

test('service point can keep disabled and revoked qr code history', function () {
    $servicePoint = ServicePoint::factory()->create(['name' => 'Table 10']);

    QrCode::factory()->disabled()->for($servicePoint)->create();
    QrCode::factory()->revoked()->for($servicePoint)->create();
    QrCode::factory()->revoked()->for($servicePoint)->create();

    expect($servicePoint->qrCodes()->count())->toBe(3);
    expect($servicePoint->activeQrCode)->toBeNull();
});

test('qr token and short code stay stable when service point is renamed or moved', function () {
    $firstArea = AreaNode::factory()->create(['name' => 'Main hall']);
    $servicePoint = ServicePoint::factory()
        ->for($firstArea->branch)
        ->for($firstArea)
        ->create([
            'name' => 'Table 12',
            'display_number' => '12',
        ]);
    $secondArea = AreaNode::factory()
        ->for($firstArea->branch)
        ->create(['name' => 'Terrace']);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'stable-public-token-without-table-number',
            'short_code' => 'QR-STABLE',
        ]);

    $servicePoint->update([
        'area_node_id' => $secondArea->id,
        'name' => 'Terrace table 12',
        'display_number' => 'T-12',
    ]);

    $qrCode->refresh();

    expect($qrCode->service_point_id)->toBe($servicePoint->id);
    expect($qrCode->public_token)->toBe('stable-public-token-without-table-number');
    expect($qrCode->short_code)->toBe('QR-STABLE');
});
