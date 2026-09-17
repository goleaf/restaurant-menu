---
paths:
  - 'tests/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tests

## Keep formatting scoped in a dirty shared checkout
Pint 1.31.1 --dirty selects every dirty PHP file even when explicit file paths are also supplied. In the shared checkout run Pint with explicit owned file paths without --dirty; run required --dirty/full-format verification only in an owned disposable snapshot, then inspect the diff. Never retain formatter changes to another task's files.
