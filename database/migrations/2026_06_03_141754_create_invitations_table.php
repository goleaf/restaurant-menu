<?php

use App\Enums\InvitationStatus;
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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('invite_token', 80)->nullable()->unique();
            $table->string('invite_code', 32)->nullable()->unique();
            $table->dateTime('expires_at');
            $table->string('status', 32)->default(InvitationStatus::Pending->value);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['role_id', 'status']);
            $table->index('email');
            $table->index('phone');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
