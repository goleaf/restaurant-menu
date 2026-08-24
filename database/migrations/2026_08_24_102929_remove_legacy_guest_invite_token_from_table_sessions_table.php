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
        $legacyTableSession = new class extends Model
        {
            public $timestamps = false;

            protected $table = 'table_sessions';
        };

        if ($legacyTableSession->newQuery()->whereNotNull('guest_invite_token')->exists()) {
            throw new RuntimeException('Legacy guest invitation credentials must be migrated to digests before their plaintext column can be removed.');
        }

        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->dropUnique(['guest_invite_token']);
            $table->dropColumn('guest_invite_token');
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->string('guest_invite_token', 64)->nullable()->unique();
        });
    }
};
