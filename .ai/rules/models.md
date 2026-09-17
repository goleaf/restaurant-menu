---
paths:
  - 'app/Models/{QrCode,TableSession,WaiterCall,TableSessionServicePoint}.php'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Models

## Maintain service-point uniqueness guards in model hooks
Derive nullable active_service_point_id and pending_service_point_id guards in model saving hooks from canonical status or unlink state. Preserve these hooks when changing write paths; Actions remain responsible for domain transitions.
