---
paths:
  - 'resources/views/flux/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Flux

## Keep Flux overrides limited to tested accessibility gaps
Use installed Flux Free components and supported props/classes/slots first. Only input/viewable and toast/index are currently overridden for translated accessible names, password pressed state and toast touch size. FrontendStyleArchitectureTest pins the upstream template hashes; compare and minimize these diffs on every Flux upgrade and delete an override once upstream exposes the required API. See docs/frontend.md for the rationale.
