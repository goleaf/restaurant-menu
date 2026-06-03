<?php

use App\Enums\OrderStatus;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draft_order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40)->default(OrderStatus::ConfirmedByWaiter->value);
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('draft_order_id');
            $table->index(['branch_id', 'status', 'confirmed_at']);
            $table->index(['table_session_id', 'status']);
            $table->index(['service_point_id', 'status']);
            $table->index('confirmed_by_user_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 160)->nullable();
            $table->string('item_name', 180);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('modifier_total', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->json('selected_modifiers')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'table_session_guest_id']);
            $table->index('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
