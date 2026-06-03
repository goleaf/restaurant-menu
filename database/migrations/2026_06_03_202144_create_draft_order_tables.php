<?php

use App\Enums\DraftOrderStatus;
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
        Schema::create('draft_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default(DraftOrderStatus::Draft->value);
            $table->timestamp('sent_to_waiter_at')->nullable();
            $table->foreignId('sent_by_guest_id')->nullable()->constrained('table_session_guests')->nullOnDelete();
            $table->timestamps();

            $table->unique('table_session_id');
            $table->index(['status', 'sent_to_waiter_at']);
            $table->index('sent_by_guest_id');
        });

        Schema::create('draft_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name', 180);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('modifier_total', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->json('selected_modifiers')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['draft_order_id', 'table_session_guest_id']);
            $table->index(['table_session_guest_id', 'created_at']);
            $table->index('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_order_items');
        Schema::dropIfExists('draft_orders');
    }
};
