<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('menu_id')->index()->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('phase', 32)->default('preparing');
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->boolean('source_changed')->default(false);
            $table->foreignId('result_id')->nullable()->index()->constrained('menu_items')->restrictOnDelete();
            $table->string('active_scope')->nullable()->unique();
            $table->json('pending_cleanup')->default('[]');
            $table->json('payload')->default('[]');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'completed_at', 'id']);
            $table->index(['kind', 'target_id']);
        });

        Schema::create('menu_operation_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_operation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('scan_cursor')->default(0);
            $table->boolean('discovered')->default(false);
            $table->timestamps();
            $table->unique(['menu_operation_id', 'menu_category_id'], 'menu_operation_category_unique');
            $table->index(['menu_operation_id', 'discovered', 'id'], 'menu_operation_discovery_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_operation_categories');
        Schema::dropIfExists('menu_operations');
    }
};
