<?php

declare(strict_types=1);

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\AuditLogAction;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\TeamInvitationConcurrencyTasks;

test('independent sqlite writers converge on one invitation lifecycle winner', function (string $competingOperation): void {
    $path = tempnam(sys_get_temp_dir(), 'restaurant-invite-concurrency-');
    $original = config('database.default');
    $connection = config('database.connections.sqlite');
    $connection['database'] = $path;
    try {
        config(['database.default' => 'invitation_concurrency', 'database.connections.invitation_concurrency' => $connection]);
        DB::purge('invitation_concurrency');
        expect($connection['transaction_mode'])->toBe('IMMEDIATE');
        expect(Artisan::call('migrate', ['--database' => 'invitation_concurrency', '--force' => true]))->toBe(0);
        $this->seed(SystemPermissionsSeeder::class);
        $owner = User::factory()->create();
        $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Concurrent Invitation Group']);
        $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
        $recipient = User::factory()->create(['email' => 'concurrent@example.test']);
        if ($competingOperation === 'suspend') {
            OrganizationUser::factory()->create(['organization_id' => $organization->id, 'user_id' => $recipient->id, 'role_id' => $role->id, 'status' => OrganizationUserStatus::Active]);
        }
        if ($competingOperation === 'create') {
            $tasks = [TeamInvitationConcurrencyTasks::create($connection, $organization->id, $role->id, $owner->id), TeamInvitationConcurrencyTasks::create($connection, $organization->id, $role->id, $owner->id)];
        } else {
            $created = app(CreateInvitationAction::class)->handle($organization, $role, $owner->fresh(), ['email' => $recipient->email]);
            $invitation = $created->invitation;
            $tasks = [TeamInvitationConcurrencyTasks::mutate($connection, $invitation->id, $owner->id, $recipient->id, 'accept', $invitation->invite_token_hash, $invitation->credentialVersion()), TeamInvitationConcurrencyTasks::mutate($connection, $invitation->id, $owner->id, $recipient->id, $competingOperation, $invitation->invite_token_hash, $invitation->credentialVersion())];
        }
        config(['database.default' => $original]);
        $results = Concurrency::driver('process')->run($tasks, 20);
        expect(array_unique(array_column($results, 'pid')))->toHaveCount(2);
        config(['database.default' => 'invitation_concurrency']);
        DB::purge('invitation_concurrency');
        $invitation = Invitation::query()->sole();
        $accepted = $invitation->status === InvitationStatus::Accepted;
        expect(OrganizationUser::query()->where('user_id', $recipient->id)->count())->toBe($accepted || $competingOperation === 'suspend' ? 1 : 0)
            ->and(AuditLog::query()->where('action', AuditLogAction::InvitationAccepted->value)->count())->toBe($accepted ? 1 : 0);
        $states = array_column($results, 'result');
        if ($competingOperation === 'create') {
            sort($states);
            expect($states)->toBe(['created', 'duplicate']);
        } elseif ($competingOperation === 'accept') {
            expect($states)->toBe(['accept', 'accept']);
        } elseif ($competingOperation === 'suspend') {
            expect($states)->toContain('suspend')
                ->and(OrganizationUser::query()->where('user_id', $recipient->id)->sole()->status)->toBe(OrganizationUserStatus::Suspended)
                ->and($invitation->status)->toBeIn([InvitationStatus::Accepted, InvitationStatus::Cancelled]);
        } else {
            expect($states)->toContain('conflict')->not->toBe(['conflict', 'conflict']);
        }
    } finally {
        config(['database.default' => $original]);
        DB::disconnect('invitation_concurrency');
        DB::purge('invitation_concurrency');
        File::delete([$path, $path.'-wal', $path.'-shm']);
        File::delete(glob($path.'.ready.*'));
    }
})->with(['create', 'accept', 'cancel', 'reissue', 'suspend']);
