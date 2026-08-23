<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            $this->addCentsColumns();
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            $this->dropCentsColumns();
        });
    }

    private function addCentsColumns(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_cents')->nullable()->after('price');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->bigInteger('price_delta_cents')->nullable()->after('price_delta');
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_cents')->nullable()->after('unit_price');
            $table->bigInteger('modifier_total_cents')->nullable()->after('modifier_total');
            $table->unsignedBigInteger('total_price_cents')->nullable()->after('total_price');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('total_price_cents')->nullable()->after('total_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_price_cents')->nullable()->after('unit_price');
            $table->unsignedBigInteger('unit_price_snapshot_cents')->nullable()->after('unit_price_snapshot');
            $table->bigInteger('modifier_total_cents')->nullable()->after('modifier_total');
            $table->unsignedBigInteger('total_price_cents')->nullable()->after('total_price');
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('service_charge_basis_points')->nullable()->after('service_charge_percent');
        });

        Schema::table('manual_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('covered_subtotal_cents')->nullable()->after('covered_subtotal_amount');
            $table->unsignedSmallInteger('service_charge_basis_points')->nullable()->after('service_charge_percent');
            $table->unsignedBigInteger('service_charge_cents')->nullable()->after('service_charge_amount');
            $table->unsignedBigInteger('tips_cents')->nullable()->after('tips_amount');
            $table->unsignedBigInteger('amount_cents')->nullable()->after('amount');
        });
    }

    private function dropCentsColumns(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropColumn([
                'covered_subtotal_cents',
                'service_charge_basis_points',
                'service_charge_cents',
                'tips_cents',
                'amount_cents',
            ]);
        });

        Schema::table('branch_settings', function (Blueprint $table) {
            $table->dropColumn('service_charge_basis_points');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_price_cents',
                'unit_price_snapshot_cents',
                'modifier_total_cents',
                'total_price_cents',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_price_cents');
        });

        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_price_cents', 'modifier_total_cents', 'total_price_cents']);
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropColumn('price_delta_cents');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('price_cents');
        });
    }
};
