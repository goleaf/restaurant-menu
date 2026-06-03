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
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_point_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('table_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('draft_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_guest_id')->nullable()->constrained('table_session_guests')->nullOnDelete();
            $table->string('actor_type', 20)->nullable();
            $table->string('actor_name', 160)->nullable();
            $table->string('event', 60);
            $table->string('status_type', 40)->nullable();
            $table->string('previous_status', 60)->nullable();
            $table->string('new_status', 60)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['order_id', 'occurred_at']);
            $table->index(['draft_order_id', 'occurred_at']);
            $table->index(['branch_id', 'occurred_at']);
            $table->index(['table_session_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
            $table->index(['new_status', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};
