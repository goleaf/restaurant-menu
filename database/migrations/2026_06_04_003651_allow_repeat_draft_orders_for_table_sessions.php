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
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->dropUnique('draft_orders_table_session_id_unique');
            $table->index(['table_session_id', 'status', 'created_at'], 'draft_orders_session_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->dropIndex('draft_orders_session_status_created_index');
            $table->unique('table_session_id');
        });
    }
};
