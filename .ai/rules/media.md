---
paths:
  - 'app/Actions/Media/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Media

## Never let rollback media cleanup interrupt transaction unwinding
Rollback callbacks use DeleteRolledBackLocalImageAction: attempt all variants, contain both deletion and logging failures, and preserve the original persistence exception. A throwing rollback callback can leave committed nested callbacks attached to the next transaction and delete a still-referenced original. Ordinary deletion and after-commit cleanup remain throwing so durable menu-operation retries still detect failures.
