<?php

use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
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
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('opened_by_guest_id')->nullable();
            $table->string('status', 40)->default(TableSessionStatus::Pending->value);
            $table->string('source', 40)->default(TableSessionSource::GuestCreated->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['service_point_id', 'status']);
            $table->index(['branch_id', 'service_point_id', 'status']);
            $table->index(['source', 'status']);
            $table->index('opened_by_guest_id');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
