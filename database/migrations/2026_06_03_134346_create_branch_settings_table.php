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
        Schema::create('branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('require_waiter_confirmation_for_orders')->default(true);
            $table->boolean('allow_guest_created_sessions')->default(false);
            $table->boolean('allow_waiter_opened_sessions')->default(true);
            $table->boolean('allow_guest_invite_links')->default(false);
            $table->boolean('guest_join_requires_approval')->default(true);
            $table->unsignedSmallInteger('polling_interval_seconds')->default(1);
            $table->string('default_language', 10)->default('en');
            $table->string('default_currency', 3)->default('EUR');
            $table->boolean('service_charge_enabled')->default(false);
            $table->boolean('tips_enabled')->default(false);
            $table->string('order_flow_mode', 40)->default('waiter_confirmation');
            $table->timestamps();

            $table->unique('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_settings');
    }
};
