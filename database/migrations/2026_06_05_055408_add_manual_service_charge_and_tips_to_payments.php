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
        Schema::table('branch_settings', function (Blueprint $table) {
            $table->decimal('service_charge_percent', 5, 2)
                ->default('0.00')
                ->after('service_charge_enabled');
        });

        Schema::table('manual_payments', function (Blueprint $table) {
            $table->decimal('covered_subtotal_amount', 10, 2)
                ->default('0.00')
                ->after('payment_method');
            $table->decimal('service_charge_percent', 5, 2)
                ->default('0.00')
                ->after('covered_subtotal_amount');
            $table->decimal('service_charge_amount', 10, 2)
                ->default('0.00')
                ->after('service_charge_percent');
            $table->decimal('tips_amount', 10, 2)
                ->default('0.00')
                ->after('service_charge_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropColumn([
                'covered_subtotal_amount',
                'service_charge_percent',
                'service_charge_amount',
                'tips_amount',
            ]);
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            $table->dropColumn('service_charge_percent');
        });
    }
};
