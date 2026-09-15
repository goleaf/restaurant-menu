<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Invitations\AcceptInvitationAction;
use App\Actions\Invitations\CancelInvitationAction;
use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Invitations\ReissueInvitationAction;
use App\Actions\Staff\SetOrganizationStaffStatusAction;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TeamInvitationConcurrencyTasks
{
    /** @param array<string,mixed> $connection */
    public static function create(array $connection, int $organizationId, int $roleId, int $actorId): Closure
    {
        return static function () use ($connection, $organizationId, $roleId, $actorId): array {
            config(['database.default' => 'invitation_concurrency', 'database.connections.invitation_concurrency' => $connection]);
            DB::purge('invitation_concurrency');
            self::awaitPeer((string) $connection['database']);
            try {
                app(CreateInvitationAction::class)->handle(Organization::findOrFail($organizationId), Role::findOrFail($roleId), User::findOrFail($actorId), ['email' => 'concurrent@example.test']);

                return ['result' => 'created', 'pid' => getmypid()];
            } catch (ValidationException) {
                return ['result' => 'duplicate', 'pid' => getmypid()];
            }
        };
    }

    /** @param array<string,mixed> $connection */
    public static function mutate(array $connection, int $invitationId, int $actorId, int $recipientId, string $operation, string $digest, string $version): Closure
    {
        return static function () use ($connection, $invitationId, $actorId, $recipientId, $operation, $digest, $version): array {
            config(['database.default' => 'invitation_concurrency', 'database.connections.invitation_concurrency' => $connection]);
            DB::purge('invitation_concurrency');
            $invitation = Invitation::findOrFail($invitationId);
            $invitation->invite_token_hash = $digest;
            self::awaitPeer((string) $connection['database']);
            try {
                if ($operation === 'accept') {
                    app(AcceptInvitationAction::class)->handle($invitation, User::findOrFail($recipientId));
                } elseif ($operation === 'cancel') {
                    app(CancelInvitationAction::class)->handle(User::findOrFail($actorId), Organization::findOrFail($invitation->organization_id), $invitation);
                } elseif ($operation === 'suspend') {
                    $membership = OrganizationUser::query()->where('organization_id', $invitation->organization_id)->where('user_id', $recipientId)->firstOrFail();
                    app(SetOrganizationStaffStatusAction::class)->suspend($membership, User::findOrFail($actorId), 'Concurrent access suspension.', expectedVersion: 0);
                } else {
                    app(ReissueInvitationAction::class)->handle(User::findOrFail($actorId), Organization::findOrFail($invitation->organization_id), $invitation, $version);
                }

                return ['result' => $operation, 'pid' => getmypid()];
            } catch (DomainException|ValidationException) {
                return ['result' => 'conflict', 'pid' => getmypid()];
            }
        };
    }

    private static function awaitPeer(string $databasePath): void
    {
        $pattern = $databasePath.'.ready.*';
        file_put_contents($databasePath.'.ready.'.getmypid(), 'ready');
        $deadline = microtime(true) + 5;
        while (count(glob($pattern)) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (count(glob($pattern)) !== 2) {
            throw new \RuntimeException('Invitation workers failed to rendezvous.');
        }
    }
}
