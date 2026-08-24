<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable();
            $table->unique(
                ['draft_order_id', 'idempotency_key'],
                'draft_order_items_draft_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table): void {
            $table->dropUnique('draft_order_items_draft_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
