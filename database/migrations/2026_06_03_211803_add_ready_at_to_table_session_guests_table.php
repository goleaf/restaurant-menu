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
        Schema::table('table_session_guests', function (Blueprint $table) {
            $table->timestamp('ready_at')->nullable()->after('status');
            $table->index(['table_session_id', 'status', 'ready_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_session_guests', function (Blueprint $table) {
            $table->dropIndex('table_session_guests_table_session_id_status_ready_at_index');
            $table->dropColumn('ready_at');
        });
    }
};
