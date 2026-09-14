<?php

declare(strict_types=1);

use App\Models\DatabaseSessionRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('session infrastructure factory persists an anonymous Laravel session without disclosing its credentials', function (): void {
    $record = DatabaseSessionRecord::factory()->create();

    $this->assertModelExists($record);
    expect($record->getTable())->toBe('sessions')
        ->and($record->getKey())->toBeString()->toHaveLength(40)
        ->and($record->getAttribute('user_id'))->toBeNull()
        ->and(unserialize(base64_decode($record->getAttribute('payload')), ['allowed_classes' => false]))->toBe([])
        ->and($record->toArray())->not->toHaveKeys(['id', 'payload']);
});

test('session factory honors the configured session table and connection', function (): void {
    Schema::create('review_sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity');
    });
    config(['session.table' => 'review_sessions', 'session.connection' => 'sqlite']);

    $record = DatabaseSessionRecord::factory()->create();

    $this->assertModelExists($record);
    expect($record->getTable())->toBe('review_sessions')
        ->and($record->getConnectionName())->toBe('sqlite');
});
