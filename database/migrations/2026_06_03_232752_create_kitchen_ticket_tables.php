<?php

use App\Enums\KitchenTicketStatus;
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
        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department_type', 40);
            $table->string('department_name', 120);
            $table->string('status', 40)->default(KitchenTicketStatus::Sent->value);
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'department_type', 'department_name']);
            $table->index(['branch_id', 'status', 'sent_at']);
            $table->index(['kitchen_department_id', 'status']);
            $table->index(['service_point_id', 'status']);
        });

        Schema::create('kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name', 160)->nullable();
            $table->string('item_name', 180);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->json('selected_modifiers')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique('order_item_id');
            $table->index(['kitchen_ticket_id', 'created_at']);
            $table->index(['table_session_guest_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_ticket_items');
        Schema::dropIfExists('kitchen_tickets');
    }
};
