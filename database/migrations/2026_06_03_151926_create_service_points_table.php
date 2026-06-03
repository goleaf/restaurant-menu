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
        Schema::create('service_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_node_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('name', 160);
            $table->string('display_number', 80)->nullable();
            $table->string('internal_code', 120)->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->string('icon', 80)->nullable();
            $table->string('status', 40)->default('available');
            $table->decimal('position_x', 8, 2)->nullable();
            $table->decimal('position_y', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'internal_code']);
            $table->index(['branch_id', 'area_node_id']);
            $table->index(['branch_id', 'type', 'is_active']);
            $table->index(['branch_id', 'status', 'is_active']);
            $table->index(['branch_id', 'display_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_points');
    }
};
