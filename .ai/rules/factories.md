---
paths:
  - 'database/factories/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Factories

## Complete supplied model graphs and derived order totals
Factory states accepting persisted variants must loadMissing('item.menu.branch') before traversing/recycling the branch, preserving already loaded relations. Test retrieved collections and partially eager-loaded graphs under strict lazy-loading prevention, including zero reads for a complete graph. Optional order graph helpers must synchronize total_price_cents from active integer-cent line totals after creating items; preserve explicit compatible parents and existing item prices.
