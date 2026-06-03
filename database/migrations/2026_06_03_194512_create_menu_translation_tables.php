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
        Schema::create('menu_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->string('language_code', 10);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['menu_category_id', 'language_code']);
            $table->index(['language_code', 'menu_category_id']);
        });

        Schema::create('menu_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('language_code', 10);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'language_code']);
            $table->index(['language_code', 'menu_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_translations');
        Schema::dropIfExists('menu_category_translations');
    }
};
