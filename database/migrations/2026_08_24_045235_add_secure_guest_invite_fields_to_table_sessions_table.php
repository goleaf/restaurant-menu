<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->string('guest_invite_token_hash', 64)->nullable()->unique()->after('guest_invite_token');
            $table->timestamp('guest_invite_expires_at')->nullable()->after('guest_invite_created_at');
        });

        $legacyTableSession = new class extends Model
        {
            protected $table = 'table_sessions';
        };

        $legacyTableSession->newQuery()
            ->select(['id', 'guest_invite_token', 'guest_invite_created_at'])
            ->whereNotNull('guest_invite_token')
            ->lazyById()
            ->each(function (Model $tableSession): void {
                $token = $tableSession->getAttribute('guest_invite_token');
                $createdAt = $tableSession->getAttribute('guest_invite_created_at');

                $tableSession->forceFill([
                    'guest_invite_token_hash' => is_string($token) ? hash('sha256', $token) : null,
                    'guest_invite_token' => null,
                    'guest_invite_expires_at' => $createdAt === null
                        ? now()->addMinutes(30)
                        : CarbonImmutable::parse((string) $createdAt)->addMinutes(30),
                ])->save();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hashedCredentialCount = (new class extends Model
        {
            protected $table = 'table_sessions';
        })->newQuery()->whereNotNull('guest_invite_token_hash')->count();

        if ($hashedCredentialCount > 0) {
            throw new RuntimeException('This migration cannot be rolled back while hashed guest invite credentials exist. Close or rotate those sessions first to avoid credential loss.');
        }

        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->dropUnique(['guest_invite_token_hash']);
            $table->dropColumn(['guest_invite_token_hash', 'guest_invite_expires_at']);
        });
    }
};
