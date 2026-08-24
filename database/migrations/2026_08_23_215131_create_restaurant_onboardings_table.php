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
        Schema::create('restaurant_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('area_node_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('menu_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('menu_category_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_onboardings');
    }
};
