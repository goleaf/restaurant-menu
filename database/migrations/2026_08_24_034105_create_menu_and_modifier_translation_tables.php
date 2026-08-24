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
        Schema::create('menu_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 5);
            $table->string('name', 160);
            $table->timestamps();

            $table->unique(['menu_id', 'language_code']);
        });

        Schema::create('modifier_group_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 5);
            $table->string('name', 160);
            $table->timestamps();

            $table->unique(['modifier_group_id', 'language_code']);
        });

        Schema::create('modifier_option_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_option_id')->constrained()->cascadeOnDelete();
            $table->string('language_code', 5);
            $table->string('name', 160);
            $table->timestamps();

            $table->unique(['modifier_option_id', 'language_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_option_translations');
        Schema::dropIfExists('modifier_group_translations');
        Schema::dropIfExists('menu_translations');
    }
};
