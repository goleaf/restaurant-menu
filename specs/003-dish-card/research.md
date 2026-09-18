<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Research decisions

- Preserve the current canonical Dish, independent receipts/revisions and guest calculator. A second editor or one giant transaction would duplicate accepted contracts.
- Missing translation row and explicit empty description differ. Use already eager-loaded rows, never a query per locale. Resolve legacy original content only at display/explicit save; no catalogue migration.
- Child menu context follows the immutable dish after an allowed move; child inputs and versions remain owned by their component. Reject archived parent writes in active authoring queries without changing historical withTrashed relations.
- Prepared-preview freshness is distinct from Livewire transport dirty state. Invalidate on relevant state/resource changes; no automatic mutation or expensive recomputation per keystroke.
- Existing local Pro is an adaptation, not a verified upstream release. Official pages/forms/nesting/security/Tabs/upload documentation and installed source inform implementation; Boost JSON failure is recorded, not bypassed with working DB access.
