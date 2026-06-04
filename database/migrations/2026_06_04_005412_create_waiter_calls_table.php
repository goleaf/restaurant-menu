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
        Schema::create('waiter_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_service_point_id')->nullable()->constrained('service_points')->cascadeOnDelete();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_guest_id')->nullable()->constrained('table_session_guests')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('active_service_point_id');
            $table->index(['branch_id', 'status', 'requested_at']);
            $table->index(['service_point_id', 'status']);
            $table->index(['table_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waiter_calls');
    }
};
