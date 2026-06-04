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
        Schema::table('kitchen_ticket_items', function (Blueprint $table) {
            $table->timestamp('served_at')->nullable()->after('status');
            $table->foreignId('served_by_user_id')->nullable()->after('served_at')->constrained('users')->nullOnDelete();
            $table->index(['status', 'served_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_ticket_items', function (Blueprint $table) {
            $table->dropIndex('kitchen_ticket_items_status_served_at_index');
            $table->dropConstrainedForeignId('served_by_user_id');
            $table->dropColumn('served_at');
        });
    }
};
