<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Shared-hosting notes

The canonical release contract is [`deployment.md`](deployment.md), with runtime operations in [`operations.md`](operations.md).

Point the public document root at `public/`; keep `.env`, SQLite and private files outside public access; ensure `storage` and `bootstrap/cache` are writable; build assets before release; run only forward production migrations; cache configuration/routes/views; and verify `/up`, login and a public QR page. Core workflows do not require workers, cron, supervisor, Redis, S3, WebSockets, Docker or SSH. If the host cannot create `public/storage`, configure the equivalent safe symlink/path without exposing private disks.
