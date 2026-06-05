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
        Schema::create('table_session_service_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_service_point_id')
                ->nullable()
                ->constrained('service_points')
                ->cascadeOnDelete();
            $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at');
            $table->foreignId('unlinked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unlinked_at')->nullable();
            $table->timestamps();

            $table->unique('active_service_point_id');
            $table->index(['table_session_id', 'unlinked_at']);
            $table->index(['service_point_id', 'unlinked_at']);
            $table->index('linked_by_user_id');
            $table->index('unlinked_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_session_service_points');
    }
};
