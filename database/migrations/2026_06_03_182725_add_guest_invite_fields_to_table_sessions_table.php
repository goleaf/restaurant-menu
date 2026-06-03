<?php

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
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->string('guest_invite_token', 64)->nullable()->unique()->after('opened_by_guest_id');
            $table->timestamp('guest_invite_created_at')->nullable()->after('guest_invite_token');
            $table->foreignId('guest_invite_created_by_guest_id')
                ->nullable()
                ->after('guest_invite_created_at')
                ->constrained('table_session_guests')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropForeign(['guest_invite_created_by_guest_id']);
            $table->dropUnique(['guest_invite_token']);
            $table->dropColumn([
                'guest_invite_token',
                'guest_invite_created_at',
                'guest_invite_created_by_guest_id',
            ]);
        });
    }
};
