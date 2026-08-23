<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 160);
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('volume', 8, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['menu_item_id', 'type', 'name']);
            $table->index(['menu_item_id', 'is_available', 'sort_order']);
            $table->index(['menu_item_id', 'type', 'sort_order']);
            $table->index(['menu_item_id', 'is_default']);
        });

        Schema::create('menu_item_variant_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_variant_id')->constrained('menu_item_variants')->cascadeOnDelete();
            $table->string('language_code', 10);
            $table->string('name', 160);
            $table->timestamps();

            $table->unique(['menu_item_variant_id', 'language_code']);
            $table->index(['language_code', 'menu_item_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_variant_translations');
        Schema::dropIfExists('menu_item_variants');
    }
};
