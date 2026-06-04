<?php

use App\Actions\AuditLogs\BuildAuditLogIndexAction;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('sqlite polling and dashboard guardrail indexes exist', function () {
    expect(prompt83IndexColumns('notifications', 'notifications_notifiable_read_created_idx'))->toBe([
        'notifiable_type',
        'notifiable_id',
        'read_at',
        'created_at',
    ])
        ->and(prompt83IndexColumns('notifications', 'notifications_notifiable_type_read_idx'))->toBe([
            'notifiable_type',
            'notifiable_id',
            'type',
            'read_at',
            'created_at',
        ])
        ->and(prompt83IndexColumns('service_points', 'service_points_branch_name_idx'))->toBe([
            'branch_id',
            'name',
            'id',
        ])
        ->and(prompt83IndexColumns('table_sessions', 'table_sessions_branch_status_started_idx'))->toBe([
            'branch_id',
            'status',
            'started_at',
            'id',
        ])
        ->and(prompt83IndexColumns('draft_orders', 'draft_orders_session_latest_idx'))->toBe([
            'table_session_id',
            'id',
        ])
        ->and(prompt83IndexColumns('kitchen_ticket_items', 'ticket_items_status_served_ticket_idx'))->toBe([
            'status',
            'served_at',
            'kitchen_ticket_id',
        ])
        ->and(prompt83IndexColumns('audit_logs', 'audit_logs_branch_created_id_idx'))->toBe([
            'branch_id',
            'created_at',
            'id',
        ]);
});

test('audit log index returns a paginated history page', function () {
    $superadmin = prompt83SuperadminUser();

    AuditLog::factory()
        ->count(12)
        ->sequence(fn (Sequence $sequence): array => [
            'organization_id' => null,
            'branch_id' => null,
            'user_id' => $superadmin->id,
            'entity_id' => $sequence->index + 1,
            'created_at' => now()->subMinutes(12 - $sequence->index),
        ])
        ->create();

    $payload = app(BuildAuditLogIndexAction::class)->handle($superadmin, 10);

    expect($payload['logs'])->toBeInstanceOf(CursorPaginator::class)
        ->and($payload['logs'])->toHaveCount(10)
        ->and($payload['logs']->hasMorePages())->toBeTrue();
});

/**
 * @return list<string>
 */
function prompt83IndexColumns(string $table, string $indexName): array
{
    $index = collect(Schema::getIndexes($table))
        ->firstWhere('name', $indexName);

    return is_array($index) ? $index['columns'] : [];
}

function prompt83SuperadminUser(): User
{
    $user = User::factory()->create();
    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    return $user;
}
