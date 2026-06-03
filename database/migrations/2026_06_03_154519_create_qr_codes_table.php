<?php

use App\Enums\QrCodeStatus;
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
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_service_point_id')->nullable()->constrained('service_points')->cascadeOnDelete();
            $table->string('public_token', 96)->unique();
            $table->string('short_code', 24)->unique();
            $table->string('status', 24)->default(QrCodeStatus::Active->value);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('active_service_point_id');
            $table->index(['service_point_id', 'status']);
            $table->index(['status', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
