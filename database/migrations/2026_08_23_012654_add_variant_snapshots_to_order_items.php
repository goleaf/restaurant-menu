<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->foreignId('menu_item_variant_id')
                ->nullable()
                ->after('menu_item_id')
                ->constrained('menu_item_variants')
                ->nullOnDelete();
            $table->string('variant_name', 160)->nullable()->after('item_name');
            $table->string('variant_type', 30)->nullable()->after('variant_name');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('menu_item_variant_id')
                ->nullable()
                ->after('menu_item_id')
                ->constrained('menu_item_variants')
                ->nullOnDelete();
            $table->string('variant_name', 160)->nullable()->after('item_name');
            $table->string('variant_type', 30)->nullable()->after('variant_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_item_variant_id');
            $table->dropColumn(['variant_name', 'variant_type']);
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_item_variant_id');
            $table->dropColumn(['variant_name', 'variant_type']);
        });
    }
};
