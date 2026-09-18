<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Requirements quality

- [x] User outcomes and independent scenarios defined; scope excludes floorplan/booking/payment redesign.
- [x] Canonical requirement IDs and authoritative ledger linked.
- [x] Current implementations distinguished from source-backed gaps and unproven hypotheses.
- [x] Actor/tenant/permissions, persistence/replay, file failure and active-service boundaries specified.
- [x] Selection limits, exact-area semantics and physical print identity are explicit.
- [x] Mobile/localization/accessibility and observed performance/runtime evidence defined.
- [x] No unresolved product decision requires a question; reversible technical choices authorized.
- [x] Constitution reviewed; no new branch/hooks/GitHub lookup/workflow/working DB mutation.
