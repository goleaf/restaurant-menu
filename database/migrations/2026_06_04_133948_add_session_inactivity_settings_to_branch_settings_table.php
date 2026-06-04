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
        Schema::table('branch_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('inactivity_warning_minutes')->default(45)->after('polling_interval_seconds');
            $table->unsignedSmallInteger('pending_session_expire_minutes')->default(30)->after('inactivity_warning_minutes');
        });

        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->index(['branch_id', 'status', 'updated_at', 'id'], 'table_sessions_branch_status_updated_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->dropIndex('table_sessions_branch_status_updated_idx');
        });

        Schema::table('branch_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'inactivity_warning_minutes',
                'pending_session_expire_minutes',
            ]);
        });
    }
};
