<?php

declare(strict_types=1);

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
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('invite_token_hash', 64)->nullable()->unique()->after('invite_token');
            $table->string('invite_code_hash', 64)->nullable()->unique()->after('invite_code');
            $table->foreignId('accepted_by_user_id')->nullable()->after('invited_by_user_id')->constrained('users')->nullOnDelete();
            $table->dateTime('accepted_at')->nullable()->after('accepted_by_user_id');
        });

        $legacyInvitation = new class extends Model
        {
            public $timestamps = false;

            protected $table = 'invitations';

            protected $guarded = [];
        };

        $legacyInvitation->newQuery()
            ->select(['id', 'invite_token', 'invite_code'])
            ->where(function ($query): void {
                $query->whereNotNull('invite_token')->orWhereNotNull('invite_code');
            })
            ->lazyById()
            ->each(function (Model $invitation): void {
                $token = $invitation->getAttribute('invite_token');
                $code = $invitation->getAttribute('invite_code');

                $invitation->forceFill([
                    'invite_token_hash' => is_string($token) ? hash('sha256', $token) : null,
                    'invite_code_hash' => is_string($code) ? hash('sha256', $code) : null,
                    'invite_token' => null,
                    'invite_code' => null,
                ])->save();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $legacyCredentialCount = (new class extends Model
        {
            public $timestamps = false;

            protected $table = 'invitations';
        })->newQuery()->whereNotNull('invite_token_hash')->count();

        if ($legacyCredentialCount > 0) {
            throw new RuntimeException('This migration cannot be rolled back while hashed invitation credentials exist. Revoke them first to avoid credential loss.');
        }

        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['accepted_by_user_id']);
            $table->dropUnique(['invite_token_hash']);
            $table->dropUnique(['invite_code_hash']);
            $table->dropColumn(['invite_token_hash', 'invite_code_hash', 'accepted_by_user_id', 'accepted_at']);
        });
    }
};
