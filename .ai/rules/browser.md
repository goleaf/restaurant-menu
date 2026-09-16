---
paths:
  - 'tests/Browser/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Browser

## Reset injected authentication for real multi-request browser sessions
Real identity browser tests use IsolatedBrowserIdentity with owned file sessions. Pest reuses its Laravel container; after resetting Auth guards and the session driver, flush the matched Route controller so Fortify does not retain a previous request's injected StatefulGuard. Keep the normal middleware chain; do not fake login or disable security to bypass session failures.
