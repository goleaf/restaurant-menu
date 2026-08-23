# Operations

## Health and diagnostics

`/up` is the no-cache production readiness endpoint. It returns `200` only after the application boots and verifies the migrated SQLite schema, a write/read/delete cycle on the default cache, a write/read/delete cycle on private local storage and a write/read/delete cycle in the log directory. Dependency failures return a generic `500`; the response never exposes the failing path, credentials or exception message. Disable an individual check only when the deployment intentionally does not use that dependency and the exception is documented through the corresponding `HEALTH_CHECK_*` key.

Every HTTP response includes an `X-Request-Id` UUID. Logs use the same `request_id`, method, route name and route template; raw paths are excluded because QR and invitation routes can carry credentials. Production defaults to the daily log channel with 14-day retention, a separate daily deprecation log and recursive redaction of password, token, authorization, cookie, authentication-session, credential, private-key and payment-card context fields. Domain identifiers such as `table_session_id` remain available for diagnostics. Inspect errors with `php artisan pail --level=error`; never copy secrets or complete request payloads into incident notes.

Unexpected reportable production exceptions may send an immediate on-demand mail alert without a queue worker. Set `ERROR_NOTIFICATIONS_ENABLED=true`, a valid `ERROR_NOTIFICATION_EMAIL`, an available `ERROR_NOTIFICATION_CACHE_STORE` and the cooldown in seconds. Identical exception class/file/line/route fingerprints are notified once per cooldown. The email intentionally contains no exception message, stack trace, raw URL, user data or request body; correlate it to the retained log with its request and incident IDs. A delivery failure does not replace the original exception and emits only a sanitized `production_error_notification_failed` log event.

## Backups

Backups must be consistent SQLite snapshots that include committed data under WAL/concurrent use, written to a private temporary location, downloaded only after superadmin authorization, audited and deleted after response/failure. Operators separately back up local uploaded files. A backup is not accepted until restoration is tested in an isolated environment.

Production restore is available from the superadmin backup panel only after recent password confirmation, a reason and exact `RESTORE` confirmation. Use a snapshot produced by the same deployed schema. The server validates the SQLite header and exact schema, serializes the operation with a filesystem lock, enters maintenance mode, stores a private pre-restore safety snapshot, replaces the database through SQLite's online backup API and records the restore in the restored audit log. Corrupt or incompatible files leave live data unchanged; a failure after replacement automatically replays the safety snapshot. A successful restore clears cache and remember tokens and invalidates every session, so all users must sign in again. Retain the generated safety snapshot until the restored application, critical data and local media references have been verified.

## Maintenance work

Required workflows do not rely on indefinite HTTP requests. Maintenance affecting many rows is bounded, idempotent, lock-protected and, when it can span requests, persists cursor/progress/status/errors/checkpoints and supports safe resume. Production seeders never truncate data. Cache clearing is an authorized dangerous action and is not a substitute for scoped invalidation.

## Incident handling

Capture time, named route/operation, `X-Request-Id`, incident ID, safe user/organization/branch IDs, release commit and non-sensitive exception class. Start with `/up`, then use the request ID in retained logs. Classify validation/domain conflict/authorization/external/infrastructure/programming failure. Restore service with the least destructive reversible action, preserve evidence, verify the critical path and add a regression test for first-party defects.
