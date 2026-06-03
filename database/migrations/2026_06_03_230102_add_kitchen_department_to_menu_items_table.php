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
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('kitchen_department_id')
                ->nullable()
                ->after('category_id')
                ->constrained('kitchen_departments')
                ->nullOnDelete();

            $table->index(['kitchen_department_id', 'is_available', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['kitchen_department_id', 'is_available', 'sort_order']);
            $table->dropConstrainedForeignId('kitchen_department_id');
        });
    }
};
