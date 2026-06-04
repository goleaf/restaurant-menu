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
        Schema::create('organization_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('next_payment_at')->nullable()->index();
            $table->string('payment_status', 24)->default('pending')->index();
            $table->timestamps();

            $table->unique('organization_id');
            $table->index(['status', 'next_payment_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_subscriptions');
    }
};
