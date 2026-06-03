<?php

use App\Enums\TableSessionJoinRequestStatus;
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
        Schema::create('table_session_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->string('guest_name', 80);
            $table->string('guest_token', 64)->unique();
            $table->string('status', 32)->default(TableSessionJoinRequestStatus::Pending->value);
            $table->foreignId('approved_by_guest_id')->nullable()->constrained('table_session_guests')->nullOnDelete();
            $table->foreignId('rejected_by_guest_id')->nullable()->constrained('table_session_guests')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['table_session_id', 'status', 'guest_name']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_session_join_requests');
    }
};
