# ADR 0001: Shared-hosting runtime baseline

- Status: accepted
- Date: 2026-08-22

## Decision

Core operation uses PHP 8.5, Laravel 13, SQLite, local files and database cache/session/queue. Required workflows do not depend on queue workers, cron, supervisor, Redis, S3, WebSockets, Docker, SSH or long-running processes.

## Rationale and consequences

This matches the repository's existing deployment intent and keeps the product deployable on ordinary shared hosting. Long maintenance work must therefore be bounded, idempotent and resumable through web requests. SQLite writes require short transactions and deliberate serialization for race-sensitive money/token/session operations. Optional infrastructure may be added only by a new requirement/ADR with deployment and rollback ownership.
