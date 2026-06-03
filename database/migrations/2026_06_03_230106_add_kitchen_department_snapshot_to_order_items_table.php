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
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('kitchen_department_id')
                ->nullable()
                ->after('menu_item_id')
                ->constrained('kitchen_departments')
                ->nullOnDelete();
            $table->string('kitchen_department_type', 40)->nullable()->after('kitchen_department_id');
            $table->string('kitchen_department_name', 120)->nullable()->after('kitchen_department_type');

            $table->index(['order_id', 'kitchen_department_id']);
            $table->index(['kitchen_department_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['kitchen_department_type', 'created_at']);
            $table->dropIndex(['order_id', 'kitchen_department_id']);
            $table->dropColumn(['kitchen_department_type', 'kitchen_department_name']);
            $table->dropConstrainedForeignId('kitchen_department_id');
        });
    }
};
