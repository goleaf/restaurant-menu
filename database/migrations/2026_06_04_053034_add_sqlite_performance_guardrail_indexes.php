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
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at', 'created_at'], 'notifications_notifiable_read_created_idx');
            $table->index(['notifiable_type', 'notifiable_id', 'type', 'read_at', 'created_at'], 'notifications_notifiable_type_read_idx');
        });

        Schema::table('service_points', function (Blueprint $table): void {
            $table->index(['branch_id', 'name', 'id'], 'service_points_branch_name_idx');
        });

        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->index(['branch_id', 'status', 'started_at', 'id'], 'table_sessions_branch_status_started_idx');
        });

        Schema::table('table_session_join_requests', function (Blueprint $table): void {
            $table->index(['table_session_id', 'status', 'expires_at', 'created_at'], 'join_requests_session_status_expires_idx');
        });

        Schema::table('draft_orders', function (Blueprint $table): void {
            $table->index(['table_session_id', 'id'], 'draft_orders_session_latest_idx');
            $table->index(['table_session_id', 'status', 'sent_to_waiter_at'], 'draft_orders_session_status_sent_idx');
        });

        Schema::table('draft_order_items', function (Blueprint $table): void {
            $table->index(['draft_order_id', 'created_at', 'id'], 'draft_order_items_order_created_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['branch_id', 'confirmed_at', 'id'], 'orders_branch_confirmed_idx');
            $table->index(['table_session_id', 'created_at', 'id'], 'orders_session_created_idx');
        });

        Schema::table('kitchen_tickets', function (Blueprint $table): void {
            $table->index(['kitchen_department_id', 'status', 'sent_at', 'id'], 'kitchen_tickets_dept_status_sent_idx');
        });

        Schema::table('kitchen_ticket_items', function (Blueprint $table): void {
            $table->index(['status', 'served_at', 'kitchen_ticket_id'], 'ticket_items_status_served_ticket_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['organization_id', 'created_at', 'id'], 'audit_logs_org_created_id_idx');
            $table->index(['branch_id', 'created_at', 'id'], 'audit_logs_branch_created_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_branch_created_id_idx');
            $table->dropIndex('audit_logs_org_created_id_idx');
        });

        Schema::table('kitchen_ticket_items', function (Blueprint $table): void {
            $table->dropIndex('ticket_items_status_served_ticket_idx');
        });

        Schema::table('kitchen_tickets', function (Blueprint $table): void {
            $table->dropIndex('kitchen_tickets_dept_status_sent_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_session_created_idx');
            $table->dropIndex('orders_branch_confirmed_idx');
        });

        Schema::table('draft_order_items', function (Blueprint $table): void {
            $table->dropIndex('draft_order_items_order_created_idx');
        });

        Schema::table('draft_orders', function (Blueprint $table): void {
            $table->dropIndex('draft_orders_session_status_sent_idx');
            $table->dropIndex('draft_orders_session_latest_idx');
        });

        Schema::table('table_session_join_requests', function (Blueprint $table): void {
            $table->dropIndex('join_requests_session_status_expires_idx');
        });

        Schema::table('table_sessions', function (Blueprint $table): void {
            $table->dropIndex('table_sessions_branch_status_started_idx');
        });

        Schema::table('service_points', function (Blueprint $table): void {
            $table->dropIndex('service_points_branch_name_idx');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_type_read_idx');
            $table->dropIndex('notifications_notifiable_read_created_idx');
        });
    }
};
