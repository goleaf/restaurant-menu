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
            $table->foreignId('pending_service_point_id')
                ->nullable()
                ->after('active_service_point_id')
                ->constrained('service_points')
                ->cascadeOnDelete();

            $table->unique('pending_service_point_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropUnique(['pending_service_point_id']);
            $table->dropConstrainedForeignId('pending_service_point_id');
        });
    }
};
