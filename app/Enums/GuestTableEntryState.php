<?php

namespace App\Enums;

enum GuestTableEntryState: string
{
    case PendingSessionCreated = 'pending_session_created';
    case ActiveSessionExists = 'active_session_exists';
    case PendingSessionExists = 'pending_session_exists';
    case JoinRequestCreated = 'join_request_created';
    case GuestCreatedSessionsDisabled = 'guest_created_sessions_disabled';
}
