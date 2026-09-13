<?php

declare(strict_types=1);

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
        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('allergens_snapshot')->default('[]')->after('modifiers_snapshot');
        });

        Schema::table('kitchen_ticket_items', function (Blueprint $table): void {
            $table->json('allergens_snapshot')->default('[]')->after('selected_modifiers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_ticket_items', function (Blueprint $table): void {
            $table->dropColumn('allergens_snapshot');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('allergens_snapshot');
        });
    }
};
