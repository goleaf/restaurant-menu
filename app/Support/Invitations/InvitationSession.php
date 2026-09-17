<?php

declare(strict_types=1);

namespace App\Support\Invitations;

use App\Actions\Invitations\ResolvedInvitationAccess;
use App\Actions\Invitations\ResolveInvitationAccessAction;
use App\Enums\InvitationAccessState;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class InvitationSession
{
    private const KEYS = ['staff_invitation_id', 'staff_invitation_state', 'staff_invitation_credential', 'staff_invitation_version', 'staff_invitation_context'];

    public function __construct(private ResolveInvitationAccessAction $resolver) {}

    public function exchange(Request $request, string $token): void
    {
        $request->session()->forget(self::KEYS);
        $access = $this->resolver->byToken($token, $this->recipient($request));
        $request->session()->put('staff_invitation_state', $access->state->sessionValue());
        $request->session()->put('staff_invitation_context', Str::random(32));

        if ($access->invitation instanceof Invitation) {
            $request->session()->put('staff_invitation_id', $access->invitation->id);
            $request->session()->put('staff_invitation_credential', hash('sha256', $token));
            $request->session()->put('staff_invitation_version', $access->invitation->credentialVersion());
        }

        if ($access->state === InvitationAccessState::Pending && $this->recipient($request) === null) {
            $request->session()->put('url.intended', route('invitations.pending'));
        }
    }

    public function resolve(Request $request): ResolvedInvitationAccess
    {
        $id = $request->session()->get('staff_invitation_id');
        $credential = $request->session()->get('staff_invitation_credential');
        if (! is_int($id)) {
            return new ResolvedInvitationAccess(InvitationAccessState::fromSession($request->session()->get('staff_invitation_state')));
        }

        $access = $this->resolver->byId($id, $this->recipient($request), is_string($credential) ? $credential : null);
        $version = $request->session()->get('staff_invitation_version');
        if ($access->invitation instanceof Invitation && (! is_string($version) || ! hash_equals($access->invitation->credentialVersion(), $version))) {
            return new ResolvedInvitationAccess(InvitationAccessState::Unavailable);
        }

        return $access;
    }

    public function context(Request $request): string
    {
        $context = $request->session()->get('staff_invitation_context');

        return is_string($context) ? $context : '';
    }

    public function audience(Request $request): string
    {
        return hash_hmac('sha256', json_encode([$this->recipient($request)?->id, $request->session()->getId()], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function assertContext(Request $request, string $context, string $audience): void
    {
        abort_unless($context !== '' && hash_equals($this->context($request), $context)
            && hash_equals($this->audience($request), $audience), 409);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([...self::KEYS, 'url.intended']);
    }

    /** @return array<string, mixed> */
    public function credential(Request $request): array
    {
        return $request->session()->only(self::KEYS);
    }

    public function recipient(Request $request): ?User
    {
        $recipient = $request->user();

        return $recipient instanceof User ? $recipient : null;
    }
}
