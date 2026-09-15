---
paths:
  - 'app/Actions/Menus/**'
---

<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Menus

## Bind image mutations to rendered identity and a durable receipt
UI removal and promotion must pass the displayed image-path hash plus a request UUID and actor. Reauthorize and check the actor/branch/item/action/image-bound receipt before looking up a gallery row that may already have been removed. Commit mutation and receipt together; keep failed physical cleanup in the existing menu-operation ledger. Image continuations may only flush that cleanup and must never enter menu/category deletion traversal.
