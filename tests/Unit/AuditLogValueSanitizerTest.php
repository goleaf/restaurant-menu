<?php

declare(strict_types=1);

use App\Actions\AuditLogs\Support\AuditLogValueSanitizer;
use App\Enums\OrderStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('audit values retain their types while nested secrets are masked', function (): void {
    $user = User::factory()->make(['id' => 42]);
    $values = [
        'status' => OrderStatus::Ready,
        'changed_at' => CarbonImmutable::parse('2026-09-14 12:00:00', 'UTC'),
        'actor' => $user,
        'enabled' => false,
        'quantity' => 0,
        'code' => '0',
        'note' => null,
        'nested' => [
            ['status' => OrderStatus::Paid, 'API-Key' => 'test-only-secret'],
            ['password' => 'test-only-password', 'name' => 'Živilė'],
        ],
    ];

    expect((new AuditLogValueSanitizer)->forStorage($values))->toBe([
        'status' => 'ready',
        'changed_at' => '2026-09-14T12:00:00.000000Z',
        'actor' => 42,
        'enabled' => false,
        'quantity' => 0,
        'code' => '0',
        'note' => null,
        'nested' => [
            ['status' => 'paid', 'API-Key' => '[redacted]'],
            ['password' => '[redacted]', 'name' => 'Živilė'],
        ],
    ]);
});

test('audit summaries distinguish booleans null and scalar values', function (mixed $value, string $expected): void {
    expect((new AuditLogValueSanitizer)->summary(['value' => $value]))->toBe('value: '.$expected);
})->with([
    'true' => [true, 'yes'],
    'false' => [false, 'no'],
    'null' => [null, 'empty'],
    'integer zero' => [0, '0'],
    'integer one' => [1, '1'],
    'string zero' => ['0', '0'],
    'empty string' => ['', ''],
    'empty array' => [[], '[]'],
    'unicode and nested secret' => [[['name' => 'Živilė', 'guest_token' => 'test-only-token']], '[{"name":"Živilė","guest_token":"[redacted]"}]'],
    'invalid utf8' => [["\xB1\x31"], '[]'],
]);

test('audit values preserve empty results and redact before formatting', function (): void {
    $sanitizer = new AuditLogValueSanitizer;

    expect($sanitizer->forStorage([]))->toBeNull()
        ->and($sanitizer->summary([]))->toBe('—')
        ->and($sanitizer->forStorage(['password' => new stdClass]))->toBe(['password' => '[redacted]'])
        ->and($sanitizer->summary(['password' => new stdClass]))->toBe('password: [redacted]');
});
