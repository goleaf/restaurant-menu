---
paths:
  - 'app/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# App

## Transferred table session QR restoration
Ordinary QR entry is limited to the current service point or an active merged link. After a table-session transfer, only an already known guest, pending-join, or active-invite credential may restore from a previous permanent QR, and only when the session transfer history proves that origin. Never make the previous QR a general entry path; terminal sessions still fail closed.

## Fingerprint the exact authorized polling snapshot
Waiter detail fingerprints must hash the exact prepared section payload returned from the same coherent read transaction. Count/MAX(updated_at) summaries miss same-second changes, older-record edits and delete/add replacements; a separate later fingerprint read can acknowledge content the client never received. Reauthorize each poll, prepare only the requested section and batch capabilities within that request; measure queries and hydration together.

## Keep the restore barrier outside the restored database
SQLite restore coordination starts before authentication/session reads: ordinary HTTP requests hold a shared lock through final session persistence, while the restore endpoint acquires an exclusive lock before authentication. Keep the independent blocked marker and maintenance mode on uncertain restore/rollback or invalidation failure. Revoke all supported persistent sessions, clear used cache stores and prevent the restore response from rewriting old credentials. The HTTP barrier does not quiesce CLI writers; follow the operator procedure.
