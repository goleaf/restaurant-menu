# Operations

## Health and diagnostics

`/up` is the basic application health endpoint. Operational checks also verify SQLite readability/writability, cache/session operation, public asset availability and a safe representative authenticated/public render. Recent Laravel and browser logs are reviewed; secrets and full sensitive payloads are never copied into incident notes.

## Backups

Backups must be consistent SQLite snapshots that include committed data under WAL/concurrent use, written to a private temporary location, downloaded only after superadmin authorization, audited and deleted after response/failure. Operators separately back up local uploaded files. A backup is not accepted until restoration is tested in an isolated environment.

## Maintenance work

Required workflows do not rely on indefinite HTTP requests. Maintenance affecting many rows is bounded, idempotent, lock-protected and, when it can span requests, persists cursor/progress/status/errors/checkpoints and supports safe resume. Production seeders never truncate data. Cache clearing is an authorized dangerous action and is not a substitute for scoped invalidation.

## Incident handling

Capture time, route/operation, correlation/request ID, safe user/organization/branch IDs, release commit and non-sensitive exception class. Classify validation/domain conflict/authorization/external/infrastructure/programming failure. Restore service with the least destructive reversible action, preserve evidence, verify the critical path and add a regression test for first-party defects.
