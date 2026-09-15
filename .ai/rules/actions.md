---
paths:
  - 'app/Actions/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Actions

## Resolve entity image paths from current scoped persistence
Organization, brand and branch image Actions reload a selected active record inside their transaction before reading the old image path. Retain original organization/brand ownership predicates; stale instances after another save or rollback must not drive cleanup. Save only the fresh image state and synchronize image/timestamp attributes back to the caller without persisting or clearing unrelated dirty fields. Reuse shared rollback/outer-commit media Actions.

## Enforce bulk allocation bounds inside the reusable Action
Component validation alone does not bound direct Action callers. Guard positive ascending range size before range/allocation with overflow-safe arithmetic; preserve transactional model events and throw on rejected required saves. Build successful results from known existing codes and actual saved model values instead of rerunning ownership and preview reads; retain archived-code reservations.
