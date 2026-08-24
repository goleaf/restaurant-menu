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
        Schema::create('restaurant_onboarding_service_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['restaurant_onboarding_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_onboarding_service_points');
    }
};
