<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyInvitation = new class extends Model
        {
            public $timestamps = false;

            protected $table = 'invitations';
        };

        $hasPlaintextCredential = $legacyInvitation->newQuery()
            ->where(function ($query): void {
                $query->whereNotNull('invite_token')->orWhereNotNull('invite_code');
            })
            ->exists();

        if ($hasPlaintextCredential) {
            throw new RuntimeException('Legacy invitation credentials must be migrated to digests before their plaintext columns can be removed.');
        }

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropUnique(['invite_token']);
            $table->dropUnique(['invite_code']);
            $table->dropColumn(['invite_token', 'invite_code']);
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('invite_token', 80)->nullable()->unique();
            $table->string('invite_code', 32)->nullable()->unique();
        });
    }
};
