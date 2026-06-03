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
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('sent_by_guest_id');
            $table->foreignId('rejected_by_user_id')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by_user_id');
            $table->timestamp('converted_to_order_at')->nullable()->after('rejection_reason');
            $table->foreignId('converted_by_user_id')->nullable()->after('converted_to_order_at')->constrained('users')->nullOnDelete();

            $table->index(['status', 'rejected_at']);
            $table->index(['status', 'converted_to_order_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'converted_to_order_at']);
            $table->dropIndex(['status', 'rejected_at']);
            $table->dropConstrainedForeignId('converted_by_user_id');
            $table->dropColumn('converted_to_order_at');
            $table->dropColumn('rejection_reason');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn('rejected_at');
        });
    }
};
