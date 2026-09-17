---
paths:
  - 'app/Mcp/Tools/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Tools

## Extend the shared restaurant MCP tool boundaries
Build restaurant read tools on RestaurantReadTool and mutations on RestaurantMutationTool, preserving credential-derived scope, strict arguments and delegated domain Actions. Keep mutation confirmation, idempotency and current authorization checks inside the shared execution boundary.
