<?php

namespace App\Enums;

enum GuestTableEntryState: string
{
    case PendingSessionCreated = 'pending_session_created';
    case ActiveSessionJoined = 'active_session_joined';
    case ActiveSessionExists = 'active_session_exists';
    case PendingSessionExists = 'pending_session_exists';
    case JoinRequestCreated = 'join_request_created';
    case GuestCreatedSessionsDisabled = 'guest_created_sessions_disabled';
    case ServicePointUnavailable = 'service_point_unavailable';
}
