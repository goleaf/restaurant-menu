<?php

use App\Enums\KitchenTicketItemStatus;
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
        Schema::table('kitchen_ticket_items', function (Blueprint $table) {
            $table->string('status', 40)
                ->default(KitchenTicketItemStatus::New->value)
                ->after('quantity');

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_ticket_items', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn('status');
        });
    }
};
