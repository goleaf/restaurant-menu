<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# ADR 0001: Shared-hosting runtime baseline

- Status: accepted
- Date: 2026-08-22

## Decision

Core operation uses PHP 8.5, Laravel 13, SQLite, local files and database cache/session/queue. Required workflows do not depend on queue workers, cron, supervisor, Redis, S3, WebSockets, Docker, SSH or long-running processes.

## Rationale and consequences

This matches the repository's existing deployment intent and keeps the product deployable on ordinary shared hosting. Long maintenance work must therefore be bounded, idempotent and resumable through web requests. SQLite writes require short transactions and deliberate serialization for race-sensitive money/token/session operations. Optional infrastructure may be added only by a new requirement/ADR with deployment and rollback ownership.
