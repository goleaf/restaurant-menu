<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const FOREIGN_KEY_INDEXES = [
        'area_node_waiters' => ['assigned_by_user_id', 'user_id'],
        'area_nodes' => ['parent_id'],
        'audit_logs' => ['guest_id', 'user_id'],
        'branch_users' => ['assigned_by_user_id'],
        'draft_order_items' => ['menu_item_variant_id'],
        'draft_orders' => ['converted_by_user_id', 'rejected_by_user_id'],
        'invitations' => ['accepted_by_user_id', 'invited_by_user_id'],
        'kitchen_ticket_items' => ['menu_item_id', 'served_by_user_id'],
        'kitchen_tickets' => ['sent_by_user_id', 'table_session_id'],
        'manual_payments' => ['recorded_by_user_id', 'service_point_id'],
        'menu_categories' => ['parent_id'],
        'order_items' => ['cancelled_by_user_id', 'kitchen_department_id', 'menu_item_variant_id', 'table_session_guest_id'],
        'order_status_logs' => ['actor_guest_id', 'actor_user_id', 'service_point_id'],
        'organization_users' => ['invited_by_user_id', 'role_id'],
        'qr_codes' => ['created_by_user_id', 'revoked_by_user_id'],
        'service_points' => ['area_node_id'],
        'table_session_join_requests' => ['approved_by_guest_id', 'approved_by_user_id', 'rejected_by_guest_id', 'rejected_by_user_id'],
        'table_sessions' => ['closed_by_user_id', 'guest_invite_created_by_guest_id', 'opened_by_user_id'],
        'waiter_calls' => ['handled_by_user_id', 'requested_by_guest_id'],
    ];

    /** @var array<string, array<string, list<string>>> */
    private const REDUNDANT_INDEXES = [
        'audit_logs' => [
            'audit_logs_branch_id_created_at_index' => ['branch_id', 'created_at'],
            'audit_logs_organization_id_created_at_index' => ['organization_id', 'created_at'],
        ],
        'brands' => [
            'brands_organization_id_name_index' => ['organization_id', 'name'],
        ],
        'kitchen_ticket_items' => [
            'kitchen_ticket_items_status_served_at_index' => ['status', 'served_at'],
        ],
        'kitchen_tickets' => [
            'kitchen_tickets_kitchen_department_id_status_index' => ['kitchen_department_id', 'status'],
        ],
        'notifications' => [
            'notifications_notifiable_type_notifiable_id_index' => ['notifiable_type', 'notifiable_id'],
        ],
        'organization_subscriptions' => [
            'organization_subscriptions_status_index' => ['status'],
        ],
        'table_sessions' => [
            'table_sessions_branch_id_status_index' => ['branch_id', 'status'],
        ],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEY_INDEXES as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->index($column);
                }
            });
        }

        foreach (self::REDUNDANT_INDEXES as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach (array_keys($indexes) as $index) {
                    $table->dropIndex($index);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::REDUNDANT_INDEXES as $tableName => $indexes) {
            Schema::table($tableName, function (Blueprint $table) use ($indexes): void {
                foreach ($indexes as $index => $columns) {
                    $table->index($columns, $index);
                }
            });
        }

        foreach (array_reverse(self::FOREIGN_KEY_INDEXES, true) as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropIndex([$column]);
                }
            });
        }
    }
};
